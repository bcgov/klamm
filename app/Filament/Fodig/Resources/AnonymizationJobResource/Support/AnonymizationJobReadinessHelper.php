<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Support;

use App\Models\Anonymizer\AnonymizationJobs;
use App\Services\Anonymizer\AnonymizationJobReadinessService;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

final class AnonymizationJobReadinessHelper
{
    private const ENTIRE_SCOPE_MODE = 'all';

    public static function isEntireScopeSelection(?string $mode, mixed $columns, array $scope): bool
    {
        if ($mode === self::ENTIRE_SCOPE_MODE) {
            return true;
        }

        $hasScope = ! empty($scope['databases'] ?? []) || ! empty($scope['schemas'] ?? []) || ! empty($scope['tables'] ?? []);
        $hasColumns = self::sanitizeIds($columns) !== [];

        // default “entire scope”.
        return $mode === null && ! $hasColumns && $hasScope;
    }

    // Build a readiness report from form
    public static function reportForSelection(?string $mode, mixed $columns, array $scope, ?int $jobId = null): array
    {
        $service = app(AnonymizationJobReadinessService::class);

        $scope = self::sanitizeScope($scope);

        if (self::isEntireScopeSelection($mode, $columns, $scope)) {
            return $service->reportForScope($scope, $jobId);
        }

        return $service->reportForColumnIds(self::sanitizeIds($columns), $jobId);
    }

    // Build a readiness report for a persisted job
    public static function reportForJob(AnonymizationJobs $job): array
    {
        return app(AnonymizationJobReadinessService::class)->reportForJob((int) $job->getKey());
    }

    public static function summary(array $report): string
    {
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];

        if (isset($summary['message'])) {
            return (string) $summary['message'];
        }

        $blocking = (int) ($summary['blocking_total'] ?? 0);
        $warnings = (int) ($summary['warnings_total'] ?? 0);
        $urgent = (int) ($summary['urgent_tickets'] ?? 0);

        $parts = [];

        if (($summary['mode'] ?? '') === 'entire_scope') {
            $total = (int) ($summary['columns_actionable_total'] ?? 0);
            $parts[] = 'Actionable columns in scope: ' . number_format($total);

            $missing = (int) ($summary['missing_method_required_total'] ?? 0);
            $review = (int) ($summary['needs_review_total'] ?? 0);

            if ($missing > 0) {
                $parts[] = 'Required missing method: ' . number_format($missing);
            }

            if ($review > 0) {
                $parts[] = 'Needs review: ' . number_format($review);
            }
        } else {
            $selected = (int) ($summary['columns_selected_total'] ?? ($summary['columns_selected'] ?? 0));
            $parts[] = 'Selected columns: ' . number_format($selected);
        }

        $parts[] = 'Blocking: ' . number_format($blocking);
        $parts[] = 'Warnings: ' . number_format($warnings);
        $parts[] = 'Urgent alerts: ' . number_format($urgent);

        if (! empty($summary['note'])) {
            $parts[] = (string) $summary['note'];
        }

        return implode(' • ', $parts);
    }

    // Render readiness issues as a small HTML list for Filament placeholders/infolists.
    public static function issuesHtml(array $report, string $emptyMessage): HtmlString
    {
        $issues = $report['issues'] ?? [];

        if (! is_array($issues) || $issues === []) {
            $message = e($emptyMessage);
            return new HtmlString("<div class=\"text-sm text-slate-500\">{$message}</div>");
        }

        $items = [];
        foreach ($issues as $issue) {
            if (! is_array($issue)) {
                continue;
            }

            $severity = strtoupper((string) ($issue['severity'] ?? ''));
            $title = e((string) ($issue['title'] ?? 'Issue'));
            $scope = e((string) ($issue['scope_name'] ?? ''));
            $details = e((string) ($issue['details'] ?? ''));
            $url = $issue['url'] ?? null;

            $line = "<div class=\"font-medium\">[{$severity}] {$title}</div>";
            if ($scope !== '') {
                $line .= "<div class=\"text-xs text-slate-500\">{$scope}</div>";
            }
            if ($details !== '') {
                $line .= "<div class=\"text-xs text-slate-600\">{$details}</div>";
            }

            if (is_string($url) && $url !== '') {
                $safeUrl = e($url);
                $line .= "<div class=\"mt-1 text-xs\"><a class=\"text-primary-600 hover:underline\" href=\"{$safeUrl}\">Open</a></div>";
            }

            $items[] = "<li class=\"rounded-lg border border-slate-200 bg-white p-3\">{$line}</li>";
        }

        return new HtmlString('<ul class="space-y-2">' . implode('', $items) . '</ul>');
    }

    private static function sanitizeScope(array $scope): array
    {
        return [
            'databases' => self::sanitizeIds($scope['databases'] ?? []),
            'schemas' => self::sanitizeIds($scope['schemas'] ?? []),
            'tables' => self::sanitizeIds($scope['tables'] ?? []),
        ];
    }

    private static function sanitizeIds(mixed $value): array
    {
        $ids = array_map('intval', Arr::wrap($value));

        return array_values(array_filter($ids, fn(int $id) => $id > 0));
    }
}
