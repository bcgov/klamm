<?php

namespace App\Jobs;

use App\Models\Anonymizer\AnonymousUpload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Job that synchronizes anonymized Siebel metadata from uploaded CSV files.
 */
class SyncAnonymousSiebelColumnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const STAGING_TABLE = 'anonymous_siebel_stagings';
    private const COLUMNS_TABLE = 'anonymous_siebel_columns';
    private const TABLES_TABLE = 'anonymous_siebel_tables';
    private const SCHEMAS_TABLE = 'anonymous_siebel_schemas';
    private const DATABASES_TABLE = 'anonymous_siebel_databases';
    private const DATA_TYPES_TABLE = 'anonymous_siebel_data_types';
    private const DEPENDENCIES_TABLE = 'anonymous_siebel_column_dependencies';

    public function __construct(public int $uploadId) {}

    /**
     * Orchestrates ingest and synchronization for the targeted upload.
     */
    public function handle(): void
    {
        $upload = AnonymousUpload::findOrFail($this->uploadId);

        $upload->update([
            'status' => 'processing',
            'error' => null,
        ]);

        try {
            // Load fresh data from the CSV into the staging table.
            $this->ingestToStaging($upload);

            $totals = DB::transaction(fn() => $this->syncFromStaging($upload));

            $upload->update([
                'status' => 'completed',
                'inserted' => $totals['inserted'],
                'updated' => $totals['updated'],
                'deleted' => $totals['deleted'],
            ]);
        } catch (Throwable $e) {
            // Record the failure reason on the upload before bubbling the exception.
            $upload->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Clears previous staging records and streams CSV rows into staging storage.
     */
    private function ingestToStaging(AnonymousUpload $upload): void
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
            // Skip blank lines to avoid inserting empty records.
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

            // Capture raw relationship descriptors and normalized JSON structures.
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
                // Bulk insert for performance when the batch threshold is reached.
                DB::table(self::STAGING_TABLE)->insert($batch);
                $batch = [];
            }
        }

        fclose($stream);

        if ($batch !== []) {
            DB::table(self::STAGING_TABLE)->insert($batch);
        }

        if ($count === 0) {
            // Reject uploads that contain headers only.
            throw new RuntimeException('The uploaded CSV did not contain any data rows.');
        }
    }

    /**
     * Promotes staged rows into the normalized metadata tables and tracks totals.
     */
    private function syncFromStaging(AnonymousUpload $upload): array
    {
        $now = now();
        $inserted = 0;
        $updated = 0;
        $deleted = 0;

        $databaseCache = [];
        $schemaCache = [];
        $tableCache = [];
        $dataTypeCache = [];

        $stagingColumnKeys = [];
        $columnMeta = [];
        $relationshipsByColumn = [];
        $referencedColumns = [];
        $touchedTableIds = [];
        $touchedColumnIds = [];

        DB::table(self::STAGING_TABLE)
            ->where('upload_id', $upload->id)
            ->chunkById(500, function ($chunk) use (
                $now,
                &$databaseCache,
                &$schemaCache,
                &$tableCache,
                &$dataTypeCache,
                &$stagingColumnKeys,
                &$columnMeta,
                &$relationshipsByColumn,
                &$referencedColumns,
                &$touchedTableIds,
                &$touchedColumnIds,
                &$inserted,
                &$updated
            ) {
                foreach ($chunk as $row) {
                    // Skip partially defined rows that cannot be normalized.
                    if (! $row->database_name || ! $row->schema_name || ! $row->table_name || ! $row->column_name) {
                        continue;
                    }

                    $databaseId = $this->resolveDatabaseId($row->database_name, $now, $databaseCache);
                    $schemaId = $this->resolveSchemaId($databaseId, $row->schema_name, $now, $schemaCache);
                    $tableId = $this->resolveTableId(
                        $schemaId,
                        $row->table_name,
                        $row->object_type,
                        $row->table_comment,
                        $now,
                        $tableCache
                    );
                    $dataTypeId = $this->resolveDataTypeId($row->data_type, $now, $dataTypeCache);

                    $columnResult = $this->upsertColumn($tableId, $dataTypeId, $row, $now);
                    $inserted += $columnResult['inserted'];
                    $updated += $columnResult['updated'];

                    $columnKey = $this->columnKey($tableId, $row->column_name);
                    $stagingColumnKeys[$columnKey] = true;
                    $touchedTableIds[$tableId] = true;
                    $touchedColumnIds[$columnResult['id']] = true;

                    $meta = [
                        'id' => $columnResult['id'],
                        'schema_name' => $row->schema_name,
                        'table_name' => $row->table_name,
                        'column_name' => $row->column_name,
                        'table_id' => $tableId,
                    ];
                    $columnMeta[$columnKey] = $meta;

                    $relations = $this->extractRelationshipsFromRow($row);
                    if ($relations !== []) {
                        $relationshipsByColumn[$columnKey] = $relations;

                        foreach ($relations as $rel) {
                            if (! isset($rel['schema'], $rel['table'], $rel['column'])) {
                                continue;
                            }

                            $refKey = $this->tripletKey($rel['schema'], $rel['table'], $rel['column']);
                            $referencedColumns[$refKey] = $rel;
                        }
                    }
                }
            });

        // Soft-delete legacy columns that were not present in the current upload.
        $deleted += $this->softDeleteMissingColumns($touchedTableIds, $stagingColumnKeys, $now);

        if ($touchedColumnIds !== []) {
            // Refresh dependency edges for any columns touched in this run.
            $this->syncRelationships(
                $columnMeta,
                $relationshipsByColumn,
                $referencedColumns,
                $touchedColumnIds,
                $now
            );
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'deleted' => $deleted,
        ];
    }

    /**
     * Resolves or creates a database record for the provided name.
     */
    private function resolveDatabaseId(string $databaseName, $now, array &$cache): int
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
    private function resolveSchemaId(int $databaseId, string $schemaName, $now, array &$cache): int
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
    private function resolveTableId(
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
    private function resolveDataTypeId(?string $dataType, $now, array &$cache): ?int
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
    private function upsertColumn(int $tableId, ?int $dataTypeId, object $row, $now): array
    {
        $columnName = trim($row->column_name);

        $existing = DB::table(self::COLUMNS_TABLE)
            ->where('table_id', $tableId)
            ->where('column_name', $columnName)
            ->first();

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
            // Capture differences to drive change tracking fields.
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

            $wasResurrected = $existing->deleted_at !== null;

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

        return [
            'id' => $id,
            'inserted' => 1,
            'updated' => 0,
        ];
    }

    /**
     * Chooses the best relationship representation available for a row.
     */
    private function extractRelationshipsFromRow(object $row): array
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
    private function softDeleteMissingColumns(array $touchedTableIds, array $stagingColumnKeys, $now): int
    {
        if ($touchedTableIds === []) {
            return 0;
        }

        $deleted = 0;

        $columns = DB::table(self::COLUMNS_TABLE)
            ->whereIn('table_id', array_keys($touchedTableIds))
            ->get();

        foreach ($columns as $column) {
            $key = $this->columnKey($column->table_id, $column->column_name);

            if (isset($stagingColumnKeys[$key]) || $column->deleted_at !== null) {
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

            ++$deleted;
        }

        return $deleted;
    }

    /**
     * Rebuilds dependency edges for every column touched during the sync.
     */
    private function syncRelationships(
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

                // Choose parent/child assignment based on declared directionality.
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
    private function buildColumnIndex(array $columnMeta, array $referencedColumns): array
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
    private function readHeader($stream): ?array
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
    private function parseRelated(string $raw): array
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
    private function diffValues(object $existing, array $payload, array $fields): array
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
    private function valuesDiffer($old, $new): bool
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
    private function toInt($value): ?int
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    private function toNullOrString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toNullableFlag($value): ?bool
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
    private function hashFor(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function columnKey(int $tableId, string $columnName): string
    {
        return $tableId . '|' . $this->norm($columnName);
    }

    private function tripletKey(string $schema, string $table, string $column): string
    {
        return $this->norm($schema) . '|' . $this->norm($table) . '|' . $this->norm($column);
    }

    private function norm(string $value): string
    {
        return Str::upper(trim($value));
    }
}
