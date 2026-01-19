<?php

namespace App\Services\Anonymizer;

use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use App\Filament\Fodig\Resources\ChangeTicketResource;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\ChangeTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnonymizationJobReadinessService
{
    public function reportForJob(int $jobId, int $sampleIssues = 25): array
    {
        if ($jobId <= 0) {
            return $this->emptyReport('Invalid job.');
        }

        $totalSelected = (int) DB::table('anonymization_job_columns as ajc')
            ->where('ajc.job_id', $jobId)
            ->distinct('ajc.column_id')
            ->count('ajc.column_id');

        if ($totalSelected === 0) {
            return $this->emptyReport('No columns selected for this job.');
        }

        $base = DB::table('anonymization_job_columns as ajc')
            ->join('anonymous_siebel_columns as c', 'c.id', '=', 'ajc.column_id')
            ->join('anonymous_siebel_tables as tables', 'tables.id', '=', 'c.table_id')
            ->join('anonymous_siebel_schemas as schemas', 'schemas.id', '=', 'tables.schema_id')
            ->where('ajc.job_id', $jobId);

        $missingMethodRequired = (int) (clone $base)
            ->where('c.anonymization_required', true)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('anonymization_method_column as amc')
                    ->whereColumn('amc.column_id', 'c.id');
            })
            ->distinct('c.id')
            ->count('c.id');

        $needsReview = (int) (clone $base)
            ->where('c.anonymization_required', true)
            ->where(function ($q) {
                $q->whereNull('c.anonymization_requirement_reviewed')
                    ->orWhere('c.anonymization_requirement_reviewed', false);
            })
            ->distinct('c.id')
            ->count('c.id');

        $sampleIds = (clone $base)
            ->select([
                'c.id',
                'c.anonymization_required',
                'schemas.schema_name',
                'tables.table_name',
                'c.column_name',
            ])
            ->orderByDesc('c.anonymization_required')
            ->orderBy('schemas.schema_name')
            ->orderBy('tables.table_name')
            ->orderBy('c.column_name')
            ->limit(max(1, $sampleIssues))
            ->get()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        $report = $this->reportForColumnIds($sampleIds, $jobId, $sampleIssues);

        $report['summary'] = array_merge($report['summary'] ?? [], [
            'mode' => 'saved_job',
            'job_id' => $jobId,
            'columns_selected_total' => $totalSelected,
            'missing_method_required_total' => $missingMethodRequired,
            'needs_review_total' => $needsReview,
            'note' => $totalSelected > $sampleIssues
                ? 'Issue list is sampled; counts reflect the full job selection.'
                : 'Counts reflect the full job selection.',
        ]);

        $report['markdown'] = $this->renderMarkdown($report['summary'], $report['issues'] ?? []);

        return $report;
    }

    public function reportForColumnIds(array $columnIds, ?int $jobId = null, int $limitIssues = 50): array
    {
        $columnIds = array_values(array_filter(array_map('intval', Arr::wrap($columnIds)), fn(int $id) => $id > 0));

        if ($columnIds === []) {
            return $this->emptyReport('No columns selected.');
        }

        if (count($columnIds) > 1500) {
            if ($jobId !== null) {
                $base = DB::table('anonymization_job_columns as ajc')
                    ->join('anonymous_siebel_columns as c', 'c.id', '=', 'ajc.column_id')
                    ->join('anonymous_siebel_tables as tables', 'tables.id', '=', 'c.table_id')
                    ->join('anonymous_siebel_schemas as schemas', 'schemas.id', '=', 'tables.schema_id')
                    ->where('ajc.job_id', $jobId);

                $missingMethodRequired = (int) (clone $base)
                    ->where('c.anonymization_required', true)
                    ->whereNotExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('anonymization_method_column as amc')
                            ->whereColumn('amc.column_id', 'c.id');
                    })
                    ->distinct('c.id')
                    ->count('c.id');

                $needsReview = (int) (clone $base)
                    ->where('c.anonymization_required', true)
                    ->where(function ($q) {
                        $q->whereNull('c.anonymization_requirement_reviewed')
                            ->orWhere('c.anonymization_requirement_reviewed', false);
                    })
                    ->distinct('c.id')
                    ->count('c.id');

                $sampleIds = (clone $base)
                    ->select([
                        'c.id',
                        'c.anonymization_required',
                        'schemas.schema_name',
                        'tables.table_name',
                        'c.column_name',
                    ])
                    ->orderByDesc('c.anonymization_required')
                    ->orderBy('schemas.schema_name')
                    ->orderBy('tables.table_name')
                    ->orderBy('c.column_name')
                    ->limit(50)
                    ->get()
                    ->pluck('id')
                    ->map(fn($id) => (int) $id)
                    ->all();

                $sampleReport = $this->reportForColumnIds($sampleIds, $jobId, 25);

                $sampleReport['summary'] = array_merge($sampleReport['summary'], [
                    'mode' => 'explicit_columns',
                    'columns_selected' => count($columnIds),
                    'missing_method_required_total' => $missingMethodRequired,
                    'needs_review_total' => $needsReview,
                    'note' => 'Issue list is sampled due to large selection size.',
                ]);

                $sampleReport['markdown'] = $this->renderMarkdown($sampleReport['summary'], $sampleReport['issues']);

                return $sampleReport;
            }

            $missingMethodRequired = AnonymousSiebelColumn::query()
                ->whereIn('id', $columnIds)
                ->where('anonymization_required', true)
                ->whereDoesntHave('anonymizationMethods')
                ->count();

            $needsReview = AnonymousSiebelColumn::query()
                ->whereIn('id', $columnIds)
                ->where('anonymization_required', true)
                ->where(function (Builder $q) {
                    $q->whereNull('anonymization_requirement_reviewed')
                        ->orWhere('anonymization_requirement_reviewed', false);
                })
                ->count();

            $sampleIds = array_slice($columnIds, 0, 50);
            $sampleReport = $this->reportForColumnIds($sampleIds, $jobId, 25);

            $sampleReport['summary'] = array_merge($sampleReport['summary'], [
                'mode' => 'explicit_columns',
                'columns_selected' => count($columnIds),
                'missing_method_required_total' => $missingMethodRequired,
                'needs_review_total' => $needsReview,
                'note' => 'Issue list is sampled due to large selection size.',
            ]);

            $sampleReport['markdown'] = $this->renderMarkdown($sampleReport['summary'], $sampleReport['issues']);

            return $sampleReport;
        }

        $columns = AnonymousSiebelColumn::query()
            ->with([
                'table.schema.database',
                'parentColumns.table.schema.database',
                'anonymizationMethods.packages',
            ])
            ->withCount('anonymizationMethods')
            ->whereIn('id', $columnIds)
            ->get();

        if ($columns->isEmpty()) {
            return $this->emptyReport('No columns found for the current selection.');
        }

        $pivotMethods = $jobId
            ? $this->pivotMethodMap($jobId, $columns->pluck('id')->all())
            : collect();

        return $this->buildReportFromColumns($columns, $pivotMethods, $limitIssues);
    }

    public function reportForScope(array $scope, ?int $jobId = null, int $sampleIssues = 25): array
    {
        $scope = [
            'databases' => array_values(array_filter(array_map('intval', Arr::wrap($scope['databases'] ?? [])), fn(int $id) => $id > 0)),
            'schemas' => array_values(array_filter(array_map('intval', Arr::wrap($scope['schemas'] ?? [])), fn(int $id) => $id > 0)),
            'tables' => array_values(array_filter(array_map('intval', Arr::wrap($scope['tables'] ?? [])), fn(int $id) => $id > 0)),
        ];

        $hasScope = ($scope['tables'] !== []) || ($scope['schemas'] !== []) || ($scope['databases'] !== []);
        if (! $hasScope) {
            return $this->emptyReport('No scope selected.');
        }

        $base = $this->scopedColumnsQuery($scope);

        $totalActionable = (clone $base)
            ->where(function (Builder $q) {
                $q->where('anonymous_siebel_columns.anonymization_required', true)
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('anonymization_method_column as amc')
                            ->whereColumn('amc.column_id', 'anonymous_siebel_columns.id');
                    });
            })
            ->count('anonymous_siebel_columns.id');

        $missingMethodRequired = (clone $base)
            ->where('anonymous_siebel_columns.anonymization_required', true)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('anonymization_method_column as amc')
                    ->whereColumn('amc.column_id', 'anonymous_siebel_columns.id');
            })
            ->count('anonymous_siebel_columns.id');

        $needsReview = (clone $base)
            ->where('anonymous_siebel_columns.anonymization_required', true)
            ->where(function (Builder $q) {
                $q->whereNull('anonymous_siebel_columns.anonymization_requirement_reviewed')
                    ->orWhere('anonymous_siebel_columns.anonymization_requirement_reviewed', false);
            })
            ->count('anonymous_siebel_columns.id');

        $sampleRows = (clone $base)
            ->select([
                'anonymous_siebel_columns.id',
                'anonymous_siebel_columns.anonymization_required',
                'schemas.schema_name',
                'tables.table_name',
                'anonymous_siebel_columns.column_name',
            ])
            ->where(function (Builder $q) {
                $q->where('anonymous_siebel_columns.anonymization_required', true)
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('anonymization_method_column as amc')
                            ->whereColumn('amc.column_id', 'anonymous_siebel_columns.id');
                    });
            })
            ->orderByDesc('anonymous_siebel_columns.anonymization_required')
            ->orderBy('schemas.schema_name')
            ->orderBy('tables.table_name')
            ->orderBy('anonymous_siebel_columns.column_name')
            ->limit(max(1, $sampleIssues))
            ->get();

        $sampleIds = $sampleRows
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        $report = $this->reportForColumnIds($sampleIds, $jobId, $sampleIssues);

        // Override summary to reflect full-scope counts.
        $report['summary'] = array_merge($report['summary'], [
            'mode' => 'entire_scope',
            'columns_actionable_total' => $totalActionable,
            'missing_method_required_total' => $missingMethodRequired,
            'needs_review_total' => $needsReview,
            'note' => 'Issue list is sampled; counts reflect the full scope.',
        ]);

        $report['markdown'] = $this->renderMarkdown($report['summary'], $report['issues']);

        return $report;
    }

    private function buildReportFromColumns(Collection $columns, Collection $pivotMethods, int $limitIssues): array
    {
        $issues = [];

        foreach ($columns as $column) {
            $qualified = $this->qualifiedColumnName($column);
            $url = AnonymousSiebelColumnResource::getUrl('edit', ['record' => $column]);

            $methodCount = (int) ($column->anonymization_methods_count ?? 0);
            $isRequired = (bool) ($column->anonymization_required ?? false);
            $reviewed = (bool) ($column->anonymization_requirement_reviewed ?? false);

            if ($isRequired && ! $reviewed) {
                $issues[] = $this->issue(
                    severity: 'blocking',
                    type: 'needs_review',
                    scopeType: 'column',
                    scopeName: $qualified,
                    title: 'Anonymization required but not reviewed',
                    details: 'This column is marked as requiring anonymization, but has not been reviewed/confirmed.',
                    url: $url,
                );
            }

            if ($isRequired && $methodCount === 0) {
                $issues[] = $this->issue(
                    severity: 'blocking',
                    type: 'missing_method',
                    scopeType: 'column',
                    scopeName: $qualified,
                    title: 'Required column has no anonymization method',
                    details: 'Job SQL will not include a masking statement for this required column until at least one method is attached.',
                    url: $url,
                );
            }

            $pivotMethodId = $pivotMethods->get($column->id);
            if ($pivotMethodId === null && $methodCount > 1) {
                $methodNames = $column->anonymizationMethods->pluck('name')->filter()->take(5)->values()->all();
                $issues[] = $this->issue(
                    severity: 'warning',
                    type: 'method_ambiguous',
                    scopeType: 'column',
                    scopeName: $qualified,
                    title: 'Multiple methods attached; none selected for this job',
                    details: $methodNames !== []
                        ? ('Methods: ' . implode(', ', $methodNames) . '. The generator will choose the first method unless a specific method is assigned.')
                        : 'Multiple methods are attached to this column. The generator will choose the first method unless a specific method is assigned.',
                    url: $url,
                );
            }

            // Dependency coverage: warn when parent deps exist but are not included in the job selection.
            $parents = $column->parentColumns ?? collect();
            if ($parents->isNotEmpty()) {
                $selectedLookup = $columns->keyBy('id');
                $missingParents = $parents
                    ->filter(fn(AnonymousSiebelColumn $p) => ! $selectedLookup->has($p->id))
                    ->take(5)
                    ->map(fn(AnonymousSiebelColumn $p) => $this->qualifiedColumnName($p))
                    ->values()
                    ->all();

                if ($missingParents !== []) {
                    $issues[] = $this->issue(
                        severity: 'warning',
                        type: 'missing_dependencies',
                        scopeType: 'column',
                        scopeName: $qualified,
                        title: 'Column dependencies not included in job',
                        details: 'This column depends on: ' . implode(', ', $missingParents) . '. Consider adding dependencies to preserve deterministic ordering/seed flow.',
                        url: $url,
                    );
                }
            }
        }

        $urgentTickets = $this->urgentTicketsForQualifiedNames(
            $columns->map(fn(AnonymousSiebelColumn $c) => $this->qualifiedColumnName($c))->all(),
            $columns->map(fn(AnonymousSiebelColumn $c) => $this->qualifiedTableName($c))->filter()->unique()->values()->all(),
        );

        foreach ($urgentTickets as $ticket) {
            $issues[] = $this->issue(
                severity: 'blocking',
                type: 'urgent_change_ticket',
                scopeType: (string) ($ticket['scope_type'] ?? 'unknown'),
                scopeName: (string) ($ticket['scope_name'] ?? 'unknown'),
                title: (string) ($ticket['title'] ?? 'URGENT change'),
                details: (string) ($ticket['impact_summary'] ?? 'Breaking-change alert detected for this job scope.'),
                url: $ticket['url'] ?? null,
            );
        }

        $issues = collect($issues)
            ->sortBy(fn(array $i) => ($i['severity'] ?? '') === 'blocking' ? 0 : 1)
            ->values()
            ->all();

        if (count($issues) > $limitIssues) {
            $issues = array_slice($issues, 0, $limitIssues);
        }

        $blocking = collect($issues)->where('severity', 'blocking')->count();
        $warnings = collect($issues)->where('severity', 'warning')->count();

        $summary = [
            'mode' => 'explicit_columns',
            'columns_selected' => $columns->count(),
            'issues_total' => count($issues),
            'blocking_total' => $blocking,
            'warnings_total' => $warnings,
            'urgent_tickets' => count($urgentTickets),
        ];

        return [
            'summary' => $summary,
            'issues' => $issues,
            'markdown' => $this->renderMarkdown($summary, $issues),
        ];
    }

    private function emptyReport(string $message): array
    {
        return [
            'summary' => [
                'mode' => 'none',
                'message' => $message,
            ],
            'issues' => [],
            'markdown' => "# Anonymization Job Readiness Report\n\n{$message}\n",
        ];
    }

    private function issue(string $severity, string $type, string $scopeType, string $scopeName, string $title, string $details, ?string $url): array
    {
        return [
            'severity' => $severity,
            'type' => $type,
            'scope_type' => $scopeType,
            'scope_name' => $scopeName,
            'title' => $title,
            'details' => $details,
            'url' => $url,
        ];
    }

    private function renderMarkdown(array $summary, array $issues): string
    {
        $lines = [];

        $lines[] = '# Anonymization Job Readiness Report';
        $lines[] = '';

        if (isset($summary['message'])) {
            $lines[] = $summary['message'];
            $lines[] = '';
            return implode("\n", $lines);
        }

        $lines[] = '## Summary';
        foreach ($summary as $k => $v) {
            $lines[] = '- ' . Str::headline((string) $k) . ': ' . (is_scalar($v) ? (string) $v : json_encode($v));
        }
        $lines[] = '';

        $lines[] = '## Issues';
        if ($issues === []) {
            $lines[] = '- None detected.';
            $lines[] = '';
            return implode("\n", $lines);
        }

        foreach ($issues as $issue) {
            $lines[] = '- [' . strtoupper((string) ($issue['severity'] ?? '')) . '] ' . ($issue['title'] ?? 'Issue') . ' (' . ($issue['scope_name'] ?? '') . ')';
            $details = trim((string) ($issue['details'] ?? ''));
            if ($details !== '') {
                $lines[] = '  - ' . str_replace("\n", ' ', $details);
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function pivotMethodMap(int $jobId, array $columnIds): Collection
    {
        $columnIds = array_values(array_filter(array_map('intval', Arr::wrap($columnIds)), fn(int $id) => $id > 0));

        if ($columnIds === []) {
            return collect();
        }

        return collect(
            DB::table('anonymization_job_columns')
                ->where('job_id', $jobId)
                ->whereIn('column_id', $columnIds)
                ->pluck('anonymization_method_id', 'column_id')
                ->all()
        )->map(fn($v) => $v !== null ? (int) $v : null);
    }

    private function urgentTicketsForQualifiedNames(array $qualifiedColumns, array $qualifiedTables): array
    {
        $qualifiedColumns = array_values(array_filter(array_map('strval', Arr::wrap($qualifiedColumns)), fn(string $s) => $s !== ''));
        $qualifiedTables = array_values(array_filter(array_map('strval', Arr::wrap($qualifiedTables)), fn(string $s) => $s !== ''));

        if ($qualifiedColumns === [] && $qualifiedTables === []) {
            return [];
        }

        return ChangeTicket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->where('title', 'like', 'URGENT:%')
            ->where(function (Builder $q) use ($qualifiedColumns, $qualifiedTables) {
                if ($qualifiedColumns !== []) {
                    $q->orWhere(function (Builder $q2) use ($qualifiedColumns) {
                        $q2->where('scope_type', 'column')
                            ->whereIn('scope_name', $qualifiedColumns);
                    });
                }

                if ($qualifiedTables !== []) {
                    $q->orWhere(function (Builder $q2) use ($qualifiedTables) {
                        $q2->where('scope_type', 'table')
                            ->whereIn('scope_name', $qualifiedTables);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->limit(25)
            ->get(['id', 'title', 'scope_type', 'scope_name', 'impact_summary'])
            ->map(fn(ChangeTicket $t) => [
                'id' => $t->getKey(),
                'title' => $t->title,
                'scope_type' => $t->scope_type,
                'scope_name' => $t->scope_name,
                'impact_summary' => $t->impact_summary,
                'url' => ChangeTicketResource::getUrl('edit', ['record' => $t]),
            ])
            ->all();
    }

    private function scopedColumnsQuery(array $scope): Builder
    {
        $query = AnonymousSiebelColumn::query()
            ->select('anonymous_siebel_columns.id')
            ->join('anonymous_siebel_tables as tables', 'tables.id', '=', 'anonymous_siebel_columns.table_id')
            ->join('anonymous_siebel_schemas as schemas', 'schemas.id', '=', 'tables.schema_id')
            ->join('anonymous_siebel_databases as databases', 'databases.id', '=', 'schemas.database_id');

        if (($scope['tables'] ?? []) !== []) {
            $query->whereIn('tables.id', $scope['tables']);
        } elseif (($scope['schemas'] ?? []) !== []) {
            $query->whereIn('schemas.id', $scope['schemas']);
        } elseif (($scope['databases'] ?? []) !== []) {
            $query->whereIn('databases.id', $scope['databases']);
        }

        return $query->distinct();
    }

    private function qualifiedColumnName(AnonymousSiebelColumn $column): string
    {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');
        $database = $schema?->getRelationValue('database');

        $segments = array_filter([
            $schema?->schema_name,
            $table?->table_name,
            $column->column_name,
        ]);

        // Prefer schema.table.column to match ChangeTicket conventions.
        $qualified = implode('.', $segments);
        if ($qualified === '' && $database) {
            $qualified = $database->database_name . '.' . ($column->column_name ?? '');
        }

        return $qualified;
    }

    private function qualifiedTableName(AnonymousSiebelColumn $column): ?string
    {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');

        $segments = array_filter([
            $schema?->schema_name,
            $table?->table_name,
        ]);

        $qualified = implode('.', $segments);

        return $qualified !== '' ? $qualified : null;
    }
}
