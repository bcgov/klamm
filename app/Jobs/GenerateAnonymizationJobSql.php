<?php

namespace App\Jobs;

use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GenerateAnonymizationJobSql implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // OOMs are fatal and will just churn retries; fail fast.
    public int $tries = 1;

    // Allow up to 60 minutes for very large schema jobs.
    // Schema-wide jobs can include thousands of columns.
    public int $timeout = 3600;

    private const WHERE_IN_CHUNK_SIZE = 10000;

    // Column count threshold for logging progress on large jobs.
    private const LARGE_JOB_THRESHOLD = 1000;

    // Column count threshold for using streamed/chunked processing.
    // Above this, we process tables incrementally to avoid memory issues.
    private const CHUNKED_PROCESSING_THRESHOLD = 2000;

    // Chunk size for batched masking generation.
    private const MASKING_CHUNK_SIZE = 500;

    private const REGENERATION_CACHE_PREFIX = 'anonymization:job-sql:regenerating:';

    public function __construct(public int $jobId)
    {
        // Use dedicated queue for long-running anonymization work
        $this->onQueue('anonymization');
    }

    public static function regenerationCacheKey(int $jobId): string
    {
        return self::REGENERATION_CACHE_PREFIX . $jobId;
    }

    public function handle(AnonymizationJobScriptService $scriptService): void
    {
        // @var AnonymizationJobs|null $job
        $job = AnonymizationJobs::query()->find($this->jobId);

        if (! $job) {
            throw new ModelNotFoundException("AnonymizationJobs {$this->jobId} not found.");
        }

        Log::info('GenerateAnonymizationJobSql: starting', [
            'job_id' => $this->jobId,
            'job_name' => $job->name,
            'memory_limit' => ini_get('memory_limit'),
        ]);

        // Detect whether this job explicitly selects columns (vs. scoped selection).
        $hasExplicitColumns = DB::table('anonymization_job_columns')
            ->where('job_id', $this->jobId)
            ->exists();

        $maskingColumnIds = $hasExplicitColumns
            ? DB::table('anonymization_job_columns')
            ->where('job_id', $this->jobId)
            ->distinct()
            ->orderBy('column_id')
            ->pluck('column_id')
            ->map(fn($id) => (int) $id)
            ->all()
            : DB::query()
            ->fromSub($this->scopedCatalogColumnsQuery($this->jobId), 'sc')
            ->join('anonymization_method_column as amc', 'amc.column_id', '=', 'sc.id')
            ->distinct()
            ->orderBy('sc.id')
            ->pluck('sc.id')
            ->map(fn($id) => (int) $id)
            ->all();

        $columnCount = count($maskingColumnIds);

        Log::info('GenerateAnonymizationJobSql: resolved columns', [
            'job_id' => $this->jobId,
            'column_count' => $columnCount,
            'has_explicit_columns' => $hasExplicitColumns,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        if ($columnCount >= self::LARGE_JOB_THRESHOLD) {
            Log::info('GenerateAnonymizationJobSql: large job detected', [
                'job_id' => $this->jobId,
                'column_count' => $columnCount,
                'processing_mode' => $columnCount >= self::CHUNKED_PROCESSING_THRESHOLD ? 'optimized' : 'standard',
            ]);
        }

        // Normalize seed contract flags before building SQL to keep seed maps consistent.
        $this->normalizeSeedContractModesForColumns($maskingColumnIds);
        $this->ensureSeedContractModesForColumns($maskingColumnIds);

        // Choose processing strategy based on column count to avoid memory spikes.
        if ($maskingColumnIds !== [] && $columnCount >= self::CHUNKED_PROCESSING_THRESHOLD) {
            Log::info('GenerateAnonymizationJobSql: preparing batched generation for large column set', [
                'job_id' => $this->jobId,
                'column_count' => $columnCount,
            ]);

            $context = $scriptService->prepareChunkedContextForColumnIds($maskingColumnIds, $job);

            if (! empty($context['halted'])) {
                $sql = (string) ($context['prefix_sql'] ?? '');
                DB::table('anonymization_jobs')
                    ->where('id', $this->jobId)
                    ->update([
                        'sql_script' => $sql,
                        'updated_at' => now(),
                    ]);

                $this->clearRegenerationCache();

                Log::warning('GenerateAnonymizationJobSql: halted due to contract review errors', [
                    'job_id' => $this->jobId,
                ]);

                return;
            }

            $orderedIds = $context['ordered_ids'] ?? [];
            if ($orderedIds === []) {
                $sql = '-- No SQL generated: no ordered columns available.';
                DB::table('anonymization_jobs')
                    ->where('id', $this->jobId)
                    ->update([
                        'sql_script' => $sql,
                        'updated_at' => now(),
                    ]);

                $this->clearRegenerationCache();
                return;
            }

            // Cache shared context for chunk workers to avoid duplicating heavy lookups.
            $cacheKey = 'anonymization:job-sql:' . $this->jobId . ':' . Str::uuid();
            Cache::put($cacheKey . ':prefix', $context['prefix_sql'] ?? '', now()->addHours(4));
            Cache::put($cacheKey . ':suffix', $context['suffix_sql'] ?? '', now()->addHours(4));
            Cache::put($cacheKey . ':rewrite', $context['rewrite_context'] ?? [], now()->addHours(4));
            Cache::put($cacheKey . ':seed_map', $context['seed_map_context'] ?? [], now()->addHours(4));
            Cache::put($cacheKey . ':seed_providers', $context['seed_provider_map'] ?? [], now()->addHours(4));
            Cache::put($cacheKey . ':ordered_ids', $orderedIds, now()->addHours(4));

            // Split ordered IDs into work chunks and dispatch a batch.
            $chunks = array_chunk($orderedIds, self::MASKING_CHUNK_SIZE);
            $chunkCount = count($chunks);
            $jobs = [];

            if ($chunkCount === 0) {
                Log::warning('GenerateAnonymizationJobSql: no chunks created for batch', [
                    'job_id' => $this->jobId,
                ]);
                return;
            }

            foreach ($chunks as $index => $chunkIds) {
                $jobs[] = new GenerateAnonymizationJobSqlChunk(
                    $this->jobId,
                    $chunkIds,
                    $index,
                    $cacheKey
                );
            }

            $jobId = $this->jobId;

            Bus::batch($jobs)
                ->onQueue('anonymization')
                ->then(function () use ($cacheKey, $jobId, $chunkCount) {
                    dispatch(new AssembleAnonymizationJobSqlChunks(
                        $jobId,
                        $cacheKey,
                        $chunkCount
                    ))->onQueue('anonymization');
                })
                ->dispatch();

            Log::info('GenerateAnonymizationJobSql: dispatched chunk batch', [
                'job_id' => $this->jobId,
                'chunk_count' => $chunkCount,
                'cache_key' => $cacheKey,
            ]);

            return;
        }

        if ($hasExplicitColumns) {
            $sql = $scriptService->buildForJob($job);
        } elseif ($maskingColumnIds === [] && $job->job_type === AnonymizationJobs::TYPE_FULL) {
            // Log scope info for debugging clone-only jobs
            $databaseCount = DB::table('anonymization_job_databases')->where('job_id', $this->jobId)->count();
            $schemaCount = DB::table('anonymization_job_schemas')->where('job_id', $this->jobId)->count();
            $tableCount = DB::table('anonymization_job_tables')->where('job_id', $this->jobId)->count();

            Log::info('GenerateAnonymizationJobSql: building clone-only for FULL job', [
                'job_id' => $this->jobId,
                'job_name' => $job->name,
                'job_type' => $job->job_type,
                'scope_databases' => $databaseCount,
                'scope_schemas' => $schemaCount,
                'scope_tables' => $tableCount,
            ]);

            $sql = $scriptService->buildCloneOnlyForJob($job);
        } elseif ($columnCount >= self::CHUNKED_PROCESSING_THRESHOLD) {
            // Use optimized processing for very large jobs
            Log::info('GenerateAnonymizationJobSql: using optimized processing for large column set', [
                'job_id' => $this->jobId,
                'column_count' => $columnCount,
            ]);
            $sql = $scriptService->buildForColumnIdsOptimized($maskingColumnIds, $job);
        } else {
            $sql = $maskingColumnIds !== []
                ? $scriptService->buildForColumnIds($maskingColumnIds, $job)
                : '-- No SQL generated: this job has no explicit columns and no in-scope columns are linked to anonymization methods.';
        }

        Log::info('GenerateAnonymizationJobSql: script generated', [
            'job_id' => $this->jobId,
            'script_length' => strlen($sql),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        DB::table('anonymization_jobs')
            ->where('id', $this->jobId)
            ->update([
                'sql_script' => $sql,
                'updated_at' => now(),
            ]);

        $this->clearRegenerationCache();

        Log::info('GenerateAnonymizationJobSql: completed', [
            'job_id' => $this->jobId,
            'job_name' => $job->name,
        ]);
    }

    // Handle job failure.
    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateAnonymizationJobSql: failed', [
            'job_id' => $this->jobId,
            'error' => $exception?->getMessage(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        DB::table('anonymization_jobs')
            ->where('id', $this->jobId)
            ->update([
                'sql_script' => '-- SQL generation failed: ' . ($exception?->getMessage() ?? 'Unknown error'),
                'updated_at' => now(),
            ]);

        $this->clearRegenerationCache();
    }

    protected function clearRegenerationCache(): void
    {
        Cache::forget(self::regenerationCacheKey($this->jobId));
    }

    // Resolve catalog columns based on job scope (databases/schemas/tables).
    protected function scopedCatalogColumnsQuery(int $jobId)
    {
        $databaseIds = DB::table('anonymization_job_databases')
            ->where('job_id', $jobId)
            ->pluck('database_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $schemaIds = DB::table('anonymization_job_schemas')
            ->where('job_id', $jobId)
            ->pluck('schema_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $tableIds = DB::table('anonymization_job_tables')
            ->where('job_id', $jobId)
            ->pluck('table_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $query = DB::table('anonymous_siebel_columns as c')
            ->join('anonymous_siebel_tables as t', 't.id', '=', 'c.table_id')
            ->join('anonymous_siebel_schemas as s', 's.id', '=', 't.schema_id')
            ->join('anonymous_siebel_databases as d', 'd.id', '=', 's.database_id')
            ->select('c.id');

        if ($tableIds !== []) {
            return $query->whereIn('t.id', $tableIds);
        }

        if ($schemaIds !== []) {
            return $query->whereIn('s.id', $schemaIds);
        }

        if ($databaseIds !== []) {
            return $query->whereIn('d.id', $databaseIds);
        }

        return $query->whereRaw('1=0');
    }

    protected function normalizeSeedContractModesForColumns(array $columnIds): void
    {
        $columnIds = array_values(array_filter(array_map('intval', $columnIds)));
        if ($columnIds === []) {
            return;
        }

        $allowed = array_map(static fn(SeedContractMode $case) => $case->value, SeedContractMode::cases());

        foreach (array_chunk($columnIds, self::WHERE_IN_CHUNK_SIZE) as $chunk) {
            DB::table('anonymous_siebel_columns')
                ->whereIn('id', $chunk)
                ->whereNotNull('seed_contract_mode')
                ->whereNotIn('seed_contract_mode', $allowed)
                ->update(['seed_contract_mode' => null]);
        }
    }

    protected function ensureSeedContractModesForColumns(array $columnIds): void
    {
        $columnIds = array_values(array_filter(array_map('intval', $columnIds)));
        if ($columnIds === []) {
            return;
        }

        $idsByEnumValue = [];

        foreach (array_chunk($columnIds, self::WHERE_IN_CHUNK_SIZE) as $chunk) {
            $rows = DB::table('anonymization_method_column as amc')
                ->join('anonymization_methods as m', 'm.id', '=', 'amc.method_id')
                ->whereIn('amc.column_id', $chunk)
                ->groupBy('amc.column_id')
                ->select([
                    'amc.column_id',
                    DB::raw('MAX(CASE WHEN m.emits_seed = true THEN 1 ELSE 0 END) as emits_seed'),
                    DB::raw('MAX(CASE WHEN m.requires_seed = true THEN 1 ELSE 0 END) as requires_seed'),
                ])
                ->get();

            foreach ($rows as $row) {
                $emits = (int) ($row->emits_seed ?? 0) === 1;
                $requires = (int) ($row->requires_seed ?? 0) === 1;

                $semantic = match (true) {
                    $emits && $requires => 'composite',
                    $emits => 'seed_source',
                    $requires => 'consumer',
                    default => null,
                };

                $enumValue = $this->seedContractEnumBackingValue($semantic);
                if ($enumValue === null) {
                    continue;
                }

                $idsByEnumValue[$enumValue] ??= [];
                $idsByEnumValue[$enumValue][] = (int) $row->column_id;
            }
        }

        foreach ($idsByEnumValue as $enumValue => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            foreach (array_chunk($ids, self::WHERE_IN_CHUNK_SIZE) as $chunk) {
                DB::table('anonymous_siebel_columns')
                    ->whereIn('id', $chunk)
                    ->whereNull('seed_contract_mode')
                    ->update(['seed_contract_mode' => $enumValue]);
            }
        }
    }

    protected function seedContractEnumBackingValue(?string $semantic): ?string
    {
        $semantic = $semantic !== null ? trim($semantic) : null;

        if ($semantic === null || $semantic === '') {
            return null;
        }

        $target = strtolower(preg_replace('/[^a-z]/', '', $semantic));
        $cases = SeedContractMode::cases();

        foreach ($cases as $case) {
            $nameNorm = strtolower(preg_replace('/[^a-z]/', '', $case->name));
            if ($nameNorm === $target) {
                return $case->value;
            }
        }

        foreach ($cases as $case) {
            $valueNorm = strtolower(preg_replace('/[^a-z]/', '', (string) $case->value));
            if ($valueNorm === $target) {
                return $case->value;
            }
        }

        return null;
    }
}
