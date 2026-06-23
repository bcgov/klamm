<?php

namespace App\Services\Anonymizer;

use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Services\Anonymizer\Concerns\ResolvesAnonymizationColumnDependencies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AnonymizationJobDependencyClosureService
{
    use ResolvesAnonymizationColumnDependencies;

    public const MODE_BASELINE_DECLARED = 'baseline_declared';

    public const MODE_SELF_CONTAINED = 'self_contained';

    public const MODE_NOT_APPLICABLE = 'not_applicable';

    private const MAX_PARENT_COLUMNS_PER_RUN = 500;

    private const CHUNKED_CONTRACT_REVIEW_LIMIT = 100;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function applyForJob(AnonymizationJobs $job, array $overrides = []): array
    {
        if ($overrides !== []) {
            $job->forceFill($overrides);
        }

        if (! $this->dependencyColumnsExist()) {
            Log::warning('AnonymizationJobDependencyClosureService: dependency columns missing; run migrations before using partial dependency closure.', [
                'job_id' => $job->getKey(),
            ]);

            return [];
        }

        if ($job->job_type !== AnonymizationJobs::TYPE_PARTIAL) {
            return $this->persistMetadata($job, [
                'mode' => self::MODE_NOT_APPLICABLE,
                'baseline_declared' => false,
                'added_parent_column_ids' => [],
                'added_parent_table_ids' => [],
                'unresolved' => [],
                'child_candidates' => [],
            ]);
        }

        if ((bool) $job->partial_uses_existing_full_anonymization) {
            return $this->persistMetadata($job, [
                'mode' => self::MODE_BASELINE_DECLARED,
                'baseline_declared' => true,
                'baseline_reference' => trim((string) ($job->partial_baseline_reference ?? '')),
                'added_parent_column_ids' => [],
                'added_parent_table_ids' => [],
                'unresolved' => [],
                'child_candidates' => [],
            ]);
        }

        $report = $this->previewForJob($job);
        $this->persistParentClosure($job, $report);

        return $this->persistMetadata($job, $report);
    }

    public function syncJobDependencyAttributesFromDatabase(AnonymizationJobs $job): void
    {
        if (! $this->dependencyColumnsExist()) {
            return;
        }

        $attrs = DB::table('anonymization_jobs')
            ->where('id', $job->getKey())
            ->select([
                'job_type',
                'partial_uses_existing_full_anonymization',
                'partial_baseline_reference',
                'dependency_resolution_mode',
                'dependency_resolution_metadata',
            ])
            ->first();

        if (! $attrs) {
            return;
        }

        $metadata = $attrs->dependency_resolution_metadata;
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $job->forceFill([
            'job_type' => $attrs->job_type,
            'partial_uses_existing_full_anonymization' => (bool) $attrs->partial_uses_existing_full_anonymization,
            'partial_baseline_reference' => $attrs->partial_baseline_reference,
            'dependency_resolution_mode' => $attrs->dependency_resolution_mode,
            'dependency_resolution_metadata' => is_array($metadata) ? $metadata : [],
        ]);
    }

    /**
     * Lightweight contract review for chunked SQL generation.
     *
     * @param  array<int, int>  $columnIds
     * @return array{lines: array<int, string>, halted: bool}
     */
    public function buildChunkedContractReview(array $columnIds, AnonymizationJobs $job, array $rewriteContext): array
    {
        $mode = trim((string) ($rewriteContext['dependency_resolution_mode'] ?? $job->dependency_resolution_mode ?? ''));
        if ($job->job_type !== AnonymizationJobs::TYPE_PARTIAL || $mode === '' || $mode === self::MODE_NOT_APPLICABLE) {
            return ['lines' => [], 'halted' => false];
        }

        $columnIds = array_values(array_filter(array_map('intval', $columnIds), fn(int $id) => $id > 0));
        $selectedIds = array_fill_keys($columnIds, true);
        $errors = [];
        $warnings = [];

        foreach (array_chunk($columnIds, 500) as $chunk) {
            $rows = DB::table('anonymous_siebel_column_dependencies as dep')
                ->join('anonymous_siebel_columns as child', 'child.id', '=', 'dep.child_field_id')
                ->join('anonymous_siebel_tables as child_tables', 'child_tables.id', '=', 'child.table_id')
                ->join('anonymous_siebel_schemas as child_schemas', 'child_schemas.id', '=', 'child_tables.schema_id')
                ->join('anonymous_siebel_databases as child_databases', 'child_databases.id', '=', 'child_schemas.database_id')
                ->join('anonymous_siebel_columns as parent', 'parent.id', '=', 'dep.parent_field_id')
                ->join('anonymous_siebel_tables as parent_tables', 'parent_tables.id', '=', 'parent.table_id')
                ->join('anonymous_siebel_schemas as parent_schemas', 'parent_schemas.id', '=', 'parent_tables.schema_id')
                ->join('anonymous_siebel_databases as parent_databases', 'parent_databases.id', '=', 'parent_schemas.database_id')
                ->whereIn('dep.child_field_id', $chunk)
                ->where('dep.is_seed_mandatory', true)
                ->select([
                    'child.column_name as child_column_name',
                    'parent.id as parent_id',
                    'parent.column_name as parent_column_name',
                    'child_databases.database_name as child_database_name',
                    'child_schemas.schema_name as child_schema_name',
                    'child_tables.table_name as child_table_name',
                    'parent_databases.database_name as parent_database_name',
                    'parent_schemas.schema_name as parent_schema_name',
                    'parent_tables.table_name as parent_table_name',
                ])
                ->get();

            foreach ($rows as $row) {
                $parentId = (int) ($row->parent_id ?? 0);
                if ($parentId <= 0 || isset($selectedIds[$parentId])) {
                    continue;
                }

                $childLabel = implode('.', array_filter([
                    (string) ($row->child_database_name ?? ''),
                    (string) ($row->child_schema_name ?? ''),
                    (string) ($row->child_table_name ?? ''),
                    (string) ($row->child_column_name ?? ''),
                ]));
                $parentLabel = implode('.', array_filter([
                    (string) ($row->parent_database_name ?? ''),
                    (string) ($row->parent_schema_name ?? ''),
                    (string) ($row->parent_table_name ?? ''),
                    (string) ($row->parent_column_name ?? ''),
                ]));

                $message = $childLabel . ': Requires parent ' . $parentLabel . ' but it is not included in this job.';
                if ($mode === self::MODE_SELF_CONTAINED) {
                    $errors[] = $message . ' Self-contained partial jobs must include parent seed providers, or explicitly mark the job as baseline-backed.';
                } else {
                    $warnings[] = $message . ' The job is marked as baseline-backed, so the generated SQL assumes the existing full anonymized dataset already contains the matching parent remap.';
                }
            }
        }

        $errors = array_values(array_unique($errors));
        $warnings = array_values(array_unique($warnings));

        if ($mode === self::MODE_SELF_CONTAINED) {
            $metadata = is_array($rewriteContext['dependency_resolution_metadata'] ?? null)
                ? $rewriteContext['dependency_resolution_metadata']
                : (is_array($job->dependency_resolution_metadata) ? $job->dependency_resolution_metadata : []);

            foreach (array_slice((array) ($metadata['unresolved'] ?? []), 0, self::CHUNKED_CONTRACT_REVIEW_LIMIT) as $unresolved) {
                if (! is_array($unresolved)) {
                    continue;
                }

                $errors[] = (string) ($unresolved['child'] ?? 'unknown')
                    . ': Required FK parent could not be resolved ('
                    . (string) ($unresolved['parent'] ?? 'unknown') . ').';
            }
        }

        $errors = array_values(array_unique($errors));
        $lines = $this->renderContractReviewCommentLines($errors, $warnings);

        return [
            'lines' => $lines,
            'halted' => $mode === self::MODE_SELF_CONTAINED && $errors !== [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewForJob(AnonymizationJobs $job): array
    {
        $selectedColumnIds = $this->selectedColumnIds((int) $job->getKey());

        if ($job->job_type !== AnonymizationJobs::TYPE_PARTIAL) {
            return [
                'mode' => self::MODE_NOT_APPLICABLE,
                'baseline_declared' => false,
                'selected_column_count' => count($selectedColumnIds),
                'added_parent_column_ids' => [],
                'added_parent_table_ids' => [],
                'unresolved' => [],
                'child_candidates' => [],
            ];
        }

        if ((bool) $job->partial_uses_existing_full_anonymization) {
            return [
                'mode' => self::MODE_BASELINE_DECLARED,
                'baseline_declared' => true,
                'baseline_reference' => trim((string) ($job->partial_baseline_reference ?? '')),
                'selected_column_count' => count($selectedColumnIds),
                'added_parent_column_ids' => [],
                'added_parent_table_ids' => [],
                'unresolved' => [],
                'child_candidates' => [],
            ];
        }

        if ($selectedColumnIds === []) {
            return [
                'mode' => self::MODE_SELF_CONTAINED,
                'baseline_declared' => false,
                'selected_column_count' => 0,
                'added_parent_column_ids' => [],
                'added_parent_table_ids' => [],
                'unresolved' => [],
                'child_candidates' => [],
            ];
        }

        $selectedColumns = AnonymousSiebelColumn::query()
            ->with([
                'table.schema.database',
                'parentColumns.table.schema.database',
            ])
            ->whereIn('id', $selectedColumnIds)
            ->get();

        $selectedIds = array_fill_keys($selectedColumnIds, true);
        $selectedTableIds = $selectedColumns
            ->pluck('table_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn(int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $parentRequirements = [];
        $unresolved = [];

        foreach ($selectedColumns as $column) {
            foreach ($this->requiredParentProvidersForColumn($column, $selectedIds) as $parentId => $requirement) {
                $parentRequirements[$parentId] = $requirement;
            }

            foreach ($this->resolveForeignKeyRelationships($column) as $relationship) {
                if (strtoupper((string) ($relationship['direction'] ?? 'OUTBOUND')) !== 'OUTBOUND') {
                    continue;
                }

                if (strtoupper(trim((string) ($relationship['column'] ?? 'ROW_ID'))) !== 'ROW_ID') {
                    continue;
                }

                if ($this->findParentRowIdColumn($relationship) instanceof AnonymousSiebelColumn) {
                    continue;
                }

                $unresolved[] = [
                    'type' => 'missing_parent_row_id',
                    'child_column_id' => (int) $column->getKey(),
                    'child' => $this->qualifiedDependencyColumnName($column),
                    'parent' => $this->relationshipLabel($relationship),
                ];
            }
        }

        if (count($parentRequirements) > self::MAX_PARENT_COLUMNS_PER_RUN) {
            $unresolved[] = [
                'type' => 'parent_closure_limit',
                'child' => 'job',
                'parent' => 'dependency closure',
                'details' => 'Parent closure found ' . number_format(count($parentRequirements)) . ' parent ROW_ID columns, above the safety limit of ' . number_format(self::MAX_PARENT_COLUMNS_PER_RUN) . '.',
            ];

            $parentRequirements = array_slice($parentRequirements, 0, self::MAX_PARENT_COLUMNS_PER_RUN, true);
        }

        $addedParentColumnIds = array_map('intval', array_keys($parentRequirements));
        $addedParentTableIds = collect($parentRequirements)
            ->pluck('table_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn(int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return [
            'mode' => self::MODE_SELF_CONTAINED,
            'baseline_declared' => false,
            'selected_column_count' => count($selectedColumnIds),
            'added_parent_column_ids' => $addedParentColumnIds,
            'added_parent_table_ids' => $addedParentTableIds,
            'added_parent_columns' => array_values($parentRequirements),
            'unresolved' => array_values($unresolved),
            'child_candidates' => $this->childCandidatesForTables($selectedTableIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function persistParentClosure(AnonymizationJobs $job, array $report): void
    {
        $jobId = (int) $job->getKey();
        $columnIds = array_values(array_filter(array_map('intval', $report['added_parent_column_ids'] ?? [])));
        $tableIds = array_values(array_filter(array_map('intval', $report['added_parent_table_ids'] ?? [])));

        if ($columnIds !== []) {
            $this->ensureParentRowIdSeedContracts($columnIds);
            $methodMap = $this->methodIdsForColumns($columnIds, (string) ($job->strategy ?? ''));
            $timestamp = now()->toDateTimeString();

            foreach ($columnIds as $columnId) {
                $exists = DB::table('anonymization_job_columns')
                    ->where('job_id', $jobId)
                    ->where('column_id', $columnId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('anonymization_job_columns')->insert([
                    'job_id' => $jobId,
                    'column_id' => $columnId,
                    'anonymization_method_id' => $methodMap[$columnId] ?? null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }

        if ($tableIds !== []) {
            $timestamp = now()->toDateTimeString();

            foreach ($tableIds as $tableId) {
                $exists = DB::table('anonymization_job_tables')
                    ->where('job_id', $jobId)
                    ->where('table_id', $tableId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('anonymization_job_tables')->insert([
                    'job_id' => $jobId,
                    'table_id' => $tableId,
                    'row_multiplier' => 1,
                    'volume_mode' => 'multiplier',
                    'target_row_count' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function persistMetadata(AnonymizationJobs $job, array $metadata): array
    {
        $mode = (string) ($metadata['mode'] ?? self::MODE_NOT_APPLICABLE);

        DB::table('anonymization_jobs')
            ->where('id', $job->getKey())
            ->update([
                'dependency_resolution_mode' => $mode,
                'dependency_resolution_metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);

        $job->forceFill([
            'dependency_resolution_mode' => $mode,
            'dependency_resolution_metadata' => $metadata,
        ]);

        return $metadata;
    }

    private function dependencyColumnsExist(): bool
    {
        return Schema::hasColumn('anonymization_jobs', 'dependency_resolution_mode')
            && Schema::hasColumn('anonymization_jobs', 'partial_uses_existing_full_anonymization');
    }

    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     * @return array<int, string>
     */
    private function renderContractReviewCommentLines(array $errors, array $warnings): array
    {
        if ($errors === [] && $warnings === []) {
            return [];
        }

        $lines = [
            str_repeat('=', 70),
            '-- Seed Contract Review',
            str_repeat('-', 70),
        ];

        if ($errors !== []) {
            $lines[] = '-- Blocking issues:';
            foreach (array_slice($errors, 0, self::CHUNKED_CONTRACT_REVIEW_LIMIT) as $error) {
                $lines[] = '--   * ' . $error;
            }
        }

        if ($warnings !== []) {
            if ($errors !== []) {
                $lines[] = '--';
            }

            $lines[] = '-- Warnings:';
            foreach (array_slice($warnings, 0, self::CHUNKED_CONTRACT_REVIEW_LIMIT) as $warning) {
                $lines[] = '--   * ' . $warning;
            }
        }

        $lines[] = str_repeat('=', 70);
        $lines[] = '';

        return $lines;
    }

    /**
     * @return array<int, int>
     */
    private function selectedColumnIds(int $jobId): array
    {
        if ($jobId <= 0) {
            return [];
        }

        return DB::table('anonymization_job_columns')
            ->where('job_id', $jobId)
            ->pluck('column_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn(int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $columnIds
     */
    private function ensureParentRowIdSeedContracts(array $columnIds): void
    {
        if ($columnIds === []) {
            return;
        }

        foreach (array_chunk($columnIds, 500) as $chunk) {
            DB::table('anonymous_siebel_columns')
                ->whereIn('id', $chunk)
                ->whereRaw('UPPER(column_name) = ?', ['ROW_ID'])
                ->update([
                    'seed_contract_mode' => SeedContractMode::SOURCE->value,
                    'seed_contract_expression' => DB::raw("COALESCE(seed_contract_expression, 'tgt.ROW_ID')"),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<int, int>  $columnIds
     * @return array<int, int|null>
     */
    private function methodIdsForColumns(array $columnIds, string $strategy = ''): array
    {
        if ($columnIds === []) {
            return [];
        }

        $direct = DB::table('anonymization_method_column')
            ->whereIn('column_id', $columnIds)
            ->selectRaw('column_id, MIN(method_id) as method_id')
            ->groupBy('column_id')
            ->pluck('method_id', 'column_id')
            ->map(fn($id) => $id !== null ? (int) $id : null)
            ->all();

        $ruleQuery = DB::table('anonymization_rule_column as arc')
            ->join('anonymization_rule_methods as arm', 'arm.rule_id', '=', 'arc.rule_id')
            ->whereIn('arc.column_id', $columnIds)
            ->groupBy('arc.column_id');

        if (trim($strategy) !== '') {
            $ruleQuery->selectRaw(
                'arc.column_id, COALESCE(MAX(CASE WHEN arm.strategy = ? THEN arm.method_id ELSE NULL END), MAX(CASE WHEN arm.is_default = true THEN arm.method_id ELSE NULL END)) as method_id',
                [$strategy]
            );
        } else {
            $ruleQuery->selectRaw('arc.column_id, MAX(CASE WHEN arm.is_default = true THEN arm.method_id ELSE NULL END) as method_id');
        }

        $rule = $ruleQuery
            ->pluck('method_id', 'column_id')
            ->map(fn($id) => $id !== null ? (int) $id : null)
            ->all();

        $result = [];
        foreach ($columnIds as $columnId) {
            $result[(int) $columnId] = $rule[$columnId] ?? $direct[$columnId] ?? null;
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $selectedTableIds
     * @return array<int, array<string, mixed>>
     */
    private function childCandidatesForTables(array $selectedTableIds): array
    {
        $selectedTableIds = array_values(array_filter(array_map('intval', $selectedTableIds), fn(int $id) => $id > 0));
        if ($selectedTableIds === []) {
            return [];
        }

        $selectedIdentities = AnonymousSiebelTable::query()
            ->with('schema')
            ->whereIn('id', $selectedTableIds)
            ->get()
            ->mapWithKeys(function (AnonymousSiebelTable $table): array {
                $schema = strtoupper(trim((string) ($table->schema?->schema_name ?? '')));
                $name = strtoupper(trim((string) ($table->table_name ?? '')));

                return $schema !== '' && $name !== ''
                    ? [$schema . '|' . $name => (int) $table->getKey()]
                    : [];
            })
            ->all();

        if ($selectedIdentities === []) {
            return [];
        }

        $candidates = [];
        $columns = AnonymousSiebelColumn::query()
            ->with(['table.schema'])
            ->where(function ($query) {
                $query->whereNotNull('related_columns_raw')
                    ->orWhereNotNull('related_columns');
            })
            ->limit(5000)
            ->get();

        foreach ($columns as $column) {
            $childTableId = (int) ($column->table_id ?? 0);
            if ($childTableId <= 0 || in_array($childTableId, $selectedTableIds, true)) {
                continue;
            }

            foreach ($this->resolveForeignKeyRelationships($column) as $relationship) {
                if (strtoupper((string) ($relationship['direction'] ?? 'OUTBOUND')) !== 'OUTBOUND') {
                    continue;
                }

                $schema = strtoupper(trim((string) ($relationship['schema'] ?? '')));
                $table = strtoupper(trim((string) ($relationship['table'] ?? '')));
                $schemaCandidates = $this->schemaLookupCandidates($schema);
                $matched = false;
                foreach ($schemaCandidates as $candidate) {
                    if (isset($selectedIdentities[$candidate . '|' . $table])) {
                        $matched = true;
                        break;
                    }
                }

                if (! $matched) {
                    continue;
                }

                $candidates[$childTableId] = [
                    'table_id' => $childTableId,
                    'table' => $this->qualifiedDependencyColumnName($column),
                    'via_column' => $this->qualifiedDependencyColumnName($column),
                    'parent' => $this->relationshipLabel($relationship),
                ];
            }
        }

        return array_values($candidates);
    }
}
