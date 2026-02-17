<?php

namespace App\Jobs;

use App\Models\Anonymizer\AnonymizationJobs;
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
    // @param array $tableIds Table IDs to process in this chunk
    // @param int $chunkIndex The index of this chunk (for ordering)
    // @param string $cacheKey The cache key prefix for storing results
    public function __construct(
        public int $jobId,
        public array $tableIds,
        public int $chunkIndex,
        public string $cacheKey,
    ) {
        $this->onQueue('anonymization');
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
            'table_count' => count($this->tableIds),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        try {
            // Pull shared context that was computed once in the parent job.
            $rewriteContext = Cache::get($this->cacheKey . ':rewrite', []);
            $orderedTableIds = Cache::get($this->cacheKey . ':ordered_table_ids', []);
            $seedProviderMap = Cache::get($this->cacheKey . ':seed_providers', []);
            $seedMapContext = Cache::get($this->cacheKey . ':seed_map', []);
            $selectedColumnIds = Cache::get($this->cacheKey . ':selected_column_ids', []);

            if ($orderedTableIds === [] || $rewriteContext === []) {
                Log::error('GenerateAnonymizationJobSqlChunk: missing cached context', [
                    'job_id' => $this->jobId,
                    'chunk_index' => $this->chunkIndex,
                    'cache_key' => $this->cacheKey,
                ]);
                return;
            }

            // Generate SQL for this chunk using cached context.
            $chunkSql = $scriptService->buildInlineTableChunk(
                $this->tableIds,
                $rewriteContext,
                $seedProviderMap,
                $seedMapContext,
                $selectedColumnIds,
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
