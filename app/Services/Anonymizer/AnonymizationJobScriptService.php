<?php

namespace App\Services\Anonymizer;

use App\Enums\SeedContractMode;
use App\Models\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\AnonymizationMethods;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnonymizationJobScriptService
{
    public function buildForJob(AnonymizationJobs $job): string
    {
        $job->loadMissing([
            'columns.anonymizationMethods.packages',
            'columns.table.schema.database',
            'columns.parentColumns.table.schema.database',
        ]);

        $columns = $job->columns ?? collect();
        $script = $this->buildFromColumns($columns, $job);

        if (trim($script) === '') {
            return '-- No anonymization SQL generated: no columns or anonymization methods configured for this job.';
        }

        return $script;
    }

    public function buildForColumnIds(array $columnIds): string
    {
        $columnIds = array_filter(array_map('intval', $columnIds));

        if ($columnIds === []) {
            return '';
        }

        $columns = AnonymousSiebelColumn::query()
            ->with([
                'anonymizationMethods.packages',
                'table.schema.database',
                'parentColumns.table.schema.database',
            ])
            ->whereIn('id', $columnIds)
            ->get();

        return $this->buildFromColumns($columns);
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

        $lines = $this->buildHeaderLines($this->jobHeaderMetadata($job));

        $contractReview = $this->validateSeedContracts($ordered);

        if ($contractReview['errors']->isNotEmpty() || $contractReview['warnings']->isNotEmpty()) {
            $lines = array_merge($lines, $this->renderContractReview($contractReview));

            if ($contractReview['errors']->isNotEmpty()) {
                $lines[] = '-- SQL generation halted due to blocking seed contract violations.';
                return trim(implode(PHP_EOL, $lines));
            }
        }
        $packages = $this->collectPackagesFromColumns($ordered);

        if ($packages->isNotEmpty()) {
            $lines[] = str_repeat('=', 70);
            $lines[] = '-- Package Dependencies';
            $lines[] = '-- Ordered for deterministic exports';
            $lines[] = str_repeat('=', 70);
            $lines[] = '';

            foreach ($packages as $package) {
                $lines[] = str_repeat('-', 70);
                $lines[] = '-- Package: ' . $package->display_label;

                if ($package->summary) {
                    $lines[] = '-- ' . trim($package->summary);
                }

                foreach ($package->compiledSqlBlocks() as $block) {
                    $lines[] = trim($block);
                    $lines[] = '';
                }
            }

            $lines[] = str_repeat('=', 70);
            $lines[] = '';
        }

        $groups = [];
        $groupOrder = [];

        /** @var AnonymousSiebelColumn $column */
        foreach ($ordered as $column) {
            $method = $this->resolveMethodForColumn($column);
            $key = $method?->id ?? 'none';

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'method' => $method,
                    'columns' => [],
                ];
                $groupOrder[] = $key;
            }

            $groups[$key]['columns'][] = $column;
        }

        foreach ($groupOrder as $key) {
            $group = $groups[$key];
            /** @var AnonymizationMethods|null $method */
            $method = $group['method'];
            /** @var Collection<int, AnonymousSiebelColumn> $columnsInGroup */
            $columnsInGroup = collect($group['columns']);

            $lines[] = str_repeat('-', 70);
            $lines[] = $this->methodHeading($method);
            $lines[] = $this->columnsListing($columnsInGroup, $ordered->pluck('id')->all());

            $sqlBlock = trim((string) ($method?->sql_block ?? ''));

            if ($sqlBlock === '') {
                $lines[] = '-- No SQL block defined for this method.';
            } else {
                foreach ($this->renderSqlBlocksForColumns($sqlBlock, $columnsInGroup) as $renderedBlock) {
                    $lines[] = $renderedBlock;
                }
            }

            $lines[] = '';
        }

        return trim(implode(PHP_EOL, $lines));
    }

    protected function validateSeedContracts(Collection $columns): array
    {
        $selected = $columns->keyBy('id');
        $errors = collect();
        $warnings = collect();

        /** @var AnonymousSiebelColumn $column */
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $mode = $column->seed_contract_mode;
            $columnLabel = $this->describeColumn($column);

            if (! $method) {
                $message = $columnLabel . ': No anonymization method is attached; seed contract cannot be evaluated.';

                if ($column->seed_contract_mode && $column->seed_contract_mode !== SeedContractMode::NONE) {
                    $errors->push($message . ' Assign a method that honors the declared seed role.');
                } else {
                    $warnings->push($message);
                }

                continue;
            }

            if ($mode === SeedContractMode::SOURCE && ! $method->emits_seed) {
                $errors->push($columnLabel . ': Declared as a seed source but method ' . $method->name . ' is not marked as emitting a seed.');
            }

            if ($mode === SeedContractMode::CONSUMER && ! $method->requires_seed) {
                $errors->push($columnLabel . ': Declared as a seed consumer but method ' . $method->name . ' does not require a seed.');
            }

            if ($mode === SeedContractMode::COMPOSITE && ! $method->supports_composite_seed) {
                $errors->push($columnLabel . ': Declared as a composite seed but method ' . $method->name . ' is not composite-ready.');
            }

            if (($mode === null || $mode === SeedContractMode::NONE) && $method->requires_seed) {
                $errors->push($columnLabel . ': Method ' . $method->name . ' requires a seed but the column is not marked as a consumer or composite.');
            }

            if (($mode === null || $mode === SeedContractMode::NONE) && $method->emits_seed) {
                $warnings->push($columnLabel . ': Method ' . $method->name . ' emits a seed but the column is not marked as a seed source.');
            }

            if (! $this->columnRequiresSeed($column, $method)) {
                continue;
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();

            if ($parents->isEmpty()) {
                $errors->push($columnLabel . ': Seed consumer columns must declare at least one parent dependency.');
                continue;
            }

            foreach ($parents as $parentRelation) {
                $mandatory = $parentRelation->pivot->is_seed_mandatory ?? true;
                $bundleDescriptor = $this->describeSeedBundle($parentRelation);
                $parentLabel = $this->describeColumn($parentRelation);
                $parentMode = $parentRelation->seed_contract_mode;
                $selectedParent = $selected->get($parentRelation->id);

                if (! $selectedParent) {
                    if ($mandatory && $parentMode !== SeedContractMode::EXTERNAL) {
                        $errors->push($columnLabel . ': Requires parent ' . $parentLabel . $bundleDescriptor . ' but it is not included in this job.');
                    } else {
                        $warnings->push($columnLabel . ': Parent ' . $parentLabel . $bundleDescriptor . ' is not included; verify the external seed handshake.');
                    }
                    continue;
                }

                $parentMethod = $this->resolveMethodForColumn($selectedParent);

                if (! $this->columnProvidesSeed($selectedParent, $parentMethod)) {
                    $errors->push($columnLabel . ': Parent ' . $parentLabel . $bundleDescriptor . ' does not emit a seed but is referenced as a dependency.');
                }
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    protected function renderContractReview(array $review): array
    {
        $lines = [
            str_repeat('=', 70),
            '-- Seed Contract Review',
            str_repeat('-', 70),
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

        $lines[] = str_repeat('=', 70);
        $lines[] = '';

        return $lines;
    }

    protected function columnProvidesSeed(AnonymousSiebelColumn $column, ?AnonymizationMethods $method): bool
    {
        $mode = $column->seed_contract_mode;

        if ($mode === SeedContractMode::SOURCE || $mode === SeedContractMode::COMPOSITE || $mode === SeedContractMode::EXTERNAL) {
            return true;
        }

        return (bool) ($method?->emits_seed);
    }

    protected function columnRequiresSeed(AnonymousSiebelColumn $column, ?AnonymizationMethods $method): bool
    {
        $mode = $column->seed_contract_mode;

        if ($mode === SeedContractMode::CONSUMER || $mode === SeedContractMode::COMPOSITE) {
            return true;
        }

        return (bool) ($method?->requires_seed);
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

    protected function renderSqlBlocksForColumns(string $template, Collection $columns): array
    {
        $output = [];

        /** @var AnonymousSiebelColumn $column */
        foreach ($columns as $column) {
            $rendered = $this->applyPlaceholders($template, $column);

            $output[] = '-- Applies to: ' . $this->describeColumn($column);
            $output[] = $rendered;
            $output[] = '';
        }

        return $output === [] ? [$template] : $output;
    }

    protected function applyPlaceholders(string $template, AnonymousSiebelColumn $column): string
    {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');
        $database = $schema?->getRelationValue('database');

        $qualifiedTable = collect([
            $database?->database_name,
            $schema?->schema_name,
            $table?->table_name,
        ])->filter()->implode('.');

        $replacements = [
            '{{TABLE}}' => $qualifiedTable ?: ($table?->table_name ?? '{{TABLE}}'),
            '{{TABLE_NAME}}' => $table?->table_name ?? '',
            '{{SCHEMA}}' => $schema?->schema_name ?? '',
            '{{DATABASE}}' => $database?->database_name ?? '',
            '{{COLUMN}}' => $column->column_name ?? '',
            '{{ALIAS}}' => 'tgt',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
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

    protected function buildHeaderLines(array $jobMeta): array
    {
        $lines = [
            str_repeat('=', 70),
            '-- ' . $jobMeta['title'],
            '-- Generated: ' . now()->toDateTimeString(),
        ];

        if ($jobMeta['job_type']) {
            $lines[] = '-- Job Type: ' . $jobMeta['job_type'];
        }

        if ($jobMeta['output']) {
            $lines[] = '-- Output Format: ' . $jobMeta['output'];
        }

        $lines[] = str_repeat('=', 70);
        $lines[] = '';

        return $lines;
    }

    protected function methodHeading(?AnonymizationMethods $method): string
    {
        if (! $method) {
            return '-- Method: (not assigned)';
        }

        $label = '-- Method: ' . $method->name;

        if ($method->category) {
            $label .= ' [' . $method->category . ']';
        }

        return $label;
    }

    protected function columnsListing(Collection $columns, array $orderedIds): string
    {
        $idPosition = array_flip($orderedIds);

        /** @var Collection<int, AnonymousSiebelColumn> $columns */
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
            return $column->anonymizationMethods->firstWhere('id', $methodId)
                ?: $column->anonymizationMethods->first();
        }

        return $column->anonymizationMethods->first();
    }

    protected function topologicallySortColumns(Collection $columns): Collection
    {
        /** @var Collection<int, AnonymousSiebelColumn> $columns */
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
