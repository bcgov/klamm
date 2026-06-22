<?php

namespace App\Services\Anonymizer;

use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ExportProgressNotificationService
{
    private const NOTIFICATION_KIND = 'export-progress';

    public const STAGE_QUEUED = 'queued';
    public const STAGE_PREPARING = 'preparing';
    public const STAGE_EXPORTING = 'exporting';
    public const STAGE_COMPLETED = 'completed';

    private const NON_COMPLETED_STAGES = [
        self::STAGE_QUEUED,
        self::STAGE_PREPARING,
        self::STAGE_EXPORTING,
    ];

    public function syncForExportId(int $exportId, ?string $stage = null): void
    {
        // Load export with user in one query to avoid  extra relation lookup later.
        $export = Export::query()->with('user')->find($exportId);

        if (! $export) {
            return;
        }

        $this->syncForExport($export, $stage);
    }

    public function syncForExport(Export $export, ?string $stage = null): void
    {
        $user = $export->user;

        if (! $user instanceof Authenticatable) {
            return;
        }

        $notification = $this->findExistingNotification($export, $user);
        $currentStage = $stage;

        // If it does not pass a stage, keep the previous stage.
        if (! $currentStage && $notification) {
            $currentStage = $notification->data['stage'] ?? null;
        }

        $data = $this->buildNotificationData($export, $this->normalizeStage($currentStage));

        if ($notification) {
            // Update in-place: rolling notification per job.
            $notification->forceFill([
                'data' => $data,
            ])->save();

            return;
        }

        DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => \Filament\Notifications\DatabaseNotification::class,
            'notifiable_type' => $user::class,
            'notifiable_id' => $user->getAuthIdentifier(),
            'data' => $data,
            // Keep unread so it remains visible in the notifications panel.
            'read_at' => null,
        ]);
    }


    // Derive and normalize state before producing notification.
    private function buildNotificationData(Export $export, ?string $stage = null): array
    {
        $metrics = $this->collectMetrics($export);
        $resolvedStage = $this->resolveStage($metrics, $stage);
        $notification = $this->buildNotificationForStage($export, $resolvedStage, $metrics);

        return array_merge($notification->getDatabaseMessage(), [
            'notification_kind' => self::NOTIFICATION_KIND,
            'export_id' => (int) $export->getKey(),
            'stage' => $resolvedStage,
            'processed_rows' => $metrics['processedRows'],
            'total_rows' => $metrics['totalRows'],
            'successful_rows' => $metrics['successfulRows'],
            'progress_percent' => $metrics['percent'],
        ]);
    }


    private function collectMetrics(Export $export): array
    {
        // Clamp counters to avoid negative values and overflow over total rows.
        $totalRows = max(0, (int) ($export->total_rows ?? 0));
        $processedRows = min($totalRows, max(0, (int) ($export->processed_rows ?? 0)));
        $successfulRows = min($totalRows, max(0, (int) ($export->successful_rows ?? 0)));

        $percent = $totalRows > 0
            ? (int) min(100, floor(($processedRows / $totalRows) * 100))
            : 0;

        // Completion can be explicit (completed_at) or inferred once processed reaches total.
        $isCompleted = filled($export->completed_at) || ($totalRows > 0 && $processedRows >= $totalRows);
        $failedRows = max(0, $totalRows - $successfulRows);

        return [
            'totalRows' => $totalRows,
            'processedRows' => $processedRows,
            'successfulRows' => $successfulRows,
            'percent' => $percent,
            'failedRows' => $failedRows,
            'isCompleted' => $isCompleted,
        ];
    }

    private function resolveStage(array $metrics, ?string $stage): string
    {
        if ($metrics['isCompleted']) {
            return self::STAGE_COMPLETED;
        }

        if ($stage) {
            return $stage;
        }

        return $metrics['processedRows'] > 0
            ? self::STAGE_EXPORTING
            : self::STAGE_QUEUED;
    }

    private function buildNotificationForStage(Export $export, string $stage, array $metrics): Notification
    {
        $notification = Notification::make("export-progress-{$export->getKey()}");

        if (! $metrics['isCompleted']) {
            return match ($stage) {
                self::STAGE_PREPARING => $notification
                    ->title('Export preparing')
                    ->body('Step 1/3: Preparing records and chunks. Row progress will begin shortly.')
                    ->info(),
                self::STAGE_QUEUED => $notification
                    ->title('Export queued')
                    ->body('Step 0/3: Queued and waiting for worker to build job. (This step may take a while.)')
                    ->info(),
                default => $notification
                    ->title('Export in progress')
                    ->body('Step 2/3: Processed ' . $this->formatNumber($metrics['processedRows']) . ' of ' . $this->formatNumber($metrics['totalRows']) . ' rows (' . $metrics['percent'] . '%).')
                    ->info(),
            };
        }

        if ($metrics['failedRows'] === 0) {
            return $notification
                ->title('Export completed')
                ->body('Step 3/3: Completed successfully with ' . $this->formatNumber($metrics['successfulRows']) . ' rows.')
                ->success();
        }

        if ($metrics['failedRows'] < $metrics['totalRows']) {
            return $notification
                ->title('Export completed with failures')
                ->body('Step 3/3: ' . $this->formatNumber($metrics['successfulRows']) . ' rows exported and ' . $this->formatNumber($metrics['failedRows']) . ' failed.')
                ->warning();
        }

        return $notification
            ->title('Export failed')
            ->body('Step 3/3: No rows were exported successfully.')
            ->danger();
    }

    private function normalizeStage(?string $stage): ?string
    {
        // Accept only known stages to prevent accidental/invalid stage values from persisting.
        if (! is_string($stage) || $stage === '') {
            return null;
        }

        if ($stage === self::STAGE_COMPLETED) {
            return $stage;
        }

        return in_array($stage, self::NON_COMPLETED_STAGES, true)
            ? $stage
            : null;
    }

    private function formatNumber(int $number): string
    {
        return Number::format($number);
    }

    private function findExistingNotification(Export $export, Authenticatable $user): ?DatabaseNotification
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->getAuthIdentifier())
            ->where('data->format', 'filament')
            ->where('data->notification_kind', self::NOTIFICATION_KIND)
            ->where('data->export_id', (int) $export->getKey())
            ->latest('created_at')
            ->first();
    }
}
