<?php

namespace App\Jobs;

use App\Enums\SeedContractMode;
use App\Models\AnonymizationJobs;
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
        $columnIds = DB::table('anonymization_job_columns as ajc')
            ->join('anonymization_method_column as amc', 'amc.column_id', '=', 'ajc.column_id')
            ->join('anonymization_methods as m', 'm.id', '=', 'amc.method_id')
            ->where('ajc.job_id', $this->jobId)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('m.sql_block')->where('m.sql_block', '<>', '');
                })->orWhere('m.emits_seed', true)
                    ->orWhere('m.requires_seed', true);
            })
            ->distinct()
            ->orderBy('ajc.column_id')
            ->pluck('ajc.column_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $this->normalizeSeedContractModesForColumns($columnIds);
        $this->ensureSeedContractModesForColumns($columnIds);

        $sql = $columnIds !== []
            ? $scriptService->buildForColumnIds($columnIds, $job)
            : '-- No anonymization SQL generated: no columns are configured for this job.';

        DB::table('anonymization_jobs')
            ->where('id', $this->jobId)
            ->update([
                'sql_script' => $sql,
                'updated_at' => now(),
            ]);
    }

    protected function normalizeSeedContractModesForColumns(array $columnIds): void
    {
        $columnIds = array_values(array_filter(array_map('intval', $columnIds)));
        if ($columnIds === []) {
            return;
        }

        $allowed = array_map(static fn(SeedContractMode $case) => $case->value, SeedContractMode::cases());

        DB::table('anonymous_siebel_columns')
            ->whereIn('id', $columnIds)
            ->whereNotNull('seed_contract_mode')
            ->whereNotIn('seed_contract_mode', $allowed)
            ->update(['seed_contract_mode' => null]);
    }

    protected function ensureSeedContractModesForColumns(array $columnIds): void
    {
        $columnIds = array_values(array_filter(array_map('intval', $columnIds)));
        if ($columnIds === []) {
            return;
        }

        $rows = DB::table('anonymization_method_column as amc')
            ->join('anonymization_methods as m', 'm.id', '=', 'amc.method_id')
            ->whereIn('amc.column_id', $columnIds)
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

            DB::table('anonymous_siebel_columns')
                ->where('id', (int) $row->column_id)
                ->whereNull('seed_contract_mode')
                ->update(['seed_contract_mode' => $enumValue]);
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
