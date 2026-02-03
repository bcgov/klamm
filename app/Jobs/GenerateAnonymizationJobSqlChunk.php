<?php

namespace App\Jobs;

use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

// Processes a chunk of columns for an anonymization job.
// Used by GenerateAnonymizationJobSql when a job has too many columns to process
// Results are cached and assembled by the parent job.
class GenerateAnonymizationJobSqlChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 minutes per chunk

    // @param int $jobId The anonymization job ID
    // @param array $columnIds Column IDs to process in this chunk
    // @param int $chunkIndex The index of this chunk (for ordering)
    // @param string $cacheKey The cache key prefix for storing results
    public function __construct(
        public int $jobId,
        public array $columnIds,
        public int $chunkIndex,
        public string $cacheKey,
    ) {
        $this->onQueue('anonymization');
    }

    // Build SQL for a specific chunk of column IDs.
    // Used by GenerateAnonymizationJobSqlChunk to process large jobs in parallel.
    // @param array $columnIds Array of column IDs to process
    // @param AnonymizationJobs $job The parent anonymization job
    // @param int $chunkIndex Index of this chunk (for logging/ordering)
    // @return string Generated SQL for this chunk
    public function buildForColumnIdsChunk(array $columnIds, AnonymizationJobs $job, int $chunkIndex): string
    {
        if (empty($columnIds)) {
            return '';
        }

        $columns = AnonymousSiebelColumn::with([
            'anonymizationMethods',
            'table.schema.database'
        ])
            ->whereIn('id', $columnIds)
            ->where('must_be_anonymized', true)
            ->get();

        if ($columns->isEmpty()) {
            return '';
        }

        // Group columns by table for organized SQL generation
        $columnsByTable = $columns->groupBy('anonymous_siebel_table_id');

        $sqlParts = [];
        $sqlParts[] = "-- Chunk {$chunkIndex}: Processing " . count($columns) . " columns from " . $columnsByTable->count() . " tables";
        $sqlParts[] = '';

        foreach ($columnsByTable as $tableId => $tableColumns) {
            $firstColumn = $tableColumns->first();
            $table = $firstColumn->table;
            $schema = $table->schema;
            $database = $schema->database;

            $fullTableName = "{$database->name}.{$schema->name}.{$table->name}";

            $sqlParts[] = "-- Table: {$fullTableName}";

            foreach ($tableColumns as $column) {
                if ($column->anonymizationMethods->isNotEmpty()) {
                    $method = $column->anonymizationMethods->first();

                    $sqlParts[] = "-- Column: {$column->name} | Method: {$method->name}";

                    if ($method->sql_block) {
                        // Replace placeholders in the SQL block
                        $sqlBlock = str_replace(
                            ['{table_name}', '{column_name}', '{schema_name}', '{database_name}'],
                            [$table->name, $column->name, $schema->name, $database->name],
                            $method->sql_block
                        );
                        $sqlParts[] = $sqlBlock;
                    }

                    $sqlParts[] = '';
                }
            }

            $sqlParts[] = '';
        }

        return implode("\n", $sqlParts);
    }

    public function handle(AnonymizationJobScriptService $scriptService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $job = AnonymizationJobs::find($this->jobId);
        if (! $job) {
            Log::error('GenerateAnonymizationJobSqlChunk: job not found', [
                'job_id' => $this->jobId,
                'chunk_index' => $this->chunkIndex,
            ]);
            return;
        }

        Log::info('GenerateAnonymizationJobSqlChunk: processing', [
            'job_id' => $this->jobId,
            'chunk_index' => $this->chunkIndex,
            'column_count' => count($this->columnIds),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        try {
            // Pull shared context that was computed once in the parent job.
            $rewriteContext = Cache::get($this->cacheKey . ':rewrite', []);
            $seedMapContext = Cache::get($this->cacheKey . ':seed_map', []);
            $seedProviderMap = Cache::get($this->cacheKey . ':seed_providers', []);
            $orderedIds = Cache::get($this->cacheKey . ':ordered_ids', []);

            if ($orderedIds === [] || $seedProviderMap === [] || $rewriteContext === []) {
                Log::error('GenerateAnonymizationJobSqlChunk: missing cached context', [
                    'job_id' => $this->jobId,
                    'chunk_index' => $this->chunkIndex,
                    'cache_key' => $this->cacheKey,
                ]);
                return;
            }

            // Generate SQL for this chunk using cached context.
            $chunkSql = $scriptService->buildMaskingChunk(
                $this->columnIds,
                $seedProviderMap,
                $rewriteContext,
                $seedMapContext,
                $orderedIds,
                $job
            );

            // Cache the result for assembly
            $chunkCacheKey = $this->cacheKey . ':chunk:' . $this->chunkIndex;
            Cache::put($chunkCacheKey, $chunkSql, now()->addHours(4));

            Log::info('GenerateAnonymizationJobSqlChunk: completed', [
                'job_id' => $this->jobId,
                'chunk_index' => $this->chunkIndex,
                'sql_length' => strlen($chunkSql),
                'cache_key' => $chunkCacheKey,
            ]);
        } catch (Throwable $e) {
            Log::error('GenerateAnonymizationJobSqlChunk: failed', [
                'job_id' => $this->jobId,
                'chunk_index' => $this->chunkIndex,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateAnonymizationJobSqlChunk: job failed', [
            'job_id' => $this->jobId,
            'chunk_index' => $this->chunkIndex,
            'error' => $exception?->getMessage(),
        ]);
    }
}
