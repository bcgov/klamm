<?php

namespace App\Services\Anonymizer;

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
            'columns.anonymizationMethods',
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
                'anonymizationMethods',
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
                'anonymizationMethods',
                'table.schema.database',
                'parentColumns.table.schema.database',
            ]);
        }

        $ordered = $this->topologicallySortColumns($columns);

        if ($ordered->isEmpty()) {
            return '';
        }

        $lines = $this->buildHeaderLines($this->jobHeaderMetadata($job));

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
                $lines[] = $sqlBlock;
            }

            $lines[] = '';
        }

        return trim(implode(PHP_EOL, $lines));
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
