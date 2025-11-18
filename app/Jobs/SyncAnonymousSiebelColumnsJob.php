<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithAnonymousSiebelSync;
use App\Models\Anonymizer\AnonymousUpload;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Job that orchestrates anonymized Siebel metadata synchronization by delegating work to chunked jobs.
 */
class SyncAnonymousSiebelColumnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use InteractsWithAnonymousSiebelSync;

    public function __construct(public int $uploadId) {}

    public int $timeout = 600;

    private const CHUNK_SIZE = 1000;

    /**
     * Streams the upload into staging and delegates processing to smaller queue jobs.
     */
    public function handle(): void
    {
        $upload = AnonymousUpload::findOrFail($this->uploadId);

        $upload->update([
            'status' => 'processing',
            'error' => null,
        ]);

        $runAt = CarbonImmutable::now();
        $uploadId = $this->uploadId;

        try {
            $this->ingestToStaging($upload);

            $chunks = [];
            DB::table(self::STAGING_TABLE)
                ->select('id')
                ->where('upload_id', $upload->id)
                ->orderBy('id')
                ->chunk(self::CHUNK_SIZE, function ($rows) use (&$chunks) {
                    if ($rows->isEmpty()) {
                        return;
                    }

                    $chunks[] = [
                        'start' => $rows->first()->id,
                        'end' => $rows->last()->id,
                    ];
                });

            if ($chunks === []) {
                throw new RuntimeException('No staging rows available for processing.');
            }

            $jobs = array_map(
                fn($bounds) => new ProcessAnonymousSiebelColumnsChunkJob(
                    $uploadId,
                    $runAt->toIso8601String(),
                    $bounds['start'],
                    $bounds['end']
                ),
                $chunks
            );

            Bus::batch($jobs)
                ->name('anonymous-siebel-sync:' . $uploadId)
                ->then(fn() => dispatch(new FinalizeAnonymousSiebelColumnsJob($uploadId, $runAt->toIso8601String())))
                ->catch(function (Batch $batch, Throwable $exception) use ($uploadId) {
                    if ($upload = AnonymousUpload::find($uploadId)) {
                        $upload->update([
                            'status' => 'failed',
                            'error' => $exception->getMessage(),
                        ]);
                    }
                })
                ->dispatch();
        } catch (Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}

class ProcessAnonymousSiebelColumnsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
    use InteractsWithAnonymousSiebelSync;

    public function __construct(
        public int $uploadId,
        public string $runTimestamp,
        public int $startId,
        public int $endId
    ) {}

    public int $timeout = 600;

    public function handle(): void
    {
        $runAt = CarbonImmutable::parse($this->runTimestamp);

        $rows = DB::table(self::STAGING_TABLE)
            ->where('upload_id', $this->uploadId)
            ->whereBetween('id', [$this->startId, $this->endId])
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $databaseCache = [];
        $schemaCache = [];
        $tableCache = [];
        $dataTypeCache = [];

        foreach ($rows as $row) {
            if (! $row->database_name || ! $row->schema_name || ! $row->table_name || ! $row->column_name) {
                continue;
            }

            $databaseId = $this->resolveDatabaseId($row->database_name, $runAt, $databaseCache);

            $schemaCache[$databaseId] ??= [];
            $schemaId = $this->resolveSchemaId($databaseId, $row->schema_name, $runAt, $schemaCache[$databaseId]);

            $tableCache[$schemaId] ??= [];
            $tableId = $this->resolveTableId(
                $schemaId,
                $row->table_name,
                $row->object_type,
                $row->table_comment,
                $runAt,
                $tableCache[$schemaId]
            );

            $dataTypeId = $this->resolveDataTypeId($row->data_type, $runAt, $dataTypeCache);

            $this->upsertColumn($tableId, $dataTypeId, $row, $runAt);
        }
    }
}

class FinalizeAnonymousSiebelColumnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use InteractsWithAnonymousSiebelSync;

    public function __construct(public int $uploadId, public string $runTimestamp) {}

    public int $timeout = 600;

    public function handle(): void
    {
        $upload = AnonymousUpload::findOrFail($this->uploadId);
        $runAt = CarbonImmutable::parse($this->runTimestamp);

        try {
            $columns = DB::table(self::COLUMNS_TABLE . ' as c')
                ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
                ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
                ->select('c.id', 'c.table_id', 'c.column_name', 'c.related_columns', 'c.related_columns_raw', 's.schema_name', 't.table_name')
                ->where('c.last_synced_at', $runAt)
                ->get();

            $touchedTableIds = [];
            $stagingColumnKeys = [];
            $columnMeta = [];
            $relationshipsByColumn = [];
            $referencedColumns = [];
            $touchedColumnIds = [];

            foreach ($columns as $column) {
                $columnKey = $this->columnKey($column->table_id, $column->column_name);

                $touchedTableIds[$column->table_id] = true;
                $stagingColumnKeys[$columnKey] = true;
                $touchedColumnIds[$column->id] = true;

                $columnMeta[$columnKey] = [
                    'schema_name' => $column->schema_name,
                    'table_name' => $column->table_name,
                    'column_name' => $column->column_name,
                    'id' => (int) $column->id,
                ];

                $relationships = [];

                if ($column->related_columns) {
                    $decoded = json_decode($column->related_columns, true);
                    if (is_array($decoded)) {
                        $relationships = array_values(array_filter($decoded, fn($item) => is_array($item)));
                    }
                }

                if ($relationships === [] && $column->related_columns_raw) {
                    $parsed = $this->parseRelated($column->related_columns_raw);
                    if ($parsed !== []) {
                        $relationships = $parsed;
                    }
                }

                if ($relationships !== []) {
                    $relationshipsByColumn[$columnKey] = $relationships;

                    foreach ($relationships as $relation) {
                        if (isset($relation['schema'], $relation['table'], $relation['column'])) {
                            $refKey = $this->tripletKey($relation['schema'], $relation['table'], $relation['column']);
                            $referencedColumns[$refKey] ??= [
                                'schema' => $relation['schema'],
                                'table' => $relation['table'],
                                'column' => $relation['column'],
                            ];
                        }
                    }
                }
            }

            $this->softDeleteMissingColumns($touchedTableIds, $stagingColumnKeys, $runAt);
            $this->syncRelationships($columnMeta, $relationshipsByColumn, $referencedColumns, $touchedColumnIds, $runAt);

            $upload->update([
                'status' => 'completed',
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
