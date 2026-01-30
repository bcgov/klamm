<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssembleAnonymizationJobSqlChunks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public int $jobId,
        public string $cacheKey,
        public int $chunkCount,
    ) {
        $this->onQueue('anonymization');
    }

    public function handle(): void
    {
        // Prefix/suffix wrap the per-chunk SQL to form a complete script.
        $prefix = Cache::get($this->cacheKey . ':prefix', '');
        $suffix = Cache::get($this->cacheKey . ':suffix', '');

        if ($prefix === '' && $suffix === '') {
            Log::error('AssembleAnonymizationJobSqlChunks: missing cached prefix/suffix', [
                'job_id' => $this->jobId,
                'cache_key' => $this->cacheKey,
            ]);
            return;
        }

        $parts = [];
        if ($prefix !== '') {
            $parts[] = rtrim($prefix);
        }

        // Append each chunk in order; missing chunks are logged but skipped.
        for ($i = 0; $i < $this->chunkCount; $i++) {
            $chunkKey = $this->cacheKey . ':chunk:' . $i;
            $chunkSql = Cache::get($chunkKey);

            if ($chunkSql === null) {
                Log::error('AssembleAnonymizationJobSqlChunks: missing chunk', [
                    'job_id' => $this->jobId,
                    'chunk_index' => $i,
                    'cache_key' => $this->cacheKey,
                ]);
                continue;
            }

            if (trim($chunkSql) !== '') {
                $parts[] = rtrim($chunkSql);
            }
        }

        if ($suffix !== '') {
            $parts[] = ltrim($suffix);
        }

        $sql = trim(implode(PHP_EOL . PHP_EOL, $parts));

        // If constraints were generated later, strip any placeholder block.
        if (str_contains($sql, '-- BEGIN CONSTRAINTS ONLY')) {
            $sql = preg_replace(
                '/^-- ======================================================================\n-- Constraints Skipped[\s\S]*?-- ======================================================================\n\n?/m',
                '',
                $sql
            ) ?? $sql;
        }

        DB::table('anonymization_jobs')
            ->where('id', $this->jobId)
            ->update([
                'sql_script' => $sql,
                'updated_at' => now(),
            ]);

        Log::info('AssembleAnonymizationJobSqlChunks: completed', [
            'job_id' => $this->jobId,
            'chunk_count' => $this->chunkCount,
            'sql_length' => strlen($sql),
        ]);

        dispatch(new GenerateAnonymizationJobConstraintsSql($this->jobId))
            ->onQueue('anonymization');

        $this->cleanupCache();
    }

    protected function cleanupCache(): void
    {
        Cache::forget($this->cacheKey . ':prefix');
        Cache::forget($this->cacheKey . ':suffix');
        Cache::forget($this->cacheKey . ':rewrite');
        Cache::forget($this->cacheKey . ':seed_map');
        Cache::forget($this->cacheKey . ':seed_providers');
        Cache::forget($this->cacheKey . ':ordered_ids');

        for ($i = 0; $i < $this->chunkCount; $i++) {
            Cache::forget($this->cacheKey . ':chunk:' . $i);
        }
    }
}
