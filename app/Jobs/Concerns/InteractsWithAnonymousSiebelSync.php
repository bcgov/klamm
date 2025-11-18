<?php

namespace App\Jobs\Concerns;

use App\Models\Anonymizer\AnonymousUpload;
use App\Services\Anonymizer\AnonymizerActivityLogger;
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

    /**
     * Clears previous staging records and streams CSV rows into staging storage.
     */
    protected function ingestToStaging(AnonymousUpload $upload): void
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

            $rawRelationships = html_entity_decode((string) ($assoc['RELATED_COLUMNS'] ?? '')) ?: null;
            $parsedRelationships = $this->parseRelated($rawRelationships ?? '');

            $payload['related_columns_raw'] = $rawRelationships;
            $payload['related_columns'] = $parsedRelationships ? json_encode($parsedRelationships, JSON_UNESCAPED_UNICODE) : null;

            $hashSource = Arr::except($payload, ['related_columns_raw', 'related_columns']);
            $payload['content_hash'] = hash('sha256', json_encode($hashSource, JSON_UNESCAPED_UNICODE));
            $payload['upload_id'] = $upload->id;
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            $batch[] = $payload;
            ++$count;

            if (count($batch) >= 1000) {
                DB::table(self::STAGING_TABLE)->insert($batch);
                $batch = [];
            }
        }

        fclose($stream);

        if ($batch !== []) {
            DB::table(self::STAGING_TABLE)->insert($batch);
        }

        if ($count === 0) {
            throw new RuntimeException('The uploaded CSV did not contain any data rows.');
        }
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
     */
    protected function softDeleteMissingColumns(array $touchedTableIdentities, array $stagingColumnKeys, $now): int
    {
        if ($touchedTableIdentities === []) {
            return 0;
        }

        $deleted = 0;
        $identityFilters = array_values($touchedTableIdentities);

        $tables = DB::table(self::TABLES_TABLE . ' as t')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select('t.id', 's.database_id', 's.schema_name', 't.table_name')
            ->where(function ($query) use ($identityFilters) {
                foreach ($identityFilters as $filter) {
                    $query->orWhere(function ($nested) use ($filter) {
                        $nested
                            ->where('s.database_id', $filter['database_id'])
                            ->where('s.schema_name', $filter['schema_name'])
                            ->where('t.table_name', $filter['table_name']);
                    });
                }
            })
            ->get();

        if ($tables->isEmpty()) {
            return 0;
        }

        $columns = DB::table(self::COLUMNS_TABLE . ' as c')
            ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select('c.id', 'c.deleted_at', 'c.column_name', 't.table_name', 's.schema_name', 's.database_id')
            ->whereIn('c.table_id', $tables->pluck('id'))
            ->get();

        foreach ($columns as $column) {
            $columnKey = $this->columnIdentityKey((int) $column->database_id, $column->schema_name, $column->table_name, $column->column_name);

            if (isset($stagingColumnKeys[$columnKey]) || $column->deleted_at !== null) {
                continue;
            }

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

            ++$deleted;
        }

        return $deleted;
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
