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
use Illuminate\Support\Facades\DB;

class GenerateAnonymizationJobSql implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * OOMs are fatal and will just churn retries; fail fast.
     */
    public int $tries = 1;

    public int $timeout = 120;

    private const WHERE_IN_CHUNK_SIZE = 10000;

    public function __construct(public int $jobId) {}

    public function handle(AnonymizationJobScriptService $scriptService): void
    {
        /** @var AnonymizationJobs|null $job */
        $job = AnonymizationJobs::query()->find($this->jobId);

        if (! $job) {
            throw new ModelNotFoundException("AnonymizationJobs {$this->jobId} not found.");
        }

        // Include:
        // - methods with non-empty sql_block (actual work)
        // - OR seed-relevant methods (emits/requires), even if sql_block is blank (seed-contract evaluation)
        //
        // Column selection behavior:
        // - If explicit job columns exist, honor them.
        // - Otherwise, expand the job scope (databases/schemas/tables) into catalog columns.

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

        $this->normalizeSeedContractModesForColumns($maskingColumnIds);
        $this->ensureSeedContractModesForColumns($maskingColumnIds);

        if ($hasExplicitColumns) {
            $sql = $scriptService->buildForJob($job);
        } elseif ($maskingColumnIds === [] && $job->job_type === AnonymizationJobs::TYPE_FULL) {
            $sql = $scriptService->buildCloneOnlyForJob($job);
        } else {
            $sql = $maskingColumnIds !== []
                ? $scriptService->buildForColumnIds($maskingColumnIds, $job)
                : '-- No SQL generated: this job has no explicit columns and no in-scope columns are linked to anonymization methods.';
        }

        DB::table('anonymization_jobs')
            ->where('id', $this->jobId)
            ->update([
                'sql_script' => $sql,
                'updated_at' => now(),
            ]);
    }

    /**
     * Resolve catalog columns based on job scope (databases/schemas/tables).
     * Returns a query selecting a single `id` column.
     */
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

        // No scope and no explicit columns.
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
