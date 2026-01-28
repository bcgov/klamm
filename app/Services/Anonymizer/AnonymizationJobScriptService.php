<?php

namespace App\Services\Anonymizer;

use App\Services\Anonymizer\Concerns\BuildsDoubleSeededDeterministicOracleScripts;
use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\Anonymizer\AnonymizationMethods;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AnonymizationJobScriptService
{
    use BuildsDoubleSeededDeterministicOracleScripts;

    private const WHERE_IN_CHUNK_SIZE = 10000;

    protected const SEED_PLACEHOLDERS = [
        '{{SEED_MAP_LOOKUP}}',
        '{{SEED_EXPR}}',
        '{{SEED_SOURCE_QUALIFIED}}',
    ];

    protected function oracleStringLiteral(?string $value): string
    {
        if ($value === null) {
            return "''";
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    protected function oracleColumnTypeForColumn(AnonymousSiebelColumn $column): string
    {
        $typeName = strtolower(trim((string) ($column->getRelationValue('dataType')?->data_type_name ?? '')));

        if ($typeName === '') {
            $typeName = strtolower(trim((string) ($column->data_type ?? '')));
        }

        if (str_contains($typeName, 'clob')) {
            return 'CLOB';
        }

        if (str_contains($typeName, 'date')) {
            return 'DATE';
        }

        if (str_contains($typeName, 'timestamp')) {
            return 'TIMESTAMP';
        }

        if (str_contains($typeName, 'number') || str_contains($typeName, 'numeric') || str_contains($typeName, 'decimal')) {
            $precision = (int) ($column->data_precision ?? 0);
            $scale = (int) ($column->data_scale ?? 0);

            if ($precision > 0) {
                return $scale > 0 ? "NUMBER({$precision},{$scale})" : "NUMBER({$precision})";
            }

            return 'NUMBER';
        }

        if (str_contains($typeName, 'char') || str_contains($typeName, 'varchar')) {
            $length = (int) ($column->data_length ?? $column->char_length ?? 0);
            if ($length <= 0) {
                $length = 255;
            }

            $length = min($length, 4000);
            return "VARCHAR2({$length})";
        }

        return 'VARCHAR2(4000)';
    }

    protected function normalizeJobOption(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function methodUsesSeedPlaceholders(?AnonymizationMethods $method): bool
    {
        $sqlBlock = trim((string) ($method?->sql_block ?? ''));

        if ($sqlBlock === '') {
            return false;
        }

        foreach (self::SEED_PLACEHOLDERS as $placeholder) {
            if (str_contains($sqlBlock, $placeholder)) {
                return true;
            }
        }

        return false;
    }

    protected function oracleColumnMaxLength(AnonymousSiebelColumn $column): int
    {
        $typeName = strtolower(trim((string) ($column->data_type ?? '')));

        if (str_contains($typeName, 'clob')) {
            return 4000;
        }

        if (
            str_contains($typeName, 'char')
            || str_contains($typeName, 'varchar')
            || str_contains($typeName, 'nvarchar')
            || str_contains($typeName, 'nchar')
        ) {
            $length = (int) ($column->data_length ?? $column->char_length ?? 0);
            if ($length <= 0) {
                $length = 255;
            }

            return min($length, 4000);
        }

        return 4000;
    }

    public function buildForJob(AnonymizationJobs $job): string
    {
        $job->loadMissing([
            'columns.anonymizationMethods.packages',
            'columns.table.schema.database',
            'columns.parentColumns.table.schema.database',
        ]);

        $columns = $job->columns ?? collect();

        if ($this->normalizeJobOption($job->seed_store_mode) === 'double-seeded') {
            $script = $this->buildDoubleSeededDeterministicFromColumns($columns, $job);
        } else {
            $script = $this->buildFromColumns($columns, $job);
        }

        if (trim($script) === '') {
            return '-- No anonymization SQL generated: no columns or anonymization methods configured for this job.';
        }

        return $script;
    }

    public function buildCloneOnlyForJob(AnonymizationJobs $job): string
    {
        $rewriteContext = $this->buildJobTableRewriteContext(collect(), $job);

        if ($rewriteContext === []) {
            return '-- No SQL generated: this job has no explicit columns and no scoped databases/schemas/tables selected.';
        }

        $tableCloneStatements = $this->renderJobTableClones($rewriteContext);

        if ($tableCloneStatements === []) {
            return '-- No SQL generated: this job has no explicit columns and no scoped databases/schemas/tables selected.';
        }

        $lines = $this->buildHeaderLines($this->jobHeaderMetadata($job), $rewriteContext);

        $lines[] = $this->commentDivider('=');
        $targetMode = $this->normalizeJobOption((string) ($rewriteContext['target_table_mode'] ?? '')) ?: 'prefixed';
        $modeLabel = $targetMode === 'anon'
            ? 'mode INITIAL_* → ANON_*'
            : ('prefix ' . ($rewriteContext['table_prefix'] ?? 'none'));
        $lines[] = '-- Target Tables'
            . ' (schema ' . ($rewriteContext['target_schema'] ?? 'unknown') . ')'
            . ' (' . $modeLabel . ')';
        $lines[] = '-- Creates working copies and keeps all updates isolated.';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';
        $lines = array_merge($lines, $tableCloneStatements);
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $preMaskSql = trim((string) ($job->pre_mask_sql ?? ''));
        if ($preMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Pre-mask SQL';
            $lines[] = '-- Runs after target table clones are created, before seed maps and masking updates.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $preMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $postMaskSql = trim((string) ($job->post_mask_sql ?? ''));
        if ($postMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Post-mask SQL';
            $lines[] = '-- Runs after masking updates and seed maps are applied.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $postMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        return trim(implode(PHP_EOL, $lines));
    }

    public function reviewSeedContractsForJob(AnonymizationJobs $job): array
    {
        $job->loadMissing([
            'columns.anonymizationMethods.packages',
            'columns.table.schema.database',
            'columns.parentColumns.table.schema.database',
        ]);

        $columns = $job->columns ?? collect();

        if ($columns->isEmpty()) {
            return [
                'errors' => collect(),
                'warnings' => collect(),
                'issues' => collect(),
            ];
        }

        if (method_exists($columns, 'loadMissing')) {
            $columns->loadMissing([
                'anonymizationMethods.packages',
                'table.schema.database',
                'parentColumns.table.schema.database',
            ]);
        }

        $ordered = $this->topologicallySortColumns($columns);

        return $this->validateSeedContracts($ordered);
    }

    public function buildForColumnIds(array $columnIds, ?AnonymizationJobs $job = null): string
    {
        $columnIds = array_values(array_unique(array_filter(array_map('intval', $columnIds))));

        if ($columnIds === []) {
            return '';
        }

        $columns = collect();

        foreach (array_chunk($columnIds, self::WHERE_IN_CHUNK_SIZE) as $chunk) {
            $columns = $columns->merge(
                AnonymousSiebelColumn::query()
                    ->with([
                        'anonymizationMethods.packages',
                        'table.schema.database',
                        'parentColumns.table.schema.database',
                    ])
                    ->whereIn('id', $chunk)
                    ->get()
            );
        }

        return $this->buildFromColumns($columns, $job);
    }

    public function buildFromColumns(Collection $columns, ?AnonymizationJobs $job = null): string
    {
        if ($columns->isEmpty()) {
            return '';
        }

        if (method_exists($columns, 'loadMissing')) {
            $columns->loadMissing([
                'anonymizationMethods.packages',
                'table.schema.database',
                'parentColumns.table.schema.database',
            ]);
        }

        $ordered = $this->topologicallySortColumns($columns);

        if ($ordered->isEmpty()) {
            return '';
        }

        $seedProviders = $this->resolveSeedProviders($ordered);

        $rewriteContext = $this->buildJobTableRewriteContext($ordered, $job);
        $seedMapContext = $this->jobUsesSeedMapPlaceholders($ordered)
            ? $this->buildSeedMapContext($ordered, $seedProviders, $rewriteContext, $job)
            : [];

        $lines = $this->buildHeaderLines($this->jobHeaderMetadata($job), $rewriteContext);

        $contractReview = $this->validateSeedContracts($ordered, $seedProviders, $seedMapContext);

        if ($contractReview['errors']->isNotEmpty() || $contractReview['warnings']->isNotEmpty()) {
            $lines = array_merge($lines, $this->renderContractReview($contractReview));

            if ($contractReview['errors']->isNotEmpty()) {
                $lines[] = '-- SQL generation halted due to blocking seed contract violations.';
                return trim(implode(PHP_EOL, $lines));
            }
        }

        $impact = $this->buildImpactReport($ordered, $job, $seedProviders, $rewriteContext, $seedMapContext);
        if ($impact !== []) {
            $lines = array_merge($lines, $impact);
        }
        $packages = $this->collectPackagesFromColumns($ordered);

        if ($packages->isNotEmpty()) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Package Dependencies';
            $lines[] = '-- Ordered for deterministic exports';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';

            foreach ($packages as $package) {
                $lines[] = $this->commentDivider('-');
                $lines[] = '-- Package: ' . $package->display_label;

                if ($package->summary) {
                    $lines[] = '-- ' . trim($package->summary);
                }

                foreach ($package->compiledSqlBlocks() as $block) {
                    $lines[] = trim($this->rewritePackageSqlBlock((string) $block, $rewriteContext));
                    $lines[] = '';
                }
            }

            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $tableCloneStatements = $this->renderJobTableClones($rewriteContext);
        if ($tableCloneStatements !== []) {
            $lines[] = $this->commentDivider('=');
            $targetMode = $this->normalizeJobOption((string) ($rewriteContext['target_table_mode'] ?? '')) ?: 'prefixed';
            $modeLabel = $targetMode === 'anon'
                ? 'mode INITIAL_* → ANON_*'
                : ('prefix ' . ($rewriteContext['table_prefix'] ?? 'none'));
            $lines[] = '-- Target Tables'
                . ' (schema ' . ($rewriteContext['target_schema'] ?? 'unknown') . ')'
                . ' (' . $modeLabel . ')';
            $lines[] = '-- Creates working copies and keeps all updates isolated.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, $tableCloneStatements);
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $preMaskSql = trim((string) ($job?->pre_mask_sql ?? ''));
        if ($preMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Pre-mask SQL';
            $lines[] = '-- Runs after target table clones are created, before seed maps and masking updates.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $preMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $seedMapStatements = $this->renderSeedMapTables($seedMapContext);
        if ($seedMapStatements !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Seed Maps (relationship preservation)';
            $lines[] = '-- Lookup tables keep dependent keys aligned with seed providers.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, $seedMapStatements);
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        // Process columns in topological order to maintain dependency cascades.
        // Parent/seed-providing columns must be masked before their dependents.
        // This ensures FK relationships remain intact when both parent and child columns are anonymized.

        $lines[] = $this->commentDivider('=');
        $lines[] = '-- Column Masking (dependency-ordered)';
        $lines[] = '-- Columns are processed in topological order: parents before children.';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $orderedIds = $ordered->pluck('id')->all();
        $lastMethodId = null;

        // @var AnonymousSiebelColumn $column
        foreach ($ordered as $column) {
            $method = $this->resolveMethodForColumn($column);
            $methodId = $method?->id ?? 'none';

            // Emit a method heading when transitioning between methods (for readability).
            if ($methodId !== $lastMethodId) {
                $lines[] = $this->commentDivider('-');
                $lines[] = $this->methodHeading($method);
                $lastMethodId = $methodId;
            }

            $sqlBlock = trim((string) ($method?->sql_block ?? ''));

            // Annotate each column with its dependencies.
            $dependencies = $this->dependencyNames($column, $orderedIds);
            $depNote = $dependencies !== []
                ? ' (depends on: ' . implode(', ', $dependencies) . ')'
                : '';

            $lines[] = '-- Column: ' . $this->describeColumn($column) . $depNote;

            if ($sqlBlock === '') {
                $lines[] = '-- No SQL block defined for this method.';
            } else {
                // Render SQL for this single column, preserving dependency order.
                foreach ($this->renderSqlBlocksForColumns($sqlBlock, collect([$column]), $seedProviders, $rewriteContext, $seedMapContext) as $renderedBlock) {
                    $lines[] = $renderedBlock;
                }
            }

            $lines[] = '';
        }

        $postMaskSql = trim((string) ($job?->post_mask_sql ?? ''));
        if ($postMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Post-mask SQL';
            $lines[] = '-- Runs after masking updates complete.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $postMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $hygiene = $this->renderSeedMapHygieneSection($seedMapContext, $job);
        if ($hygiene !== []) {
            $lines = array_merge($lines, $hygiene);
        }

        // Commit final DML so masking updates persist.
        $lines[] = $this->commentDivider('=');
        $lines[] = '-- Finalize';
        $lines[] = 'COMMIT;';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return trim(implode(PHP_EOL, $lines));
    }

    protected function jobUsesSeedMapPlaceholders(Collection $columns): bool
    {
        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $sqlBlock = strtolower((string) ($method?->sql_block ?? ''));

            if ($sqlBlock === '') {
                continue;
            }

            if (
                str_contains($sqlBlock, strtolower('{{SEED_MAP_LOOKUP}}'))
                || str_contains($sqlBlock, strtolower('{{SEED_MAP_TABLE}}'))
            ) {
                return true;
            }
        }

        return false;
    }

    protected function validateSeedContracts(Collection $columns, array $seedProviders = [], array $seedMapContext = []): array
    {
        $selected = $columns->keyBy('id');
        $errors = collect();
        $warnings = collect();
        $issues = collect();

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $mode = $column->seed_contract_mode;
            $columnLabel = $this->describeColumn($column);

            $pushIssue = function (string $severity, string $message, string $code = 'seed_contract') use (&$issues, $column) {
                $issues->push([
                    'column_id' => $column->id,
                    'severity' => $severity,
                    'code' => $code,
                    'message' => $message,
                ]);
            };

            if (! $method) {
                $message = $columnLabel . ': No anonymization method is attached; seed contract cannot be evaluated.';

                // Allow SOURCE/EXTERNAL columns without a method (they can exist only to supply {{SEED_EXPR}}).
                if (in_array($mode, [SeedContractMode::SOURCE, SeedContractMode::EXTERNAL], true)) {
                    $detail = $message . ' Column is declared as a seed provider; continuing without a method.';
                    $warnings->push($detail);
                    $pushIssue('warning', $detail, 'missing_method');
                    continue;
                }

                if ($column->seed_contract_mode && $column->seed_contract_mode !== SeedContractMode::NONE) {
                    $detail = $message . ' Assign a method that honors the declared seed role.';
                    $errors->push($detail);
                    $pushIssue('error', $detail, 'missing_method');
                } else {
                    $warnings->push($message);
                    $pushIssue('warning', $message, 'missing_method');
                }

                continue;
            }

            $usesSeedPlaceholder = $this->methodUsesSeedPlaceholders($method);

            // Warn if a method is marked requires_seed but doesn't reference any seed placeholders.
            if ($method->requires_seed && ! $usesSeedPlaceholder) {
                $detail = $columnLabel . ': Method ' . $method->name
                    . ' is marked as requiring a seed, but its SQL block does not reference ' . implode(', ', self::SEED_PLACEHOLDERS) . '.';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'seed_placeholder_missing');
            }

            // Only enforce explicit mode mismatches; inferred emit/consume behavior doesn't require manual flags.
            if ($mode === SeedContractMode::SOURCE && ! $method->emits_seed) {
                $detail = $columnLabel . ': Declared as a seed source but method ' . $method->name . ' is not marked as emitting a seed.';
                $errors->push($detail);
                $pushIssue('error', $detail, 'source_mismatch');
            }

            if ($mode === SeedContractMode::CONSUMER && ! $method->requires_seed) {
                $detail = $columnLabel . ': Declared as a seed consumer but method ' . $method->name . ' does not require a seed.';
                $errors->push($detail);
                $pushIssue('error', $detail, 'consumer_mismatch');
            }

            if ($mode === SeedContractMode::CONSUMER && $method->requires_seed && ! $usesSeedPlaceholder) {
                $detail = $columnLabel . ': Declared as a seed consumer and method ' . $method->name
                    . ' requires a seed, but the method SQL does not reference seed placeholders. Seed wiring may be ineffective.';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'consumer_placeholder_missing');
            }

            if ($mode === SeedContractMode::COMPOSITE && ! $method->supports_composite_seed) {
                $detail = $columnLabel . ': Declared as a composite seed but method ' . $method->name . ' is not composite-ready.';
                $errors->push($detail);
                $pushIssue('error', $detail, 'composite_mismatch');
            }

            if (! $this->columnRequiresSeed($column, $method)) {
                continue;
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();

            if ($parents->isEmpty()) {
                $fallbackProvider = $this->inferSeedProviderFromSelection($column, $columns);

                if ($fallbackProvider) {
                    $detail = $columnLabel . ': No explicit parent dependency set; using inferred seed provider ' . $this->describeColumn($fallbackProvider) . '.';
                    $warnings->push($detail);
                    $pushIssue('warning', $detail, 'implicit_seed_provider');
                    continue;
                }

                $detail = $columnLabel . ': Seed consumer columns must declare at least one parent dependency.';
                $errors->push($detail);
                $pushIssue('error', $detail, 'missing_parent');
                continue;
            }

            foreach ($parents as $parentRelation) {
                $mandatory = $parentRelation->pivot->is_seed_mandatory ?? true;
                $bundleDescriptor = $this->describeSeedBundle($parentRelation);
                $parentLabel = $this->describeColumn($parentRelation);
                $selectedParent = $selected->get($parentRelation->id);

                if (! $selectedParent) {
                    // Allow EXTERNAL parents to be omitted from the job selection (non-blocking).
                    if ($mandatory && $parentRelation->seed_contract_mode !== SeedContractMode::EXTERNAL) {
                        $fallbackProvider = $this->inferSeedProviderFromSelection($column, $columns);

                        if ($fallbackProvider) {
                            $detail = $columnLabel . ': Requires parent ' . $parentLabel . $bundleDescriptor
                                . ' but it is not included in this job; using inferred seed provider ' . $this->describeColumn($fallbackProvider) . ' instead.';
                            $warnings->push($detail);
                            $pushIssue('warning', $detail, 'missing_parent_selection');
                        } else {
                            $detail = $columnLabel . ': Requires parent ' . $parentLabel . $bundleDescriptor . ' but it is not included in this job.';
                            $errors->push($detail);
                            $pushIssue('error', $detail, 'missing_parent_selection');
                        }
                    } else {
                        $detail = $columnLabel . ': Parent ' . $parentLabel . $bundleDescriptor . ' is not included; verify the external seed handshake.';
                        $warnings->push($detail);
                        $pushIssue('warning', $detail, 'external_seed_handshake');
                    }
                    continue;
                }

                $parentMethod = $this->resolveMethodForColumn($selectedParent);

                if (! $this->columnProvidesSeed($selectedParent, $parentMethod)) {
                    // If a selected parent doesn't provide a seed, warn (don't block) so SQL can still generate.
                    $detail = $columnLabel . ': Parent ' . $parentLabel . $bundleDescriptor
                        . ' is referenced as a dependency but is not declared as a seed provider; using it anyway. Consider marking it as SOURCE.';
                    $warnings->push($detail);
                    $pushIssue('warning', $detail, 'parent_not_seed');
                }

                // Require an explicit seed expression when a provider participates in a generated seed map.
                if (isset(($seedMapContext['providers'] ?? [])[(int) $selectedParent->id])) {
                    $expr = trim((string) ($selectedParent->seed_contract_expression ?? ''));
                    if ($expr === '') {
                        $defaultExpr = $this->seedExpressionForProvider($selectedParent);
                        $detail = $columnLabel . ': Parent ' . $parentLabel
                            . ' is used as a seed provider but is missing seed_contract_expression; defaulting to ' . $defaultExpr . '.';
                        $warnings->push($detail);
                        $pushIssue('warning', $detail, 'seed_provider_expression_missing');
                    }
                }
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'issues' => $issues,
        ];
    }

    protected function renderContractReview(array $review): array
    {
        $lines = [
            $this->commentDivider('='),
            '-- Seed Contract Review',
            $this->commentDivider('-'),
        ];

        if ($review['errors']->isNotEmpty()) {
            $lines[] = '-- Blocking issues:';
            foreach ($review['errors'] as $error) {
                $lines[] = '--   * ' . $error;
            }
        }

        if ($review['warnings']->isNotEmpty()) {
            if ($review['errors']->isNotEmpty()) {
                $lines[] = '--';
            }

            $lines[] = '-- Warnings:';
            foreach ($review['warnings'] as $warning) {
                $lines[] = '--   * ' . $warning;
            }
        }

        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function columnProvidesSeed(AnonymousSiebelColumn $column, ?AnonymizationMethods $method): bool
    {
        $mode = $column->seed_contract_mode;

        // Treat explicit seed modes as authoritative, regardless of method flags.
        if ($mode === SeedContractMode::SOURCE || $mode === SeedContractMode::COMPOSITE || $mode === SeedContractMode::EXTERNAL) {
            return true;
        }

        return (bool) ($method?->emits_seed);
    }

    protected function columnRequiresSeed(AnonymousSiebelColumn $column, ?AnonymizationMethods $method): bool
    {
        $mode = $column->seed_contract_mode;

        // Treat explicit seed modes as authoritative, regardless of method flags.
        if ($mode === SeedContractMode::CONSUMER || $mode === SeedContractMode::COMPOSITE) {
            return true;
        }

        // Use method SQL placeholders as the source of truth for whether a seed is actually consumed.
        return (bool) ($method?->requires_seed) && $this->methodUsesSeedPlaceholders($method);
    }

    protected function resolveSeedProviders(Collection $columns): array
    {
        $providers = [];

        $seedEmitters = $columns
            ->filter(function (AnonymousSiebelColumn $candidate) {
                return $this->columnProvidesSeed($candidate, $this->resolveMethodForColumn($candidate));
            })
            ->values();

        $emittersByTable = $seedEmitters
            ->groupBy(fn(AnonymousSiebelColumn $c) => (int) ($c->table_id ?? 0));

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $provider = $this->seedProviderForColumn($column, $method, $columns, $emittersByTable, $seedEmitters);

            $providers[$column->id] = [
                'provider' => $provider,
                'expression' => $this->seedExpressionForProvider($provider ?? $column),
            ];
        }

        return $providers;
    }

    protected function seedProviderForColumn(
        AnonymousSiebelColumn $column,
        ?AnonymizationMethods $method,
        Collection $selectedColumns,
        Collection $emittersByTable,
        Collection $seedEmitters
    ): ?AnonymousSiebelColumn {
        if (! $this->columnRequiresSeed($column, $method)) {
            return $this->columnProvidesSeed($column, $method) ? $column : null;
        }

        // Prefer an explicitly selected parent column as the seed provider.
        $parents = $column->getRelationValue('parentColumns') ?? collect();

        $selectedById = $selectedColumns->keyBy('id');

        foreach ($parents as $parent) {
            $selectedParent = $selectedById->get($parent->id);
            if (! $selectedParent) {
                continue;
            }

            // Use the explicitly selected parent; validation can still warn if it isn't a seed provider.
            return $selectedParent;
        }

        // Fall back to another seed emitter in the same table when no explicit parent is selected.
        $tableId = (int) ($column->table_id ?? 0);
        $sameTable = ($emittersByTable->get($tableId) ?? collect())
            ->filter(fn(AnonymousSiebelColumn $c) => $c->id !== $column->id)
            ->values();

        if ($sameTable->count() === 1) {
            return $sameTable->first();
        }

        if ($sameTable->count() > 1) {
            // If multiple emitters exist, pick the first by column name for determinism.
            return $sameTable->sortBy('column_name')->first();
        }

        // If still unresolved, fall back to the single remaining seed emitter in the job.
        $global = $seedEmitters
            ->filter(fn(AnonymousSiebelColumn $c) => $c->id !== $column->id)
            ->values();

        if ($global->count() === 1) {
            return $global->first();
        }

        // If no safe provider can be chosen, return null and let validation enforce explicit parents.
        return null;
    }

    // Resolve {{SEED_EXPR}} for a seed provider (explicit expression, else default to tgt.<column>).
    protected function seedExpressionForProvider(AnonymousSiebelColumn $provider): string
    {
        $expression = trim((string) ($provider->seed_contract_expression ?? ''));

        if ($expression !== '') {
            return $expression;
        }

        return 'tgt.' . ($provider->column_name ?? 'seed');
    }

    protected function inferSeedProviderFromSelection(AnonymousSiebelColumn $column, Collection $selectedColumns): ?AnonymousSiebelColumn
    {
        $method = $this->resolveMethodForColumn($column);

        if (! $this->columnRequiresSeed($column, $method)) {
            return null;
        }

        $seedEmitters = $selectedColumns
            ->filter(function (AnonymousSiebelColumn $candidate) {
                return $this->columnProvidesSeed($candidate, $this->resolveMethodForColumn($candidate));
            })
            ->values();

        if ($seedEmitters->isEmpty()) {
            return null;
        }

        $tableId = (int) ($column->table_id ?? 0);
        $sameTable = $seedEmitters
            ->filter(fn(AnonymousSiebelColumn $c) => (int) ($c->table_id ?? 0) === $tableId && $c->id !== $column->id)
            ->values();

        if ($sameTable->count() === 1) {
            return $sameTable->first();
        }

        if ($sameTable->count() > 1) {
            return $sameTable->sortBy('column_name')->first();
        }

        $global = $seedEmitters
            ->filter(fn(AnonymousSiebelColumn $c) => $c->id !== $column->id)
            ->values();

        return $global->count() === 1 ? $global->first() : null;
    }

    protected function describeSeedBundle(AnonymousSiebelColumn $parent): string
    {
        $pivot = $parent->pivot;

        if (! $pivot) {
            return '';
        }

        $label = trim((string) ($pivot->seed_bundle_label ?? ''));
        $components = $pivot->seed_bundle_components ?? null;

        if (is_string($components)) {
            $decoded = json_decode($components, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $components = $decoded;
            } else {
                $components = null;
            }
        }

        if ($components instanceof Collection) {
            $components = $components->all();
        }

        $componentLabel = null;

        if (is_array($components) && $components !== []) {
            $componentLabel = implode(' + ', array_map('strval', $components));
        }

        $parts = array_filter([$label, $componentLabel]);

        return $parts === [] ? '' : ' [' . implode(' | ', $parts) . ']';
    }

    protected function collectPackagesFromColumns(Collection $columns): Collection
    {
        return $columns
            ->map(fn(AnonymousSiebelColumn $column) => $this->resolveMethodForColumn($column))
            ->filter()
            ->flatMap(fn(AnonymizationMethods $method) => $method->packages ?? collect())
            ->filter()
            ->unique(fn($package) => $package->id)
            ->values();
    }

    protected function renderSqlBlocksForColumns(string $template, Collection $columns, array $seedProviders = [], array $rewriteContext = [], array $seedMapContext = []): array
    {
        $output = [];

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $rendered = $this->applyPlaceholders($template, $column, $seedProviders[$column->id] ?? null, $rewriteContext, $seedMapContext);

            $output[] = '-- Applies to: ' . $this->describeColumn($column);
            $output[] = $rendered;
            $output[] = '';
        }

        return $output === [] ? [$template] : $output;
    }

    protected function applyPlaceholders(
        string $template,
        AnonymousSiebelColumn $column,
        ?array $seedProvider = null,
        array $rewriteContext = [],
        array $seedMapContext = []
    ): string {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');
        $database = $schema?->getRelationValue('database');

        $tableId = (int) ($table?->getKey() ?? $column->table_id ?? 0);
        $tableMap = $rewriteContext['tables_by_id'] ?? [];
        $mapped = $tableId > 0 ? ($tableMap[$tableId] ?? null) : null;

        $renderSchemaName = $mapped['target_schema'] ?? ($schema?->schema_name ?? '');
        $renderTableName = $mapped['target_table'] ?? ($table?->table_name ?? '');

        $qualifiedTable = collect([
            // Omit database prefix when rewriting so SQL runs in the target schema.
            $mapped ? null : $database?->database_name,
            $renderSchemaName,
            $renderTableName,
        ])->filter()->implode('.');

        $seedColumnName = $seedProvider['provider']?->column_name ?? $column->column_name ?? '';
        $seedSourceLabel = isset($seedProvider['provider'])
            ? $this->describeColumn($seedProvider['provider'])
            : $this->describeColumn($column);
        $seedQualified = $this->seedQualifiedReference($column, $seedProvider['provider'] ?? null, $qualifiedTable, $rewriteContext);
        $seedExpression = $seedProvider['expression'] ?? ('tgt.' . $seedColumnName);

        $seedMap = $this->seedMapForColumn($seedProvider['provider'] ?? null, $seedMapContext);
        $seedMapTable = $seedMap['seed_map_table'] ?? '';
        $seedMapLookup = $seedMapTable !== ''
            ? '(SELECT sm.new_value FROM ' . $seedMapTable . ' sm WHERE sm.old_value = tgt.' . ($column->column_name ?? '') . ' AND ROWNUM = 1)'
            : '';

        $jobSeed = $rewriteContext['job_seed'] ?? '';
        $jobSeedLiteral = $rewriteContext['job_seed_literal'] ?? "''";

        $columnMaxLength = $this->oracleColumnMaxLength($column);
        $columnName = $column->column_name ?? '';
        $columnMaxLengthExpr = ($columnMaxLength > 0 && $columnMaxLength < 4000)
            ? (string) $columnMaxLength
            : ($columnName !== '' ? ('length(tgt.' . $columnName . ')') : '4000');

        $replacements = [
            '{{TABLE}}' => $qualifiedTable ?: ($renderTableName ?: ($table?->table_name ?? '{{TABLE}}')),
            '{{TABLE_NAME}}' => $renderTableName,
            '{{SCHEMA}}' => $renderSchemaName,
            '{{DATABASE}}' => $mapped ? '' : ($database?->database_name ?? ''),
            '{{COLUMN}}' => $column->column_name ?? '',
            '{{COLUMN_MAX_LEN}}' => (string) $columnMaxLength,
            '{{COLUMN_MAX_LEN_EXPR}}' => $columnMaxLengthExpr,
            '{{ALIAS}}' => 'tgt',
            '{{SEED_COLUMN}}' => $seedColumnName,
            '{{SEED_SOURCE}}' => $seedSourceLabel,
            '{{SEED_SOURCE_QUALIFIED}}' => $seedQualified,
            '{{SEED_EXPR}}' => $seedExpression,
            '{{SEED_MAP_TABLE}}' => $seedMapTable,
            '{{SEED_MAP_LOOKUP}}' => $seedMapLookup,
            '{{JOB_SEED}}' => is_string($jobSeed) ? $jobSeed : '',
            '{{JOB_SEED_LITERAL}}' => is_string($jobSeedLiteral) ? $jobSeedLiteral : "''",
        ];

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template);

        $rawReplace = $rewriteContext['raw_replace'] ?? [];
        if ($rawReplace !== []) {
            $rendered = str_replace(array_keys($rawReplace), array_values($rawReplace), $rendered);
        }

        return $rendered;
    }

    protected function buildJobTableRewriteContext(Collection $columns, ?AnonymizationJobs $job): array
    {
        $targetSchema = $this->targetSchemaForJob($job);
        $tablePrefix = $this->tablePrefixForJob($job);
        $targetTableMode = $this->normalizeJobOption($job?->target_table_mode) ?: 'prefixed';

        if (! $targetSchema || ! $tablePrefix) {
            return [];
        }

        $tables = collect();

        // For FULL jobs, clone every table in the selected schema scope.
        if ($job && $job->job_type === AnonymizationJobs::TYPE_FULL) {
            $schemaIds = $this->schemaIdsForJobOrSelection($job, $columns);

            if ($schemaIds !== []) {
                $tables = AnonymousSiebelTable::query()
                    ->withTrashed()
                    ->with(['schema.database'])
                    ->whereIn('schema_id', $schemaIds)
                    ->orderBy('table_name')
                    ->get();
            }
        }

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $table = $column->getRelationValue('table');
            if ($table) {
                $tables->push($table);
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();
            foreach ($parents as $parent) {
                $parentTable = $parent->getRelationValue('table');
                if ($parentTable) {
                    $tables->push($parentTable);
                }
            }
        }

        $tables = $tables
            ->filter()
            ->unique(fn($t) => (int) $t->getKey())
            ->values();

        $tablesById = [];
        $rawReplace = [];

        foreach ($tables as $table) {
            $schema = $table->getRelationValue('schema');
            $database = $schema?->getRelationValue('database');
            $sourceSchema = $schema?->schema_name;
            $sourceTable = $table->table_name;

            if (! $sourceSchema || ! $sourceTable) {
                continue;
            }

            $targetTableName = $this->targetTableNameForSourceTable($sourceTable, $tablePrefix, $targetTableMode);
            $targetTable = $this->oracleIdentifier($targetTableName);
            $targetQualified = $targetSchema . '.' . $targetTable;
            $sourceQualified = $sourceSchema . '.' . $sourceTable;

            $tablesById[(int) $table->getKey()] = [
                'source_schema' => $sourceSchema,
                'source_table' => $sourceTable,
                'source_qualified' => $sourceQualified,
                'target_schema' => $targetSchema,
                'target_table' => $targetTable,
                'target_qualified' => $targetQualified,
            ];

            // Rewrite qualified source names to their target working-copy equivalents.
            $rawReplace[$sourceQualified] = $targetQualified;

            if ($database?->database_name) {
                $rawReplace[$database->database_name . '.' . $sourceQualified] = $targetQualified;
            }
        }

        // Sort longer matches first to reduce accidental partial rewrites.
        if ($rawReplace !== []) {
            uksort($rawReplace, fn($a, $b) => strlen($b) <=> strlen($a));
        }

        return [
            'target_schema' => $targetSchema,
            'table_prefix' => $tablePrefix,
            'target_table_mode' => $targetTableMode,
            'tables_by_id' => $tablesById,
            'raw_replace' => $rawReplace,
            'seed_store_mode' => trim((string) ($job?->seed_store_mode ?? '')),
            'seed_store_schema' => trim((string) ($job?->seed_store_schema ?? '')),
            'seed_store_prefix' => trim((string) ($job?->seed_store_prefix ?? '')),
            'seed_map_hygiene_mode' => trim((string) ($job?->seed_map_hygiene_mode ?? '')),
            'job_seed' => (string) ($job?->job_seed ?? ''),
            'job_seed_literal' => $this->oracleStringLiteral($job?->job_seed),
        ];
    }

    protected function targetTableNameForSourceTable(string $sourceTable, string $tablePrefix, string $mode): string
    {
        $mode = $this->normalizeJobOption($mode);

        if ($mode === 'anon') {
            // In anon mode, write into ANON_* tables (including INITIAL_* -> ANON_*).
            if (Str::startsWith($sourceTable, 'ANON_')) {
                return $sourceTable;
            }

            if (Str::startsWith($sourceTable, 'INITIAL_')) {
                return 'ANON_' . Str::after($sourceTable, 'INITIAL_');
            }

            return 'ANON_' . $sourceTable;
        }

        // In prefixed mode, write into <prefix>_<source_table> working copies.
        return $tablePrefix . '_' . $sourceTable;
    }

    protected function renderSeedMapHygieneSection(array $seedMapContext, ?AnonymizationJobs $job): array
    {
        $seedStoreMode = $this->normalizeJobOption($job?->seed_store_mode);
        $mode = $this->normalizeJobOption($job?->seed_map_hygiene_mode);

        if ($seedStoreMode !== 'persistent' || $mode === '' || $mode === 'none') {
            return [];
        }

        $providers = $seedMapContext['providers'] ?? [];
        if (! is_array($providers) || $providers === []) {
            return [];
        }

        $tables = [];
        foreach ($providers as $provider) {
            if (($provider['seed_map_persistence'] ?? null) !== 'persistent') {
                continue;
            }

            $table = trim((string) ($provider['seed_map_table'] ?? ''));
            if ($table !== '') {
                $tables[$table] = true;
            }
        }

        $tables = array_keys($tables);
        sort($tables);

        if ($tables === []) {
            return [];
        }

        $commented = $mode !== 'execute';
        $prefix = $commented ? '-- ' : '';

        $lines = [
            $this->commentDivider('='),
            '-- Seed Map Hygiene (Oracle MGMT_DM_TT analogue)',
            '-- Seed maps store old→new value mappings and should be removed before exporting/cloning to less-secure environments.',
            '-- Mode: ' . ($commented ? 'commented' : 'execute'),
            $this->commentDivider('='),
            '',
        ];

        foreach ($tables as $table) {
            $lines[] = $prefix . '-- Drop seed map: ' . $table;
            $lines[] = $prefix . 'BEGIN';
            $lines[] = $prefix . "  EXECUTE IMMEDIATE 'DROP TABLE {$table} PURGE';";
            $lines[] = $prefix . 'EXCEPTION';
            $lines[] = $prefix . '  WHEN OTHERS THEN';
            $lines[] = $prefix . '    IF SQLCODE != -942 THEN RAISE; END IF;';
            $lines[] = $prefix . 'END;';
            $lines[] = $prefix . '/';
            $lines[] = '';
        }

        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function buildImpactReport(
        Collection $columns,
        ?AnonymizationJobs $job,
        array $seedProviders,
        array $rewriteContext,
        array $seedMapContext
    ): array {
        $lines = [
            $this->commentDivider('='),
            '-- Impact Report (heuristics)',
            '-- This section mirrors Oracle-style preflight guidance using metadata + method templates only.',
            '-- It does not inspect real data or constraints; treat warnings as prompts for review.',
            $this->commentDivider('='),
            '',
        ];

        $warnings = [];

        $seedStoreMode = $this->normalizeJobOption($job?->seed_store_mode);
        if ($seedStoreMode === 'persistent') {
            $warnings[] = 'Persistent seed maps are enabled: ensure the seed store schema is access-controlled and dropped before distributing masked datasets.';
        }

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $sqlBlock = strtolower(trim((string) ($method?->sql_block ?? '')));

            if ($sqlBlock === '') {
                continue;
            }

            $label = $this->describeColumn($column);
            $dataType = strtoupper(trim((string) ($column->getRelationValue('dataType')?->data_type_name ?? '')));
            $length = (int) ($column->data_length ?? $column->char_length ?? 0);
            $mode = $column->seed_contract_mode;

            $isKeyLike = preg_match('/(^|_)(id|rowid|row_id|integration_id|key)(_|$)/i', (string) ($column->column_name ?? '')) === 1;

            $isConditional = str_contains($sqlBlock, 'case ') || (str_contains($sqlBlock, ' when ') && str_contains($sqlBlock, ' then '));
            $usesSeedMapLookup = str_contains($sqlBlock, strtolower('{{SEED_MAP_LOOKUP}}'));
            $usesHash = str_contains($sqlBlock, 'standard_hash') || str_contains($sqlBlock, 'dbms_crypto') || str_contains($sqlBlock, 'sha');
            $usesRandom = str_contains($sqlBlock, 'dbms_random') || str_contains($sqlBlock, 'random');
            $usesRegexp = str_contains($sqlBlock, 'regexp_replace');

            if ($isConditional) {
                if (($column->getRelationValue('parentColumns') ?? collect())->isNotEmpty() || $mode === SeedContractMode::CONSUMER || $mode === SeedContractMode::COMPOSITE) {
                    $warnings[] = $label . ': Conditional masking detected; column participates in a dependency/seed graph. Oracle warns of conditional “bleeding” with duplicates + dependents. Prefer deterministic mapping tables (seed maps) over inline CASE for key fields.';
                } else {
                    $warnings[] = $label . ': Conditional masking detected. Review duplicates and ensure conditional branches do not produce collisions.';
                }
            }

            if ($usesSeedMapLookup && $seedStoreMode !== 'persistent') {
                $warnings[] = $label . ': Uses {{SEED_MAP_LOOKUP}} but seed store is not persistent. Cross-run determinism requires persistent seed maps.';
            }

            if ($usesHash && ($dataType === 'VARCHAR' || $dataType === 'VARCHAR2' || str_contains($dataType, 'CHAR'))) {
                if ($length > 0 && $length < 32) {
                    $warnings[] = $label . ": Hashing detected with {$dataType}({$length}). Risk of truncation/collisions. Ensure the expression output fits the column (e.g., use RAWTOHEX + adequate length) and verify unique constraints.";
                } else {
                    $warnings[] = $label . ": Hashing detected. Ensure expression output type/length matches {$dataType}" . ($length > 0 ? "({$length})" : '') . ' and does not violate uniqueness constraints.';
                }
            }

            if ($usesRandom && $isKeyLike && $length > 0 && $length <= 8) {
                $warnings[] = $label . ": Randomization detected on key-like column with short length ({$length}). Uniqueness capacity may be too small; consider deterministic seed mapping or a larger target width.";
            }

            if ($usesRegexp) {
                $warnings[] = $label . ': REGEXP-based masking detected. Ensure all original values match the regex to preserve one-to-one mapping and avoid uniqueness violations.';
            }
        }

        $warnings = array_values(array_unique(array_filter(array_map('trim', $warnings))));

        if ($warnings === []) {
            $lines[] = '-- No heuristic warnings generated.';
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            return $lines;
        }

        foreach ($warnings as $warning) {
            $lines[] = '-- WARNING: ' . $warning;
        }

        $lines[] = '';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function schemaIdsForJobOrSelection(AnonymizationJobs $job, Collection $selectedColumns): array
    {
        $schemaIds = DB::table('anonymization_job_schemas')
            ->where('job_id', (int) $job->getKey())
            ->pluck('schema_id')
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($schemaIds !== []) {
            return $schemaIds;
        }

        $tableIds = DB::table('anonymization_job_tables')
            ->where('job_id', (int) $job->getKey())
            ->pluck('table_id')
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tableIds !== []) {
            return DB::table('anonymous_siebel_tables')
                ->whereIn('id', $tableIds)
                ->pluck('schema_id')
                ->map(fn($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $databaseIds = DB::table('anonymization_job_databases')
            ->where('job_id', (int) $job->getKey())
            ->pluck('database_id')
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($databaseIds !== []) {
            return DB::table('anonymous_siebel_schemas')
                ->whereIn('database_id', $databaseIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $selectedColumns
            ->map(fn(AnonymousSiebelColumn $column) => (int) ($column->getRelationValue('table')?->schema_id ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function targetSchemaForJob(?AnonymizationJobs $job): ?string
    {
        $override = trim((string) ($job?->target_schema ?? ''));

        if ($override !== '') {
            // Sanitize the schema override into a safe Oracle identifier.
            return $this->oracleIdentifier(Str::upper($override));
        }

        $type = $job?->job_type;

        return match ($type) {
            AnonymizationJobs::TYPE_FULL => 'SBLANONF',
            AnonymizationJobs::TYPE_PARTIAL => 'SBLANONP',
            default => null,
        };
    }

    protected function tablePrefixForJob(?AnonymizationJobs $job): ?string
    {
        $name = trim((string) ($job?->name ?? ''));

        if ($name === '') {
            return null;
        }

        $parts = preg_split('/[^A-Za-z0-9]+/', $name) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));

        if ($parts === []) {
            return null;
        }

        $parts = array_map(fn($part) => Str::studly($part), $parts);

        // Convert the job name into a safe prefix like Foo_Bar (Studly parts joined by _).
        return implode('_', $parts);
    }

    protected function renderJobTableClones(array $rewriteContext): array
    {
        $tablesById = $rewriteContext['tables_by_id'] ?? [];

        if ($tablesById === []) {
            return [];
        }

        $lines = [];
        foreach ($tablesById as $mapping) {
            $source = $mapping['source_qualified'] ?? null;
            $target = $mapping['target_qualified'] ?? null;
            if (! $source || ! $target) {
                continue;
            }

            $lines[] = '-- Clone: ' . $source . ' -> ' . $target;
            $lines[] = 'BEGIN';
            $lines[] = "  EXECUTE IMMEDIATE 'DROP TABLE {$target} PURGE';";
            $lines[] = 'EXCEPTION';
            $lines[] = '  WHEN OTHERS THEN';
            $lines[] = '    IF SQLCODE != -942 THEN RAISE; END IF;';
            $lines[] = 'END;';
            $lines[] = '/';
            $lines[] = 'CREATE TABLE ' . $target . ' AS SELECT * FROM ' . $source . ';';
            $lines[] = '';
        }

        return $lines;
    }

    protected function oracleIdentifier(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        $clean = preg_replace('/_+/', '_', (string) $clean);
        $clean = trim((string) $clean, '_');
        if ($clean === '') {
            $clean = 'OBJ';
        }

        if (strlen($clean) <= 30) {
            return $clean;
        }

        $hash = substr(md5($clean), 0, 8);
        $baseLen = 30 - 1 - 8;
        return substr($clean, 0, $baseLen) . '_' . $hash;
    }

    protected function rewritePackageSqlBlock(string $block, array $rewriteContext): string
    {
        $prefix = $rewriteContext['table_prefix'] ?? null;

        if (! is_string($prefix) || $prefix === '') {
            return $block;
        }

        preg_match_all('/\bcreate\s+table\s+([A-Za-z0-9_]+)\b/i', $block, $matches);
        $tableNames = array_values(array_unique($matches[1] ?? []));

        if ($tableNames === []) {
            return $block;
        }

        foreach ($tableNames as $tableName) {
            if (! is_string($tableName) || $tableName === '' || str_contains($tableName, '.')) {
                continue;
            }

            $prefixed = $this->oracleIdentifier($prefix . '_' . $tableName);
            $block = preg_replace('/\b' . preg_quote($tableName, '/') . '\b/', $prefixed, $block);
        }

        return $block;
    }

    protected function buildSeedMapContext(Collection $columns, array $seedProviders, array $rewriteContext, ?AnonymizationJobs $job = null): array
    {
        $providers = [];

        $targetSchema = $rewriteContext['target_schema'] ?? null;
        $prefix = $rewriteContext['table_prefix'] ?? null;
        $tableMap = $rewriteContext['tables_by_id'] ?? [];

        $seedStoreMode = strtolower(trim((string) ($job?->seed_store_mode ?? ($rewriteContext['seed_store_mode'] ?? 'temporary'))));
        $seedStoreSchema = trim((string) ($job?->seed_store_schema ?? ($rewriteContext['seed_store_schema'] ?? '')));
        $seedStorePrefix = trim((string) ($job?->seed_store_prefix ?? ($rewriteContext['seed_store_prefix'] ?? '')));

        $isPersistent = $seedStoreMode === 'persistent';
        if ($seedStoreSchema === '') {
            $seedStoreSchema = (string) $targetSchema;
        }

        if ($seedStorePrefix === '') {
            $seedStorePrefix = (string) $prefix;
        }

        if (! is_string($targetSchema) || $targetSchema === '' || ! is_string($prefix) || $prefix === '' || ! is_array($tableMap)) {
            return [];
        }

        if ($isPersistent && (! is_string($seedStoreSchema) || $seedStoreSchema === '' || ! is_string($seedStorePrefix) || $seedStorePrefix === '')) {
            return [];
        }

        $providerIds = [];

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            if (! $this->columnRequiresSeed($column, $method)) {
                continue;
            }

            $provider = $seedProviders[$column->id]['provider'] ?? null;
            if ($provider instanceof AnonymousSiebelColumn) {
                $providerIds[(int) $provider->id] = true;
            }
        }

        foreach (array_keys($providerIds) as $providerId) {
            // @var AnonymousSiebelColumn|null $provider
            $provider = $columns->firstWhere('id', $providerId);
            if (! $provider) {
                continue;
            }

            $providerTable = $provider->getRelationValue('table');
            $tableId = (int) ($providerTable?->getKey() ?? $provider->table_id ?? 0);
            $mapped = $tableId > 0 ? ($tableMap[$tableId] ?? null) : null;

            if (! is_array($mapped) || ! isset($mapped['target_qualified'], $mapped['source_table'])) {
                continue;
            }

            $seedMapName = $this->oracleIdentifier(
                ($isPersistent ? $seedStorePrefix : $prefix)
                    . '_SEEDMAP_' . ($mapped['source_table'] ?? 'T') . '_' . ($provider->column_name ?? 'C')
            );

            // Load dataType so DDL uses the canonical Oracle column type.
            if (! $provider->relationLoaded('dataType')) {
                $provider->loadMissing('dataType');
            }

            $columnType = $this->oracleColumnTypeForColumn($provider);

            $seedExpr = $this->seedExpressionForProvider($provider);
            $seedExpr = $this->renderSeedExpressionPlaceholders($seedExpr, $rewriteContext);

            $providers[(int) $providerId] = [
                'provider_id' => (int) $providerId,
                'provider_column' => $provider->column_name,
                'provider_table' => $mapped['target_qualified'],
                'seed_expression' => $seedExpr,
                'seed_map_table' => ($isPersistent ? $seedStoreSchema : $targetSchema) . '.' . $seedMapName,
                'seed_map_persistence' => $isPersistent ? 'persistent' : 'temporary',
                'old_value_type' => $columnType,
                'new_value_type' => $columnType,
            ];
        }

        return [
            'providers' => $providers,
        ];
    }

    protected function renderSeedExpressionPlaceholders(string $expression, array $rewriteContext): string
    {
        $jobSeedLiteral = $rewriteContext['job_seed_literal'] ?? "''";
        $jobSeed = $rewriteContext['job_seed'] ?? '';

        return str_replace(
            ['{{JOB_SEED_LITERAL}}', '{{JOB_SEED}}'],
            [is_string($jobSeedLiteral) ? $jobSeedLiteral : "''", is_string($jobSeed) ? $jobSeed : ''],
            $expression
        );
    }

    protected function renderSeedMapTables(array $seedMapContext): array
    {
        $providers = $seedMapContext['providers'] ?? [];
        if (! is_array($providers) || $providers === []) {
            return [];
        }

        $lines = [];
        foreach ($providers as $provider) {
            $seedMapTable = $provider['seed_map_table'] ?? null;
            $providerTable = $provider['provider_table'] ?? null;
            $providerColumn = $provider['provider_column'] ?? null;
            $seedExpr = $provider['seed_expression'] ?? null;
            $persistence = $provider['seed_map_persistence'] ?? 'temporary';
            $oldValueType = $provider['old_value_type'] ?? 'VARCHAR2(4000)';
            $newValueType = $provider['new_value_type'] ?? 'VARCHAR2(4000)';

            if (! $seedMapTable || ! $providerTable || ! $providerColumn || ! $seedExpr) {
                continue;
            }

            $lines[] = '-- Seed map for: ' . $providerTable . '.' . $providerColumn;

            if ($persistence === 'persistent') {
                $lines[] = 'BEGIN';
                $lines[] = "  EXECUTE IMMEDIATE 'CREATE TABLE {$seedMapTable} (old_value {$oldValueType} PRIMARY KEY, new_value {$newValueType})';";
                $lines[] = 'EXCEPTION';
                $lines[] = '  WHEN OTHERS THEN';
                $lines[] = '    IF SQLCODE != -955 THEN RAISE; END IF;';
                $lines[] = 'END;';
                $lines[] = '/';

                $lines[] = 'MERGE INTO ' . $seedMapTable . ' sm';
                $lines[] = 'USING (';
                $lines[] = '  SELECT DISTINCT';
                $lines[] = '    tgt.' . $providerColumn . ' AS old_value,';
                $lines[] = '    ' . $seedExpr . ' AS new_value';
                $lines[] = '  FROM ' . $providerTable . ' tgt';
                $lines[] = ') src';
                $lines[] = 'ON (sm.old_value = src.old_value)';
                $lines[] = 'WHEN NOT MATCHED THEN';
                $lines[] = '  INSERT (old_value, new_value) VALUES (src.old_value, src.new_value);';
                $lines[] = '';
            } else {
                $lines[] = 'BEGIN';
                $lines[] = "  EXECUTE IMMEDIATE 'DROP TABLE {$seedMapTable} PURGE';";
                $lines[] = 'EXCEPTION';
                $lines[] = '  WHEN OTHERS THEN';
                $lines[] = '    IF SQLCODE != -942 THEN RAISE; END IF;';
                $lines[] = 'END;';
                $lines[] = '/';
                $lines[] = 'CREATE TABLE ' . $seedMapTable . ' AS';
                $lines[] = 'SELECT';
                $lines[] = '  tgt.' . $providerColumn . ' AS old_value,';
                $lines[] = '  ' . $seedExpr . ' AS new_value';
                $lines[] = 'FROM ' . $providerTable . ' tgt;';
                $lines[] = '';
            }
        }

        return $lines;
    }

    protected function seedMapForColumn(?AnonymousSiebelColumn $provider, array $seedMapContext): array
    {
        if (! $provider) {
            return [];
        }

        $providers = $seedMapContext['providers'] ?? [];
        if (! is_array($providers)) {
            return [];
        }

        return $providers[(int) $provider->id] ?? [];
    }

    protected function seedQualifiedReference(
        AnonymousSiebelColumn $column,
        ?AnonymousSiebelColumn $provider,
        string $fallbackQualifiedTable,
        array $rewriteContext
    ): string {
        $subject = $provider ?: $column;
        $subjectTable = $subject->getRelationValue('table');
        $tableId = (int) ($subjectTable?->getKey() ?? $subject->table_id ?? 0);
        $mapped = ($rewriteContext['tables_by_id'] ?? [])[$tableId] ?? null;

        $qualified = $mapped['target_qualified'] ?? null;
        if ($qualified) {
            return $qualified . '.' . ($subject->column_name ?? '');
        }

        if ($provider) {
            return $this->describeColumn($provider);
        }

        return $fallbackQualifiedTable
            ? ($fallbackQualifiedTable . '.' . ($column->column_name ?? ''))
            : ($column->column_name ?? '');
    }

    protected function jobHeaderMetadata(?AnonymizationJobs $job): array
    {
        if (! $job) {
            return [
                'title' => 'Ad-hoc Anonymization Preview',
                'job_type' => null,
                'output' => null,
            ];
        }

        return [
            'title' => 'Anonymization Job: ' . $job->name,
            'job_type' => $job->job_type ? Str::title($job->job_type) : null,
            'output' => $job->output_format ? Str::upper($job->output_format) : null,
        ];
    }

    protected function buildHeaderLines(array $jobMeta, array $rewriteContext = []): array
    {
        $lines = [
            $this->commentDivider('='),
            '-- ' . $jobMeta['title'],
            '-- Generated: ' . now()->toDateTimeString(),
        ];

        if ($jobMeta['job_type']) {
            $lines[] = '-- Job Type: ' . $jobMeta['job_type'];
        }

        if ($jobMeta['output']) {
            $lines[] = '-- Output Format: ' . $jobMeta['output'];
        }

        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $targetSchema = $rewriteContext['target_schema'] ?? null;
        if (is_string($targetSchema) && $targetSchema !== '') {
            $lines[] = '-- Execute everything within the target schema by default.';
            $lines[] = 'ALTER SESSION SET CURRENT_SCHEMA = ' . $targetSchema . ';';
            $lines[] = '';
        }

        return $lines;
    }

    protected function commentDivider(string $char = '=', int $length = 70): string
    {
        return '-- ' . str_repeat($char, $length);
    }

    protected function methodHeading(?AnonymizationMethods $method): string
    {
        if (! $method) {
            return '-- Method: (not assigned)';
        }

        $label = '-- Method: ' . $method->name;

        $categorySummary = $method->categorySummary();
        if ($categorySummary) {
            $label .= ' [' . $categorySummary . ']';
        }

        return $label;
    }

    protected function columnsListing(Collection $columns, array $orderedIds): string
    {
        $idPosition = array_flip($orderedIds);

        // @var Collection<int, AnonymousSiebelColumn> $columns
        $columns = $columns->sortBy(fn(AnonymousSiebelColumn $column) => $idPosition[$column->id] ?? PHP_INT_MAX);

        $lines = ['-- Columns:'];

        foreach ($columns as $column) {
            $dependencies = $this->dependencyNames($column, $orderedIds);
            $line = sprintf('--   - %s', $this->describeColumn($column));

            if ($dependencies !== []) {
                $line .= ' (depends on: ' . implode(', ', $dependencies) . ')';
            }

            $lines[] = $line;
        }

        return implode(PHP_EOL, $lines);
    }

    protected function dependencyNames(AnonymousSiebelColumn $column, array $selectedIds): array
    {
        $selectedLookup = array_flip($selectedIds);

        $parents = $column->getRelationValue('parentColumns') ?? collect();

        return $parents
            ->filter(fn(AnonymousSiebelColumn $parent) => isset($selectedLookup[$parent->id]))
            ->map(fn(AnonymousSiebelColumn $parent) => $this->describeColumn($parent))
            ->unique()
            ->values()
            ->all();
    }

    protected function describeColumn(AnonymousSiebelColumn $column): string
    {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');
        $database = $schema?->getRelationValue('database');

        $segments = array_filter([
            $database?->database_name,
            $schema?->schema_name,
            $table?->table_name,
            $column->column_name,
        ]);

        return implode('.', $segments);
    }

    protected function resolveMethodForColumn(AnonymousSiebelColumn $column): ?AnonymizationMethods
    {
        $methodId = $column->pivot->anonymization_method_id ?? null;

        if ($methodId) {
            // Prefer the job-selected anonymization method from the pivot when present.
            // Fall back to the column's global method list when no pivot match is loaded.
            $resolved = $column->anonymizationMethods->firstWhere('id', $methodId);

            if ($resolved) {
                return $resolved;
            }

            return AnonymizationMethods::withTrashed()->find($methodId);
        }

        return $column->anonymizationMethods->first();
    }

    protected function topologicallySortColumns(Collection $columns): Collection
    {
        // @var Collection<int, AnonymousSiebelColumn> $columns
        $columns = $columns->keyBy(fn(AnonymousSiebelColumn $column) => $column->id);
        $graph = [];

        foreach ($columns as $column) {
            $graph[$column->id] = [];
        }

        foreach ($columns as $column) {
            $parents = $column->getRelationValue('parentColumns') ?? collect();

            foreach ($parents as $parent) {
                if ($columns->has($parent->id)) {
                    $graph[$parent->id][] = $column->id;
                }
            }
        }

        $visited = [];
        $temp = [];
        $order = [];
        $hasCycle = false;

        $visit = function (int $nodeId) use (&$visit, &$graph, &$visited, &$temp, &$order, &$hasCycle) {
            if ($hasCycle) {
                return;
            }

            if (isset($visited[$nodeId])) {
                return;
            }

            if (isset($temp[$nodeId])) {
                $hasCycle = true;
                return;
            }

            $temp[$nodeId] = true;

            foreach ($graph[$nodeId] as $neighbor) {
                $visit($neighbor);
                if ($hasCycle) {
                    return;
                }
            }

            unset($temp[$nodeId]);
            $visited[$nodeId] = true;
            $order[] = $nodeId;
        };

        foreach (array_keys($graph) as $nodeId) {
            if (! isset($visited[$nodeId])) {
                $visit($nodeId);
            }
        }

        if ($hasCycle) {
            return $columns
                ->values()
                ->sortBy(fn(AnonymousSiebelColumn $column) => $this->describeColumn($column))
                ->values();
        }

        $orderedIds = array_reverse($order);

        return collect($orderedIds)
            ->map(fn(int $id) => $columns->get($id))
            ->filter()
            ->values();
    }
}
