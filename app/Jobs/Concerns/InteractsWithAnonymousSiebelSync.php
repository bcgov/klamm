<?php

namespace App\Jobs\Concerns;

use App\Models\Anonymizer\AnonymousUpload;
use App\Services\Anonymizer\AnonymizerActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Shared utilities for Siebel anonymization synchronization jobs.
 */
trait InteractsWithAnonymousSiebelSync
{
    protected const STAGING_TABLE = 'anonymous_siebel_stagings';
    protected const COLUMNS_TABLE = 'anonymous_siebel_columns';
    protected const TABLES_TABLE = 'anonymous_siebel_tables';
    protected const SCHEMAS_TABLE = 'anonymous_siebel_schemas';
    protected const DATABASES_TABLE = 'anonymous_siebel_databases';
    protected const DATA_TYPES_TABLE = 'anonymous_siebel_data_types';
    protected const DEPENDENCIES_TABLE = 'anonymous_siebel_column_dependencies';
    protected const METADATA_UPSERT_CHUNK_SIZE = 500;

    /**
     * Clears previous staging records and streams CSV rows into staging storage.
     */
    protected function ingestToStaging(AnonymousUpload $upload): int
    {
        DB::table(self::STAGING_TABLE)
            ->where('upload_id', $upload->id)
            ->delete();

        $stream = Storage::disk($upload->file_disk)->readStream($upload->path);
        if (! $stream) {
            throw new RuntimeException('Unable to open upload stream');
        }

        $header = $this->readHeader($stream);
        if ($header === null) {
            fclose($stream);

            throw new RuntimeException('The uploaded CSV did not contain a header row.');
        }

        // Use associative array keyed by unique constraint to deduplicate within each batch
        // For cross-batch duplicates, PostgreSQL's ON CONFLICT DO UPDATE handles it
        // Last occurrence in CSV wins due to the UPSERT update behavior
        $batch = [];
        $now = now();
        $count = 0;

        while (($row = fgetcsv($stream)) !== false) {
            if ($row === null || $row === [null] || $row === ['']) {
                continue;
            }

            $assoc = [];
            foreach ($header as $index => $key) {
                $assoc[$key] = $row[$index] ?? null;
            }

            // Build core payload for hash calculation (excludes relationship fields)
            $payload = [
                'database_name' => trim((string) ($assoc['DATABASE_NAME'] ?? '')),
                'schema_name' => trim((string) ($assoc['SCHEMA_NAME'] ?? '')),
                'object_type' => strtolower(trim((string) ($assoc['OBJECT_TYPE'] ?? 'table'))),
                'table_name' => trim((string) ($assoc['TABLE_NAME'] ?? '')),
                'column_name' => trim((string) ($assoc['COLUMN_NAME'] ?? '')),
                'column_id' => $this->toInt($assoc['COLUMN_ID'] ?? null),
                'data_type' => $this->toNullOrString($assoc['DATA_TYPE'] ?? null),
                'data_length' => $this->toInt($assoc['DATA_LENGTH'] ?? null),
                'data_precision' => $this->toInt($assoc['DATA_PRECISION'] ?? null),
                'data_scale' => $this->toInt($assoc['DATA_SCALE'] ?? null),
                'nullable' => $this->toNullOrString($assoc['NULLABLE'] ?? null),
                'char_length' => $this->toInt($assoc['CHAR_LENGTH'] ?? null),
                'column_comment' => $this->toNullOrString($assoc['COLUMN_COMMENT'] ?? null),
                'table_comment' => $this->toNullOrString($assoc['TABLE_COMMENT'] ?? null),
            ];

            // Calculate content hash before adding relationship fields (avoids Arr::except memory overhead)
            $payload['content_hash'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

            // Add relationship fields after hash calculation
            $rawRelationships = $assoc['RELATED_COLUMNS'] ?? null;
            if ($rawRelationships !== null && $rawRelationships !== '') {
                $rawRelationships = html_entity_decode((string) $rawRelationships);
                $parsedRelationships = $this->parseRelated($rawRelationships);
                $payload['related_columns'] = $parsedRelationships ? json_encode($parsedRelationships, JSON_UNESCAPED_UNICODE) : null;
            } else {
                $rawRelationships = null;
                $payload['related_columns'] = null;
            }
            $payload['related_columns_raw'] = $rawRelationships;

            // Add metadata fields
            $payload['upload_id'] = $upload->id;
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            // Build unique key for within-batch deduplication (last occurrence wins)
            $uniqueKey = implode('|', [
                $payload['upload_id'],
                $this->norm($payload['database_name']),
                $this->norm($payload['schema_name']),
                $this->norm($payload['table_name']),
                $this->norm($payload['column_name']),
            ]);

            $batch[$uniqueKey] = $payload;

            if (count($batch) >= 1000) {
                $count += $this->upsertStagingBatch(array_values($batch));
                $batch = [];

                // Force garbage collection for very large files
                if ($count % 10000 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        fclose($stream);

        if ($batch !== []) {
            $count += $this->upsertStagingBatch(array_values($batch));
            $batch = [];
        }

        // Final cleanup
        gc_collect_cycles();

        if ($count === 0) {
            throw new RuntimeException('The uploaded CSV did not contain any data rows.');
        }

        return $count;
    }

    protected function upsertStagingBatch(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }

        $now = now();

        foreach ($batch as &$row) {
            $row['updated_at'] = $now;
        }

        unset($row);

        DB::table(self::STAGING_TABLE)->upsert(
            $batch,
            ['upload_id', 'database_name', 'schema_name', 'table_name', 'column_name'],
            [
                'object_type',
                'column_id',
                'data_type',
                'data_length',
                'data_precision',
                'data_scale',
                'nullable',
                'char_length',
                'column_comment',
                'table_comment',
                'related_columns_raw',
                'related_columns',
                'content_hash',
                'updated_at',
            ]
        );

        return count($batch);
    }

    protected function chunkedUpsert(string $table, array $rows, array $uniqueBy, array $updateColumns, int $chunkSize = self::METADATA_UPSERT_CHUNK_SIZE): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            // Deduplicate chunk by uniqueBy keys to prevent ON CONFLICT errors
            // Last occurrence wins to match CSV semantics
            $deduplicated = [];
            foreach ($chunk as $row) {
                $key = implode('|', array_map(fn($col) => $this->norm((string) ($row[$col] ?? '')), $uniqueBy));
                $deduplicated[$key] = $row;
            }

            DB::table($table)->upsert(array_values($deduplicated), $uniqueBy, $updateColumns);
        }
    }

    protected function databaseCacheKey(string $databaseName): string
    {
        return $this->norm($databaseName);
    }

    protected function schemaCacheKey(int $databaseId, string $schemaName): string
    {
        return $databaseId . '|' . $this->norm($schemaName);
    }

    protected function tableCacheKey(int $schemaId, string $tableName): string
    {
        return $schemaId . '|' . $this->norm($tableName);
    }

    protected function dataTypeCacheKey(?string $dataType): ?string
    {
        if ($dataType === null || $dataType === '') {
            return null;
        }

        return $this->norm($dataType);
    }

    protected function synchronizeSiebelMetadataFromStaging(int $uploadId, CarbonImmutable $runAt): array
    {
        $databaseMap = $this->refreshDatabasesFromStaging($uploadId, $runAt);
        $schemaMap = $this->refreshSchemasFromStaging($uploadId, $runAt, $databaseMap);
        $tableMap = $this->refreshTablesFromStaging($uploadId, $runAt, $schemaMap, $databaseMap);
        $dataTypeMap = $this->refreshDataTypesFromStaging($uploadId, $runAt);

        return [
            'databases' => $databaseMap,
            'schemas' => $schemaMap,
            'tables' => $tableMap,
            'data_types' => $dataTypeMap,
        ];
    }

    protected function refreshDatabasesFromStaging(int $uploadId, CarbonImmutable $runAt): array
    {
        $names = DB::table(self::STAGING_TABLE)
            ->where('upload_id', $uploadId)
            ->distinct()
            ->pluck('database_name')
            ->filter(fn($name) => $name !== null && trim($name) !== '')
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        $existing = DB::table(self::DATABASES_TABLE)
            ->whereIn('database_name', $names->all())
            ->get();

        $existingMap = [];
        foreach ($existing as $record) {
            $existingMap[$this->databaseCacheKey($record->database_name)] = $record;
        }

        $rows = [];
        foreach ($names as $databaseName) {
            $databaseName = trim((string) $databaseName);
            if ($databaseName === '') {
                continue;
            }

            $key = $this->databaseCacheKey($databaseName);
            $existingRecord = $existingMap[$key] ?? null;
            $hash = $this->hashFor([
                'database_name' => $databaseName,
            ]);

            if ($existingRecord) {
                $diff = [];

                if ($existingRecord->content_hash !== $hash) {
                    $diff['content_hash'] = [
                        'old' => $existingRecord->content_hash,
                        'new' => $hash,
                    ];
                }

                $rows[] = [
                    'database_name' => $databaseName,
                    'description' => $existingRecord->description,
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => $diff ? $runAt : $existingRecord->changed_at,
                    'changed_fields' => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : $existingRecord->changed_fields,
                    'deleted_at' => null,
                    'created_at' => $existingRecord->created_at,
                    'updated_at' => $runAt,
                ];
            } else {
                $rows[] = [
                    'database_name' => $databaseName,
                    'description' => null,
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => null,
                    'changed_fields' => null,
                    'deleted_at' => null,
                    'created_at' => $runAt,
                    'updated_at' => $runAt,
                ];
            }
        }

        $this->chunkedUpsert(
            self::DATABASES_TABLE,
            $rows,
            ['database_name'],
            ['description', 'content_hash', 'last_synced_at', 'changed_at', 'changed_fields', 'deleted_at', 'updated_at']
        );

        $map = [];
        $records = DB::table(self::DATABASES_TABLE)
            ->whereIn('database_name', $names->all())
            ->get();

        foreach ($records as $record) {
            $map[$this->databaseCacheKey($record->database_name)] = [
                'id' => (int) $record->id,
                'name' => $record->database_name,
            ];
        }

        return $map;
    }

    protected function refreshSchemasFromStaging(int $uploadId, CarbonImmutable $runAt, array $databaseMap): array
    {
        if ($databaseMap === []) {
            return [];
        }

        $rows = DB::table(self::STAGING_TABLE)
            ->where('upload_id', $uploadId)
            ->select('database_name', 'schema_name')
            ->distinct()
            ->get();

        $schemas = [];
        $schemaNames = [];
        $databaseIds = [];

        foreach ($rows as $row) {
            $databaseName = trim((string) $row->database_name);
            if ($databaseName === '') {
                continue;
            }

            $databaseKey = $this->databaseCacheKey($databaseName);
            $databaseEntry = $databaseMap[$databaseKey] ?? null;
            if (! $databaseEntry) {
                continue;
            }

            $schemaName = trim((string) $row->schema_name);
            if ($schemaName === '') {
                continue;
            }

            $schemaKey = $this->schemaCacheKey($databaseEntry['id'], $schemaName);
            $schemas[$schemaKey] = [
                'database_id' => $databaseEntry['id'],
                'schema_name' => $schemaName,
            ];

            $schemaNames[$schemaName] = true;
            $databaseIds[$databaseEntry['id']] = true;
        }

        if ($schemas === []) {
            return [];
        }

        $existing = DB::table(self::SCHEMAS_TABLE)
            ->whereIn('database_id', array_keys($databaseIds))
            ->whereIn('schema_name', array_keys($schemaNames))
            ->get();

        $existingMap = [];
        foreach ($existing as $record) {
            $existingMap[$this->schemaCacheKey((int) $record->database_id, $record->schema_name)] = $record;
        }

        $rowsForUpsert = [];
        foreach ($schemas as $schemaKey => $schema) {
            $existingRecord = $existingMap[$schemaKey] ?? null;
            $hash = $this->hashFor([
                'schema_name' => $schema['schema_name'],
                'database_id' => $schema['database_id'],
            ]);

            if ($existingRecord) {
                $diff = [];

                if ((int) $existingRecord->database_id !== $schema['database_id']) {
                    $diff['database_id'] = [
                        'old' => (int) $existingRecord->database_id,
                        'new' => $schema['database_id'],
                    ];
                }

                if ($existingRecord->content_hash !== $hash) {
                    $diff['content_hash'] = [
                        'old' => $existingRecord->content_hash,
                        'new' => $hash,
                    ];
                }

                $rowsForUpsert[] = [
                    'database_id' => $schema['database_id'],
                    'schema_name' => $schema['schema_name'],
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => $diff ? $runAt : $existingRecord->changed_at,
                    'changed_fields' => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : $existingRecord->changed_fields,
                    'deleted_at' => null,
                    'created_at' => $existingRecord->created_at,
                    'updated_at' => $runAt,
                ];
            } else {
                $rowsForUpsert[] = [
                    'database_id' => $schema['database_id'],
                    'schema_name' => $schema['schema_name'],
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => null,
                    'changed_fields' => null,
                    'deleted_at' => null,
                    'created_at' => $runAt,
                    'updated_at' => $runAt,
                ];
            }
        }

        $this->chunkedUpsert(
            self::SCHEMAS_TABLE,
            $rowsForUpsert,
            ['database_id', 'schema_name'],
            ['content_hash', 'last_synced_at', 'changed_at', 'changed_fields', 'deleted_at', 'updated_at']
        );

        $records = DB::table(self::SCHEMAS_TABLE)
            ->whereIn('database_id', array_keys($databaseIds))
            ->whereIn('schema_name', array_keys($schemaNames))
            ->get();

        $map = [];
        foreach ($records as $record) {
            $map[$this->schemaCacheKey((int) $record->database_id, $record->schema_name)] = [
                'id' => (int) $record->id,
                'database_id' => (int) $record->database_id,
                'schema_name' => $record->schema_name,
            ];
        }

        return $map;
    }

    protected function refreshTablesFromStaging(int $uploadId, CarbonImmutable $runAt, array $schemaMap, array $databaseMap): array
    {
        if ($schemaMap === [] || $databaseMap === []) {
            return [];
        }

        $rows = DB::table(self::STAGING_TABLE . ' as s')
            ->where('s.upload_id', $uploadId)
            ->select(
                's.database_name',
                's.schema_name',
                's.table_name',
                DB::raw('max(s.object_type) as object_type'),
                DB::raw('max(s.table_comment) as table_comment')
            )
            ->groupBy('s.database_name', 's.schema_name', 's.table_name')
            ->get();

        $tables = [];
        $schemaIds = [];
        $tableNames = [];

        foreach ($rows as $row) {
            $databaseName = trim((string) $row->database_name);
            $schemaName = trim((string) $row->schema_name);
            $tableName = trim((string) $row->table_name);

            if ($databaseName === '' || $schemaName === '' || $tableName === '') {
                continue;
            }

            $databaseKey = $this->databaseCacheKey($databaseName);
            $databaseEntry = $databaseMap[$databaseKey] ?? null;
            if (! $databaseEntry) {
                continue;
            }

            $schemaKey = $this->schemaCacheKey($databaseEntry['id'], $schemaName);
            $schemaEntry = $schemaMap[$schemaKey] ?? null;

            if (! $schemaEntry) {
                continue;
            }

            $schemaId = $schemaEntry['id'];

            $tableKey = $this->tableCacheKey($schemaId, $tableName);

            $tables[$tableKey] = [
                'schema_id' => $schemaId,
                'database_id' => $schemaEntry['database_id'],
                'table_name' => $tableName,
                'object_type' => $row->object_type ? strtolower($row->object_type) : 'table',
                'table_comment' => $row->table_comment,
            ];

            $schemaIds[$schemaId] = true;
            $tableNames[$tableName] = true;
        }

        if ($tables === []) {
            return [];
        }

        $existing = DB::table(self::TABLES_TABLE)
            ->whereIn('schema_id', array_keys($schemaIds))
            ->whereIn('table_name', array_keys($tableNames))
            ->get();

        $existingMap = [];
        foreach ($existing as $record) {
            $existingMap[$this->tableCacheKey((int) $record->schema_id, $record->table_name)] = $record;
        }

        $rowsForUpsert = [];
        foreach ($tables as $tableKey => $table) {
            $existingRecord = $existingMap[$tableKey] ?? null;
            $hash = $this->hashFor([
                'table_name' => $table['table_name'],
                'schema_id' => $table['schema_id'],
                'object_type' => $table['object_type'],
                'table_comment' => $table['table_comment'],
            ]);

            if ($existingRecord) {
                $diff = [];

                if ($existingRecord->object_type !== $table['object_type']) {
                    $diff['object_type'] = [
                        'old' => $existingRecord->object_type,
                        'new' => $table['object_type'],
                    ];
                }

                if ($existingRecord->table_comment !== $table['table_comment']) {
                    $diff['table_comment'] = [
                        'old' => $existingRecord->table_comment,
                        'new' => $table['table_comment'],
                    ];
                }

                if ($existingRecord->content_hash !== $hash) {
                    $diff['content_hash'] = [
                        'old' => $existingRecord->content_hash,
                        'new' => $hash,
                    ];
                }

                $rowsForUpsert[] = [
                    'schema_id' => $table['schema_id'],
                    'table_name' => $table['table_name'],
                    'object_type' => $table['object_type'],
                    'table_comment' => $table['table_comment'],
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => $diff ? $runAt : $existingRecord->changed_at,
                    'changed_fields' => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : $existingRecord->changed_fields,
                    'deleted_at' => null,
                    'created_at' => $existingRecord->created_at,
                    'updated_at' => $runAt,
                ];
            } else {
                $rowsForUpsert[] = [
                    'schema_id' => $table['schema_id'],
                    'table_name' => $table['table_name'],
                    'object_type' => $table['object_type'],
                    'table_comment' => $table['table_comment'],
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => null,
                    'changed_fields' => null,
                    'deleted_at' => null,
                    'created_at' => $runAt,
                    'updated_at' => $runAt,
                ];
            }
        }

        $this->chunkedUpsert(
            self::TABLES_TABLE,
            $rowsForUpsert,
            ['schema_id', 'table_name'],
            ['object_type', 'table_comment', 'content_hash', 'last_synced_at', 'changed_at', 'changed_fields', 'deleted_at', 'updated_at']
        );

        $records = DB::table(self::TABLES_TABLE)
            ->whereIn('schema_id', array_keys($schemaIds))
            ->whereIn('table_name', array_keys($tableNames))
            ->get();

        $map = [];
        foreach ($records as $record) {
            $map[$this->tableCacheKey((int) $record->schema_id, $record->table_name)] = [
                'id' => (int) $record->id,
                'schema_id' => (int) $record->schema_id,
                'table_name' => $record->table_name,
            ];
        }

        return $map;
    }

    protected function refreshDataTypesFromStaging(int $uploadId, CarbonImmutable $runAt): array
    {
        $names = DB::table(self::STAGING_TABLE)
            ->where('upload_id', $uploadId)
            ->whereNotNull('data_type')
            ->distinct()
            ->pluck('data_type')
            ->filter(fn($name) => $name !== null && trim($name) !== '')
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        $existing = DB::table(self::DATA_TYPES_TABLE)
            ->whereIn('data_type_name', $names->all())
            ->get();

        $existingMap = [];
        foreach ($existing as $record) {
            $key = $this->dataTypeCacheKey($record->data_type_name);
            if ($key !== null) {
                $existingMap[$key] = $record;
            }
        }

        $rows = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $key = $this->dataTypeCacheKey($name);
            if ($key === null) {
                continue;
            }

            $existingRecord = $existingMap[$key] ?? null;

            if ($existingRecord) {
                $rows[] = [
                    'data_type_name' => $name,
                    'description' => $existingRecord->description,
                    'deleted_at' => null,
                    'created_at' => $existingRecord->created_at,
                    'updated_at' => $runAt,
                ];
            } else {
                $rows[] = [
                    'data_type_name' => $name,
                    'description' => null,
                    'deleted_at' => null,
                    'created_at' => $runAt,
                    'updated_at' => $runAt,
                ];
            }
        }

        $this->chunkedUpsert(
            self::DATA_TYPES_TABLE,
            $rows,
            ['data_type_name'],
            ['description', 'deleted_at', 'updated_at']
        );

        $records = DB::table(self::DATA_TYPES_TABLE)
            ->whereIn('data_type_name', $names->all())
            ->get();

        $map = [];
        foreach ($records as $record) {
            $key = $this->dataTypeCacheKey($record->data_type_name);
            if ($key !== null) {
                $map[$key] = [
                    'id' => (int) $record->id,
                    'name' => $record->data_type_name,
                ];
            }
        }

        return $map;
    }

    protected function loadExistingColumnsForChunk(array $tableColumns): array
    {
        if ($tableColumns === []) {
            return [];
        }

        $tableIds = array_keys($tableColumns);
        $columnNames = [];
        foreach ($tableColumns as $columns) {
            foreach ($columns as $columnName) {
                $columnNames[] = $columnName;
            }
        }

        $columnNames = array_values(array_unique($columnNames));

        if ($columnNames === []) {
            return [];
        }

        $records = DB::table(self::COLUMNS_TABLE)
            ->whereIn('table_id', $tableIds)
            ->whereIn('column_name', $columnNames)
            ->get();

        $map = [];
        foreach ($records as $record) {
            $map[$this->columnKey((int) $record->table_id, $record->column_name)] = $record;
        }

        return $map;
    }

    protected function syncColumnsFromStaging(
        AnonymousUpload $upload,
        CarbonImmutable $runAt,
        ?int $totalBytes,
        int $stagingCount,
        array $databaseMap,
        array $schemaMap,
        array $tableMap,
        array $dataTypeMap,
        ?callable $progressReporter = null
    ): array {
        $uploadId = $upload->id;
        $chunkSize = 1500;
        $processedRows = 0;
        $processedBytes = $totalBytes ? 0 : null;
        $totals = [
            'inserted' => 0,
            'updated' => 0,
        ];

        $touchedTableIdentities = [];

        // Use temporary tables to track processed column identities instead of keeping them in memory
        // This prevents memory exhaustion with very large imports
        $tempColumnIdentitiesTable = 'temp_column_identities_' . $uploadId;
        DB::statement("CREATE TEMPORARY TABLE {$tempColumnIdentitiesTable} (column_identity VARCHAR(512) PRIMARY KEY)");

        $columnIdentitiesBatch = [];
        $columnIdentitiesBatchSize = 5000;

        // Use a temporary table to track touched column IDs instead of keeping them in memory
        // This prevents memory exhaustion with very large imports
        $tempTableName = 'temp_touched_columns_' . $uploadId;
        DB::statement("CREATE TEMPORARY TABLE {$tempTableName} (column_id INTEGER PRIMARY KEY)");

        $touchedColumnIdsBatch = [];
        $touchedColumnIdsBatchSize = 5000;
        $lastProgressAt = microtime(true);

        DB::table(self::STAGING_TABLE . ' as s')
            ->where('s.upload_id', $uploadId)
            ->orderBy('s.id')
            ->chunkById($chunkSize, function ($rows) use (
                &$processedRows,
                &$processedBytes,
                &$totals,
                &$touchedTableIdentities,
                &$columnIdentitiesBatch,
                $columnIdentitiesBatchSize,
                $tempColumnIdentitiesTable,
                &$touchedColumnIdsBatch,
                $touchedColumnIdsBatchSize,
                $tempTableName,
                $stagingCount,
                $totalBytes,
                $runAt,
                $databaseMap,
                $schemaMap,
                $tableMap,
                $dataTypeMap,
                $progressReporter,
                &$lastProgressAt
            ) {
                $tableColumns = [];

                foreach ($rows as $row) {
                    $databaseKey = $this->databaseCacheKey($row->database_name);
                    $databaseEntry = $databaseMap[$databaseKey] ?? null;
                    if (! $databaseEntry) {
                        continue;
                    }

                    $schemaKey = $this->schemaCacheKey($databaseEntry['id'], (string) $row->schema_name);
                    $schemaEntry = $schemaMap[$schemaKey] ?? null;
                    if (! $schemaEntry) {
                        continue;
                    }

                    $tableName = trim((string) $row->table_name);
                    if ($tableName === '') {
                        continue;
                    }

                    $tableKey = $this->tableCacheKey($schemaEntry['id'], $tableName);
                    $tableEntry = $tableMap[$tableKey] ?? null;
                    if (! $tableEntry) {
                        continue;
                    }

                    $columnName = trim((string) $row->column_name);
                    if ($columnName === '') {
                        continue;
                    }

                    $tableColumns[$tableEntry['id']][] = $columnName;
                }

                $existingColumns = $this->loadExistingColumnsForChunk($tableColumns);

                $rowsForUpsert = [];
                $logCreated = [];
                $logUpdated = [];
                $logRestored = [];

                foreach ($rows as $row) {
                    $databaseKey = $this->databaseCacheKey($row->database_name);
                    $databaseEntry = $databaseMap[$databaseKey] ?? null;
                    if (! $databaseEntry) {
                        continue;
                    }

                    $schemaKey = $this->schemaCacheKey($databaseEntry['id'], (string) $row->schema_name);
                    $schemaEntry = $schemaMap[$schemaKey] ?? null;
                    if (! $schemaEntry) {
                        continue;
                    }

                    $tableName = trim((string) $row->table_name);
                    if ($tableName === '') {
                        continue;
                    }

                    $tableKey = $this->tableCacheKey($schemaEntry['id'], $tableName);
                    $tableEntry = $tableMap[$tableKey] ?? null;
                    if (! $tableEntry) {
                        continue;
                    }

                    $columnName = trim((string) $row->column_name);
                    if ($columnName === '') {
                        continue;
                    }

                    $columnKey = $this->columnKey($tableEntry['id'], $columnName);
                    $existing = $existingColumns[$columnKey] ?? null;

                    if ($existing) {
                        // Batch the touched column IDs for temporary table insert
                        $touchedColumnIdsBatch[(int) $existing->id] = true;
                        if (count($touchedColumnIdsBatch) >= $touchedColumnIdsBatchSize) {
                            $this->flushTouchedColumnIds($tempTableName, $touchedColumnIdsBatch);
                            $touchedColumnIdsBatch = [];
                        }
                    }

                    $dataTypeKey = $this->dataTypeCacheKey($row->data_type);
                    $dataTypeEntry = $dataTypeKey ? ($dataTypeMap[$dataTypeKey] ?? null) : null;
                    $dataTypeId = $dataTypeEntry['id'] ?? null;

                    $nullableFlag = $this->toNullableFlag($row->nullable);

                    $payload = [
                        'table_id' => $tableEntry['id'],
                        'column_name' => $columnName,
                        'column_id' => $row->column_id,
                        'data_type_id' => $dataTypeId,
                        'data_length' => $row->data_length,
                        'data_precision' => $row->data_precision,
                        'data_scale' => $row->data_scale,
                        'nullable' => $nullableFlag,
                        'char_length' => $row->char_length,
                        'column_comment' => $row->column_comment,
                        'table_comment' => $row->table_comment,
                        'related_columns_raw' => $row->related_columns_raw,
                        'related_columns' => $row->related_columns,
                        'content_hash' => $row->content_hash,
                        'last_synced_at' => $runAt,
                    ];

                    $rowForUpsert = array_merge($payload, [
                        'deleted_at' => null,
                        'updated_at' => $runAt,
                    ]);

                    $fieldsForDiff = array_keys(Arr::except($payload, ['table_id', 'column_name', 'last_synced_at']));

                    if ($existing) {
                        $diff = $this->diffValues($existing, $payload, $fieldsForDiff);
                        $wasDeleted = $existing->deleted_at !== null;
                        $hasChanges = $diff !== [] || $wasDeleted;

                        $rowForUpsert['changed_at'] = $hasChanges ? $runAt : $existing->changed_at;
                        $rowForUpsert['changed_fields'] = $hasChanges ? json_encode($diff, JSON_UNESCAPED_UNICODE) : $existing->changed_fields;
                        $rowForUpsert['last_synced_at'] = $runAt;
                        $rowForUpsert['created_at'] = $existing->created_at;

                        if ($hasChanges) {
                            if ($wasDeleted) {
                                $logRestored[] = [
                                    'id' => (int) $existing->id,
                                    'diff' => [
                                        'deleted_at' => [
                                            'old' => $existing->deleted_at,
                                            'new' => null,
                                        ],
                                    ],
                                ];
                            }

                            if ($diff !== []) {
                                $logUpdated[] = [
                                    'id' => (int) $existing->id,
                                    'diff' => $diff,
                                ];
                            }

                            ++$totals['updated'];
                        }
                    } else {
                        $rowForUpsert['created_at'] = $runAt;
                        $rowForUpsert['changed_at'] = null;
                        $rowForUpsert['changed_fields'] = null;

                        $logCreated[] = [
                            'table_id' => $tableEntry['id'],
                            'column_name' => $columnName,
                        ];

                        ++$totals['inserted'];
                    }

                    $rowsForUpsert[] = $rowForUpsert;

                    $tableIdentityKey = $this->tableIdentityKey($databaseEntry['id'], $row->schema_name, $tableName);
                    $touchedTableIdentities[$tableIdentityKey] = [
                        'database_id' => $databaseEntry['id'],
                        'schema_name' => $row->schema_name,
                        'table_name' => $tableName,
                    ];

                    $columnIdentity = $this->columnIdentityKey($databaseEntry['id'], $row->schema_name, $tableName, $columnName);

                    // Batch the processed column identities for temporary table insert
                    $columnIdentitiesBatch[$columnIdentity] = true;
                    if (count($columnIdentitiesBatch) >= $columnIdentitiesBatchSize) {
                        $this->flushColumnIdentities($tempColumnIdentitiesTable, $columnIdentitiesBatch);
                        $columnIdentitiesBatch = [];
                    }
                }

                if ($rowsForUpsert !== []) {
                    DB::table(self::COLUMNS_TABLE)->upsert(
                        $rowsForUpsert,
                        ['table_id', 'column_name'],
                        [
                            'column_id',
                            'data_type_id',
                            'data_length',
                            'data_precision',
                            'data_scale',
                            'nullable',
                            'char_length',
                            'column_comment',
                            'table_comment',
                            'related_columns_raw',
                            'related_columns',
                            'content_hash',
                            'last_synced_at',
                            'changed_at',
                            'changed_fields',
                            'deleted_at',
                            'updated_at',
                        ]
                    );
                }

                if ($logCreated !== []) {
                    $tableIds = array_unique(array_column($logCreated, 'table_id'));
                    $columnNames = array_unique(array_column($logCreated, 'column_name'));

                    $insertedRecords = DB::table(self::COLUMNS_TABLE)
                        ->whereIn('table_id', $tableIds)
                        ->whereIn('column_name', $columnNames)
                        ->get();

                    $insertedMap = [];
                    foreach ($insertedRecords as $record) {
                        $insertedMap[$this->columnKey((int) $record->table_id, $record->column_name)] = (int) $record->id;
                    }

                    foreach ($logCreated as $entry) {
                        $key = $this->columnKey($entry['table_id'], $entry['column_name']);
                        if (isset($insertedMap[$key])) {
                            AnonymizerActivityLogger::logColumnEvent(
                                $insertedMap[$key],
                                'created',
                                [],
                                [
                                    'upload_id' => $this->uploadId,
                                ]
                            );
                        }
                    }

                    foreach ($insertedMap as $columnId) {
                        // Batch the touched column IDs for temporary table insert
                        $touchedColumnIdsBatch[$columnId] = true;
                        if (count($touchedColumnIdsBatch) >= $touchedColumnIdsBatchSize) {
                            $this->flushTouchedColumnIds($tempTableName, $touchedColumnIdsBatch);
                            $touchedColumnIdsBatch = [];
                        }
                    }
                }

                foreach ($logRestored as $event) {
                    AnonymizerActivityLogger::logColumnEvent(
                        $event['id'],
                        'restored',
                        $event['diff'],
                        [
                            'upload_id' => $this->uploadId,
                        ]
                    );
                }

                foreach ($logUpdated as $event) {
                    AnonymizerActivityLogger::logColumnEvent(
                        $event['id'],
                        'updated',
                        $event['diff'],
                        [
                            'upload_id' => $this->uploadId,
                        ]
                    );
                }

                $processedRows += count($rows);

                // Proactive garbage collection every 10 chunks to prevent memory accumulation
                if ($processedRows % 15000 === 0) {
                    gc_collect_cycles();
                }

                if ($totalBytes) {
                    $processedBytes = (int) min($totalBytes, floor($totalBytes * ($processedRows / max($stagingCount, 1))));
                } else {
                    $processedBytes = $processedRows;
                }

                if ($progressReporter) {
                    $shouldReport = $processedRows <= 5 || ($processedRows % 5000 === 0);
                    $now = microtime(true);
                    if (! $shouldReport && ($now - $lastProgressAt) >= 2.0) {
                        $shouldReport = true;
                    }

                    if ($shouldReport) {
                        $lastProgressAt = $now;
                        $progressReporter([
                            'processed_rows' => $processedRows,
                            'processed_bytes' => $processedBytes,
                            'inserted' => $totals['inserted'],
                            'updated' => $totals['updated'],
                            'status_detail' => sprintf('Upserting columns (%d/%d)', $processedRows, $stagingCount),
                        ]);
                    }
                }
            });

        // Flush any remaining batched touched column IDs
        if (! empty($touchedColumnIdsBatch)) {
            $this->flushTouchedColumnIds($tempTableName, $touchedColumnIdsBatch);
        }

        // Flush any remaining batched column identities
        if (! empty($columnIdentitiesBatch)) {
            $this->flushColumnIdentities($tempColumnIdentitiesTable, $columnIdentitiesBatch);
        }

        // Return temp table names instead of loading all data into memory
        // The consuming methods will query these tables in chunks
        return [
            'totals' => $totals,
            'touchedTableIdentities' => $touchedTableIdentities,
            'processedColumnIdentitiesTempTable' => $tempColumnIdentitiesTable,
            'processedRows' => $stagingCount,
            'processedBytes' => $totalBytes ?? ($processedBytes ?? 0),
            'touchedColumnIdsTempTable' => $tempTableName,
        ];
    }

    /**
     * Flush a batch of touched column IDs to the temporary table.
     * This prevents memory exhaustion by keeping IDs in the database instead of in memory.
     */
    protected function flushTouchedColumnIds(string $tempTableName, array &$batch): void
    {
        if (empty($batch)) {
            return;
        }

        $values = array_map(fn($id) => "({$id})", array_keys($batch));
        $valuesStr = implode(',', $values);

        DB::statement("INSERT INTO {$tempTableName} (column_id) VALUES {$valuesStr} ON CONFLICT (column_id) DO NOTHING");
    }

    /**
     * Flush a batch of processed column identities to the temporary table.
     * This prevents memory exhaustion by keeping identities in the database instead of in memory.
     */
    protected function flushColumnIdentities(string $tempTableName, array &$batch): void
    {
        if (empty($batch)) {
            return;
        }

        $values = array_map(
            fn($identity) => "('" . str_replace("'", "''", $identity) . "')",
            array_keys($batch)
        );
        $valuesStr = implode(',', $values);

        DB::statement("INSERT INTO {$tempTableName} (column_identity) VALUES {$valuesStr} ON CONFLICT (column_identity) DO NOTHING");
    }
    protected function cleanupStaging(int $uploadId): void
    {
        DB::statement('DELETE FROM ' . self::STAGING_TABLE . ' WHERE upload_id = ?', [$uploadId]);
    }

    /**
     * Resolves or creates a database record for the provided name.
     */
    protected function resolveDatabaseId(string $databaseName, $now, array &$cache): int
    {
        $key = $this->norm($databaseName);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $hash = $this->hashFor([
            'database_name' => $databaseName,
        ]);

        $record = DB::table(self::DATABASES_TABLE)
            ->where('database_name', $databaseName)
            ->first();

        if ($record) {
            $updates = [
                'last_synced_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if ($record->content_hash !== $hash) {
                $updates['content_hash'] = $hash;
                $updates['changed_at'] = $now;
                $updates['changed_fields'] = json_encode([
                    'content_hash' => [
                        'old' => $record->content_hash,
                        'new' => $hash,
                    ],
                ]);
            }

            DB::table(self::DATABASES_TABLE)
                ->where('id', $record->id)
                ->update($updates);

            return $cache[$key] = (int) $record->id;
        }

        $id = DB::table(self::DATABASES_TABLE)->insertGetId([
            'database_name' => $databaseName,
            'description' => null,
            'content_hash' => $hash,
            'last_synced_at' => $now,
            'changed_at' => null,
            'changed_fields' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cache[$key] = $id;
    }

    /**
     * Resolves or creates a schema record under a database.
     */
    protected function resolveSchemaId(int $databaseId, string $schemaName, $now, array &$cache): int
    {
        $key = $this->norm($schemaName);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $hash = $this->hashFor([
            'schema_name' => $schemaName,
            'database_id' => $databaseId,
        ]);

        $record = DB::table(self::SCHEMAS_TABLE)
            ->where('schema_name', $schemaName)
            ->where('database_id', $databaseId)
            ->first();

        if ($record) {
            $updates = [
                'database_id' => $databaseId,
                'last_synced_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if ($record->content_hash !== $hash) {
                $updates['content_hash'] = $hash;
                $updates['changed_at'] = $now;
                $updates['changed_fields'] = json_encode([
                    'database_id' => [
                        'old' => $record->database_id,
                        'new' => $databaseId,
                    ],
                ]);
            }

            DB::table(self::SCHEMAS_TABLE)
                ->where('id', $record->id)
                ->update($updates);

            return $cache[$key] = (int) $record->id;
        }

        $id = DB::table(self::SCHEMAS_TABLE)->insertGetId([
            'database_id' => $databaseId,
            'schema_name' => $schemaName,
            'description' => null,
            'type' => null,
            'content_hash' => $hash,
            'last_synced_at' => $now,
            'changed_at' => null,
            'changed_fields' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cache[$key] = $id;
    }

    /**
     * Resolves or creates a table record, updating metadata and comments as needed.
     */
    protected function resolveTableId(
        int $schemaId,
        string $tableName,
        ?string $objectType,
        ?string $tableComment,
        $now,
        array &$cache
    ): int {
        $key = $schemaId . '|' . $this->norm($tableName);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $object = $objectType ? strtolower($objectType) : 'table';
        $hash = $this->hashFor([
            'table_name' => $tableName,
            'schema_id' => $schemaId,
            'object_type' => $object,
            'table_comment' => $tableComment,
        ]);

        $record = DB::table(self::TABLES_TABLE)
            ->where('schema_id', $schemaId)
            ->where('table_name', $tableName)
            ->first();

        if ($record) {
            $updates = [
                'object_type' => $object,
                'table_comment' => $tableComment,
                'content_hash' => $hash,
                'last_synced_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            $diff = $this->diffValues($record, $updates, ['object_type', 'table_comment', 'content_hash']);
            if ($diff !== []) {
                $updates['changed_at'] = $now;
                $updates['changed_fields'] = json_encode($diff, JSON_UNESCAPED_UNICODE);
            }

            DB::table(self::TABLES_TABLE)
                ->where('id', $record->id)
                ->update($updates);

            return $cache[$key] = (int) $record->id;
        }

        $id = DB::table(self::TABLES_TABLE)->insertGetId([
            'schema_id' => $schemaId,
            'object_type' => $object,
            'table_name' => $tableName,
            'table_comment' => $tableComment,
            'content_hash' => $hash,
            'last_synced_at' => $now,
            'changed_at' => null,
            'changed_fields' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cache[$key] = $id;
    }

    /**
     * Resolves or creates a data type reference for the column.
     */
    protected function resolveDataTypeId(?string $dataType, $now, array &$cache): ?int
    {
        if ($dataType === null || $dataType === '') {
            return null;
        }

        $key = $this->norm($dataType);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $record = DB::table(self::DATA_TYPES_TABLE)
            ->where('data_type_name', $dataType)
            ->first();

        if ($record) {
            DB::table(self::DATA_TYPES_TABLE)
                ->where('id', $record->id)
                ->update([
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);

            return $cache[$key] = (int) $record->id;
        }

        $id = DB::table(self::DATA_TYPES_TABLE)->insertGetId([
            'data_type_name' => $dataType,
            'description' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cache[$key] = $id;
    }

    /**
     * Inserts or updates a canonical column record from the staging payload.
     */
    protected function upsertColumn(int $tableId, ?int $dataTypeId, object $row, $now, array &$columnCache = []): array
    {
        $columnName = trim($row->column_name);
        $cacheKey = $this->norm($columnName);

        $existing = $columnCache[$tableId][$cacheKey] ?? null;

        if (! $existing) {
            $existing = DB::table(self::COLUMNS_TABLE)
                ->where('table_id', $tableId)
                ->where('column_name', $columnName)
                ->first();

            if ($existing) {
                $columnCache[$tableId][$cacheKey] = $existing;
            }
        }

        $relationships = $this->extractRelationshipsFromRow($row);
        $relationshipsJson = $relationships ? json_encode($relationships, JSON_UNESCAPED_UNICODE) : null;

        $payload = [
            'table_id' => $tableId,
            'column_name' => $columnName,
            'column_id' => $row->column_id,
            'data_type_id' => $dataTypeId,
            'data_length' => $row->data_length,
            'data_precision' => $row->data_precision,
            'data_scale' => $row->data_scale,
            'nullable' => $this->toNullableFlag($row->nullable),
            'char_length' => $row->char_length,
            'column_comment' => $row->column_comment,
            'table_comment' => $row->table_comment,
            'related_columns_raw' => $row->related_columns_raw,
            'related_columns' => $relationshipsJson,
            'content_hash' => $row->content_hash,
            'last_synced_at' => $now,
        ];

        if ($existing) {
            $diff = $this->diffValues($existing, $payload, array_keys(Arr::except($payload, ['table_id', 'column_name'])));

            $updates = $payload;
            $updates['updated_at'] = $now;
            $updates['deleted_at'] = null;

            if ($diff !== []) {
                $updates['changed_at'] = $now;
                $updates['changed_fields'] = json_encode($diff, JSON_UNESCAPED_UNICODE);
            }

            DB::table(self::COLUMNS_TABLE)
                ->where('id', $existing->id)
                ->update($updates);

            $updatedRecord = clone $existing;
            foreach ($updates as $field => $value) {
                $updatedRecord->{$field} = $value;
            }
            $columnCache[$tableId][$cacheKey] = $updatedRecord;

            $wasResurrected = $existing->deleted_at !== null;

            if ($wasResurrected) {
                AnonymizerActivityLogger::logColumnEvent(
                    (int) $existing->id,
                    'restored',
                    [
                        'deleted_at' => [
                            'old' => $existing->deleted_at,
                            'new' => null,
                        ],
                    ],
                    [
                        'upload_id' => $this->uploadId,
                    ]
                );
            }

            if ($diff !== []) {
                AnonymizerActivityLogger::logColumnEvent(
                    (int) $existing->id,
                    'updated',
                    $diff,
                    [
                        'upload_id' => $this->uploadId,
                    ]
                );
            }

            return [
                'id' => (int) $existing->id,
                'inserted' => 0,
                'updated' => ($diff !== [] || $wasResurrected) ? 1 : 0,
            ];
        }

        $id = DB::table(self::COLUMNS_TABLE)->insertGetId(array_merge($payload, [
            'changed_at' => null,
            'changed_fields' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $record = (object) array_merge($payload, [
            'id' => $id,
            'deleted_at' => null,
            'changed_at' => null,
            'changed_fields' => null,
        ]);
        $columnCache[$tableId][$cacheKey] = $record;

        AnonymizerActivityLogger::logColumnEvent(
            $id,
            'created',
            [],
            [
                'upload_id' => $this->uploadId,
            ]
        );

        return [
            'id' => $id,
            'inserted' => 1,
            'updated' => 0,
        ];
    }

    /**
     * Chooses the best relationship representation available for a row.
     */
    protected function extractRelationshipsFromRow(object $row): array
    {
        if ($row->related_columns) {
            $decoded = json_decode($row->related_columns, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn($item) => is_array($item)));
            }
        }

        if (! $row->related_columns_raw) {
            return [];
        }

        return $this->parseRelated($row->related_columns_raw) ?: [];
    }

    /**
     * Soft deletes any existing columns that were not present in the latest upload.
     *
     * @param array $touchedTableIdentities Array of table identities that were touched
     * @param string $tempTableName Name of temporary table containing processed column identities
     * @param mixed $now Timestamp for the deletion
     */
    protected function softDeleteMissingColumns(array $touchedTableIdentities, string $tempTableName, $now): int
    {
        if ($touchedTableIdentities === []) {
            return 0;
        }

        $deleted = 0;
        $identityFilters = array_values($touchedTableIdentities);

        // Create a temporary table for table identities to avoid parameter limit issues
        // PostgreSQL has a limit of 65,535 parameters per query, and with 3 params per table
        // we can hit this limit with large imports (>20k tables)
        $tempTableIdentitiesTable = 'temp_table_identities_' . uniqid();
        DB::statement("CREATE TEMPORARY TABLE {$tempTableIdentitiesTable} (
            database_id INTEGER NOT NULL,
            schema_name VARCHAR(255) NOT NULL,
            table_name VARCHAR(255) NOT NULL
        )");

        // Insert table identities in chunks to stay within parameter limits
        // Each insert has 3 params, so chunk at ~20k to be safe
        foreach (array_chunk($identityFilters, 20000) as $chunk) {
            $insertData = [];
            foreach ($chunk as $filter) {
                $insertData[] = [
                    'database_id' => $filter['database_id'],
                    'schema_name' => $filter['schema_name'],
                    'table_name' => $filter['table_name'],
                ];
            }
            if (!empty($insertData)) {
                DB::table($tempTableIdentitiesTable)->insert($insertData);
            }
        }

        // Create a temp table to store table IDs that match our identity filters
        $tempMatchedTablesTable = 'temp_matched_tables_' . uniqid();
        DB::statement("CREATE TEMPORARY TABLE {$tempMatchedTablesTable} (
            table_id INTEGER PRIMARY KEY
        )");

        // Insert matching table IDs using a join (avoids loading into PHP memory)
        DB::statement("
            INSERT INTO {$tempMatchedTablesTable} (table_id)
            SELECT t.id
            FROM " . self::TABLES_TABLE . " t
            INNER JOIN " . self::SCHEMAS_TABLE . " s ON t.schema_id = s.id
            INNER JOIN {$tempTableIdentitiesTable} ti
                ON s.database_id = ti.database_id
                AND s.schema_name = ti.schema_name
                AND t.table_name = ti.table_name
        ");

        // Check if we have any matched tables
        $matchedCount = DB::table($tempMatchedTablesTable)->count();
        if ($matchedCount === 0) {
            DB::statement("DROP TABLE IF EXISTS {$tempTableIdentitiesTable}");
            DB::statement("DROP TABLE IF EXISTS {$tempMatchedTablesTable}");
            return 0;
        }

        // Create a temporary table with column identities to potentially delete
        $tempDeleteCandidatesTable = 'temp_delete_candidates_' . uniqid();
        DB::statement("CREATE TEMPORARY TABLE {$tempDeleteCandidatesTable} (
            column_id INTEGER PRIMARY KEY,
            column_identity VARCHAR(512)
        )");

        // Insert all non-deleted columns from touched tables into temp table with their identities
        // Process in chunks to avoid memory issues, but use the temp table for the join
        $lastProcessedId = 0;
        $chunkSize = 500;

        do {
            $columns = DB::table(self::COLUMNS_TABLE . ' as c')
                ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
                ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
                ->join($tempMatchedTablesTable . ' as mt', 'c.table_id', '=', 'mt.table_id')
                ->select('c.id', 'c.column_name', 't.table_name', 's.schema_name', 's.database_id')
                ->where('c.id', '>', $lastProcessedId)
                ->whereNull('c.deleted_at')
                ->orderBy('c.id')
                ->limit($chunkSize)
                ->get();

            if ($columns->isEmpty()) {
                break;
            }

            $insertData = [];
            foreach ($columns as $column) {
                $columnKey = $this->columnIdentityKey(
                    (int) $column->database_id,
                    $column->schema_name,
                    $column->table_name,
                    $column->column_name
                );
                $insertData[] = [
                    'column_id' => $column->id,
                    'column_identity' => $columnKey,
                ];
                $lastProcessedId = $column->id;
            }

            if (!empty($insertData)) {
                DB::table($tempDeleteCandidatesTable)->insert($insertData);
            }

            // Free memory
            unset($columns, $insertData);
        } while (true);

        // Create a temp table to store column IDs that need to be deleted
        // (those in candidates but NOT in processed identities)
        $tempColumnsToDeleteTable = 'temp_columns_to_delete_' . uniqid();
        DB::statement("CREATE TEMPORARY TABLE {$tempColumnsToDeleteTable} (
            column_id INTEGER PRIMARY KEY
        )");

        // Use SQL to find columns to delete (avoids loading into PHP memory)
        DB::statement("
            INSERT INTO {$tempColumnsToDeleteTable} (column_id)
            SELECT dc.column_id
            FROM {$tempDeleteCandidatesTable} dc
            LEFT JOIN {$tempTableName} processed ON dc.column_identity = processed.column_identity
            WHERE processed.column_identity IS NULL
        ");

        // Process deletions in chunks using cursor-based pagination
        $lastDeletedId = 0;
        $deleteChunkSize = 100;

        do {
            $columnsToProcess = DB::table(self::COLUMNS_TABLE . ' as c')
                ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
                ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
                ->join($tempColumnsToDeleteTable . ' as del', 'c.id', '=', 'del.column_id')
                ->select('c.id', 's.database_id', 's.schema_name', 't.table_name', 'c.column_name')
                ->where('c.id', '>', $lastDeletedId)
                ->orderBy('c.id')
                ->limit($deleteChunkSize)
                ->get();

            if ($columnsToProcess->isEmpty()) {
                break;
            }

            foreach ($columnsToProcess as $column) {
                $diff = [
                    'deleted_at' => [
                        'old' => null,
                        'new' => $now,
                    ],
                ];

                DB::table(self::COLUMNS_TABLE)
                    ->where('id', $column->id)
                    ->update([
                        'deleted_at' => $now,
                        'changed_at' => $now,
                        'changed_fields' => json_encode($diff, JSON_UNESCAPED_UNICODE),
                        'updated_at' => $now,
                    ]);

                AnonymizerActivityLogger::logColumnEvent(
                    (int) $column->id,
                    'deleted',
                    $diff,
                    [
                        'upload_id' => $this->uploadId,
                    ]
                );

                $lastDeletedId = $column->id;
                ++$deleted;
            }

            // Free memory
            unset($columnsToProcess);
        } while (true);

        // Clean up temporary tables
        DB::statement("DROP TABLE IF EXISTS {$tempColumnsToDeleteTable}");
        DB::statement("DROP TABLE IF EXISTS {$tempDeleteCandidatesTable}");
        DB::statement("DROP TABLE IF EXISTS {$tempMatchedTablesTable}");
        DB::statement("DROP TABLE IF EXISTS {$tempTableIdentitiesTable}");

        return $deleted;
    }
    /**
     * Rebuild column relationships from a temporary table containing column IDs.
     * Processes in chunks to avoid memory exhaustion.
     *
     * @param string $tempTableName Name of temporary table containing column IDs
     * @param CarbonImmutable $runAt Timestamp for the update
     */
    protected function rebuildColumnRelationships(string $tempTableName, CarbonImmutable $runAt): void
    {
        $chunkSize = 1000;
        $processedChunks = 0;

        // Process column IDs from temp table in chunks
        DB::table($tempTableName)
            ->orderBy('column_id')
            ->chunk($chunkSize, function ($tempRows) use ($runAt, &$processedChunks) {
                $chunkIds = $tempRows->pluck('column_id')->map(fn($id) => (int) $id)->all();

                if (empty($chunkIds)) {
                    return;
                }

                $this->processColumnRelationshipsChunk($chunkIds, $runAt);

                // Garbage collection every 10 chunks
                $processedChunks++;
                if ($processedChunks % 10 === 0) {
                    gc_collect_cycles();
                }
            });
    }

    /**
     * Process a chunk of column IDs for relationship rebuilding.
     */
    protected function processColumnRelationshipsChunk(array $chunkIds, CarbonImmutable $runAt): void
    {
        $rows = DB::table(self::COLUMNS_TABLE . ' as c')
            ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select(
                'c.id',
                'c.table_id',
                'c.column_name',
                'c.related_columns',
                'c.related_columns_raw',
                's.schema_name',
                't.table_name'
            )
            ->whereIn('c.id', $chunkIds)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $columnMeta = [];
        $relationshipsByColumn = [];
        $referencedColumns = [];
        $touchedColumnIds = [];

        foreach ($rows as $row) {
            $columnKey = $this->columnKey((int) $row->table_id, $row->column_name);

            $columnMeta[$columnKey] = [
                'id' => (int) $row->id,
                'table_id' => (int) $row->table_id,
                'schema_name' => $row->schema_name,
                'table_name' => $row->table_name,
                'column_name' => $row->column_name,
            ];

            $touchedColumnIds[(int) $row->id] = true;

            $relationships = $this->extractRelationshipsFromRow($row);
            if ($relationships === []) {
                continue;
            }

            $relationshipsByColumn[$columnKey] = $relationships;

            foreach ($relationships as $relation) {
                if (! isset($relation['schema'], $relation['table'], $relation['column'])) {
                    continue;
                }

                $referenceKey = $this->tripletKey($relation['schema'], $relation['table'], $relation['column']);

                if (! isset($referencedColumns[$referenceKey])) {
                    $referencedColumns[$referenceKey] = [
                        'schema' => $relation['schema'],
                        'table' => $relation['table'],
                        'column' => $relation['column'],
                    ];
                }
            }
        }

        $this->syncRelationships($columnMeta, $relationshipsByColumn, $referencedColumns, $touchedColumnIds, $runAt);
    }

    /**
     * Rebuilds dependency edges for every column touched during the sync.
     */
    protected function syncRelationships(
        array $columnMeta,
        array $relationshipsByColumn,
        array $referencedColumns,
        array $touchedColumnIds,
        $now
    ): void {
        if ($relationshipsByColumn === [] && $touchedColumnIds === []) {
            return;
        }

        $columnIndex = $this->buildColumnIndex($columnMeta, $referencedColumns);

        $touchedIds = array_keys($touchedColumnIds);
        if ($touchedIds !== []) {
            DB::table(self::DEPENDENCIES_TABLE)
                ->whereIn('child_field_id', $touchedIds)
                ->delete();
        }

        if ($relationshipsByColumn === []) {
            return;
        }

        $rows = [];
        $seen = [];

        foreach ($relationshipsByColumn as $columnKey => $relations) {
            $childId = $columnIndex['byKey'][$columnKey] ?? null;
            if (! $childId) {
                continue;
            }

            foreach ($relations as $relation) {
                if (! isset($relation['schema'], $relation['table'], $relation['column'])) {
                    continue;
                }

                $targetKey = $this->tripletKey($relation['schema'], $relation['table'], $relation['column']);
                $targetId = $columnIndex['byTriplet'][$targetKey] ?? null;
                if (! $targetId) {
                    continue;
                }

                $direction = strtoupper($relation['direction'] ?? 'OUTBOUND');

                if ($direction === 'OUTBOUND') {
                    $parentId = $targetId;
                    $childIdForRow = $childId;
                } else {
                    $parentId = $childId;
                    $childIdForRow = $targetId;
                }

                if (! $childIdForRow) {
                    continue;
                }

                $signature = ($parentId ?? 'null') . '|' . $childIdForRow;
                if (isset($seen[$signature])) {
                    continue;
                }
                $seen[$signature] = true;

                $rows[] = [
                    'parent_field_id' => $parentId,
                    'child_field_id' => $childIdForRow,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table(self::DEPENDENCIES_TABLE)->insert($rows);
        }
    }

    /**
     * Builds lookup maps to resolve column IDs from either local metadata or referenced triples.
     */
    protected function buildColumnIndex(array $columnMeta, array $referencedColumns): array
    {
        $byKey = [];
        $byTriplet = [];

        foreach ($columnMeta as $columnKey => $meta) {
            if (! isset($meta['id'])) {
                continue;
            }

            $byKey[$columnKey] = $meta['id'];
            $triplet = $this->tripletKey($meta['schema_name'], $meta['table_name'], $meta['column_name']);
            $byTriplet[$triplet] = $meta['id'];
        }

        $missing = array_diff_key($referencedColumns, $byTriplet);
        if ($missing === []) {
            return [
                'byKey' => $byKey,
                'byTriplet' => $byTriplet,
            ];
        }

        DB::table(self::COLUMNS_TABLE . ' as c')
            ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select('c.id', 's.schema_name', 't.table_name', 'c.column_name')
            ->where(function ($query) use ($missing) {
                foreach ($missing as $ref) {
                    $query->orWhere(function ($nested) use ($ref) {
                        $nested
                            ->where('s.schema_name', $ref['schema'])
                            ->where('t.table_name', $ref['table'])
                            ->where('c.column_name', $ref['column']);
                    });
                }
            })
            ->orderBy('c.id')
            ->chunk(500, function ($chunk) use (&$byTriplet) {
                foreach ($chunk as $record) {
                    $triplet = $this->tripletKey($record->schema_name, $record->table_name, $record->column_name);
                    $byTriplet[$triplet] = (int) $record->id;
                }
            });

        return [
            'byKey' => $byKey,
            'byTriplet' => $byTriplet,
        ];
    }

    /**
     * Reads the header row from the provided CSV stream.
     */
    protected function readHeader($stream): ?array
    {
        $header = fgetcsv($stream);
        if ($header === false || $header === null) {
            return null;
        }

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
        }

        return array_map(static fn($value) => strtoupper(trim((string) $value)), $header);
    }

    /**
     * Parses relationship descriptors into structured arrays.
     */
    protected function parseRelated(string $raw): array
    {
        $raw = trim(html_entity_decode($raw));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*;\s*/', $raw) ?: [];
        $relationships = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $pattern = '/^(INBOUND|OUTBOUND)\s*(<-|->)\s*([^.]+)\.([^.]+)\.([^\s]+)(?:\s+via\s+(\S+))?/i';
            if (preg_match($pattern, $part, $matches)) {
                $relationships[] = [
                    'direction' => strtoupper($matches[1]),
                    'arrow' => $matches[2],
                    'schema' => trim($matches[3]),
                    'table' => trim($matches[4]),
                    'column' => trim($matches[5], ','),
                    'constraint' => $matches[6] ?? null,
                ];
                continue;
            }

            $relationships[] = [
                'descriptor' => $part,
            ];
        }

        return $relationships;
    }

    /**
     * Produces a diff array describing changes between stored and new values.
     */
    protected function diffValues(object $existing, array $payload, array $fields): array
    {
        $diff = [];

        foreach ($fields as $field) {
            if ($field === 'changed_fields' || $field === 'changed_at') {
                continue;
            }

            $old = $existing->{$field} ?? null;
            $new = $payload[$field] ?? null;

            if ($this->valuesDiffer($old, $new)) {
                $diff[$field] = [
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        return $diff;
    }

    /**
     * Determines whether two values differ while accounting for type juggling.
     */
    protected function valuesDiffer($old, $new): bool
    {
        if ($old === null && $new === null) {
            return false;
        }

        if (is_bool($old) || is_bool($new)) {
            return (bool) $old !== (bool) $new;
        }

        if (is_numeric($old) || is_numeric($new)) {
            return (float) $old !== (float) $new;
        }

        return (string) $old !== (string) $new;
    }

    /**
     * Typed helper utilities to normalize CSV scalar values.
     */
    protected function toInt($value): ?int
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    protected function toNullOrString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function toNullableFlag($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, ['Y', 'YES', 'TRUE'], true)) {
            return true;
        }

        if (in_array($normalized, ['N', 'NO', 'FALSE'], true)) {
            return false;
        }

        return null;
    }

    /**
     * Hashing and key helpers shared across the sync pipeline.
     */
    protected function hashFor(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    protected function columnKey(int $tableId, string $columnName): string
    {
        return $tableId . '|' . $this->norm($columnName);
    }

    protected function tableIdentityKey(int $databaseId, string $schemaName, string $tableName): string
    {
        return $databaseId . '|' . $this->norm($schemaName) . '|' . $this->norm($tableName);
    }

    protected function columnIdentityKey(int $databaseId, string $schemaName, string $tableName, string $columnName): string
    {
        return $this->tableIdentityKey($databaseId, $schemaName, $tableName) . '|' . $this->norm($columnName);
    }

    protected function tripletKey(string $schema, string $table, string $column): string
    {
        return $this->norm($schema) . '|' . $this->norm($table) . '|' . $this->norm($column);
    }

    protected function norm(string $value): string
    {
        return Str::upper(trim($value));
    }
}
