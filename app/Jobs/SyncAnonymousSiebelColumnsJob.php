<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithAnonymousSiebelSync;
use App\Jobs\Exceptions\AnonymousUploadCancelledException;
use App\Models\Anonymizer\AnonymousUpload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

// Job that stages anonymized Siebel metadata and reconciles canonical tables in bulk.
class SyncAnonymousSiebelColumnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use InteractsWithAnonymousSiebelSync;

    // Allow the queue worker to manage timeouts (set via worker flag) without a hard 10-minute cap here.
    public int $timeout = 0;

    public function __construct(public int $uploadId, public bool $forceRestart = false)
    {
        // Use dedicated queue for long-running anonymization work
        $this->onQueue('anonymization');
    }

    public function handle(): void
    {
        $upload = AnonymousUpload::findOrFail($this->uploadId);

        if ($this->isCancellationRequested($upload)) {
            return;
        }

        $currentPhase = (string) ($upload->run_phase ?? '');

        $persist = function (array $attributes) use ($upload, &$currentPhase): void {
            $this->throwIfCancellationRequested($upload->id, $currentPhase);

            if (array_key_exists('run_phase', $attributes)) {
                $currentPhase = (string) ($attributes['run_phase'] ?? '');
            }

            $this->persistProgress($upload->id, $attributes);
        };

        // Resume only when the prior run failed after staging work we can reuse.
        $runPhase = (string) ($upload->run_phase ?? '');
        $canResume = ! $this->forceRestart
            && $upload->status === 'failed'
            && in_array($runPhase, ['staged', 'metadata_reconciled', 'columns_upserted'], true);

        $runAt = CarbonImmutable::now();
        $totalBytes = $this->determineUploadSize($upload);

        $upload->update([
            'status' => 'processing',
            'status_detail' => 'Preparing import',
            'run_phase' => 'preparing',
            'failed_phase' => null,
            'checkpoint' => null,
            'error' => null,
            'error_context' => null,
            'warnings_count' => 0,
            'warnings' => null,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'total_bytes' => $totalBytes,
            'processed_bytes' => 0,
            'processed_rows' => 0,
            'progress_updated_at' => $runAt,
        ]);

        $currentPhase = 'preparing';

        try {
            $persist([
                'status_detail' => 'Pruning temporary staging',
                'run_phase' => 'pruning_staging',
            ]);

            $prunedStagingRows = $this->purgeTemporaryStagingBeforeRun($upload->id);

            $persist([
                'status_detail' => $prunedStagingRows > 0
                    ? ('Pruned ' . number_format($prunedStagingRows) . ' stale staging row(s)')
                    : 'No stale staging rows to prune',
                'run_phase' => 'preparing',
            ]);

            if ($this->forceRestart) {
                $persist([
                    'status_detail' => 'Restarting import (clearing staging)',
                    'run_phase' => 'restarting',
                ]);
                $this->cleanupStaging($upload->id);
            }

            $persist([
                'status_detail' => 'Loading staging',
                'run_phase' => 'staging',
                'processed_rows' => 0,
                'processed_bytes' => 0,
                'inserted' => 0,
                'updated' => 0,
            ]);

            $stagedRows = 0;
            if ($canResume && DB::table(self::STAGING_TABLE)->where('upload_id', $upload->id)->exists()) {
                $stagedRows = (int) DB::table(self::STAGING_TABLE)->where('upload_id', $upload->id)->count();
                $persist([
                    'status_detail' => 'Resuming (reusing staged rows)',
                    'run_phase' => 'staged',
                    'checkpoint' => [
                        'staged_rows' => $stagedRows,
                        'reused' => true,
                        'at' => $runAt->toIso8601String(),
                    ],
                ]);
            } else {
                $stagedRows = $this->ingestToStaging($upload, function (array $progress) use ($upload) {
                    $this->persistProgress($upload->id, $progress);
                });
                $persist([
                    'run_phase' => 'staged',
                    'checkpoint' => [
                        'staged_rows' => $stagedRows,
                        'reused' => false,
                        'at' => $runAt->toIso8601String(),
                    ],
                ]);
            }

            $persist([
                'status_detail' => 'Reconciling metadata',
                'run_phase' => 'reconciling_metadata',
                'processed_rows' => 0,
                'processed_bytes' => 0,
                'inserted' => 0,
                'updated' => 0,
            ]);

            $metadata = $this->synchronizeSiebelMetadataFromStaging($upload->id, $runAt);

            $persist([
                'run_phase' => 'metadata_reconciled',
                'checkpoint' => [
                    'staged_rows' => $stagedRows,
                    'metadata_reconciled_at' => CarbonImmutable::now()->toIso8601String(),
                ],
            ]);

            if ($metadata['databases'] === [] || $metadata['schemas'] === [] || $metadata['tables'] === []) {
                throw new RuntimeException('The uploaded CSV did not contain resolvable Siebel metadata.');
            }

            $persist([
                'status_detail' => 'Upserting columns',
                'run_phase' => 'upserting_columns',
            ]);

            $result = $this->syncColumnsFromStaging(
                $upload,
                $runAt,
                $totalBytes,
                $stagedRows,
                $metadata['databases'],
                $metadata['schemas'],
                $metadata['tables'],
                $metadata['data_types'],
                function (array $progress) use ($upload) {
                    $this->persistProgress($upload->id, $progress);
                }
            );

            if ((bool) ($upload->override_anonymization_rules ?? false)) {
                $persist([
                    'status_detail' => 'Applying anonymization rules',
                    'run_phase' => 'applying_anonymization_rules',
                ]);

                $ruleSync = $this->synchronizeAnonymizationRulesFromStaging(
                    $upload,
                    $runAt,
                    function (array $progress) use ($upload) {
                        $this->persistProgress($upload->id, $progress);
                    }
                );

                $result['totals']['updated'] = (int) ($result['totals']['updated'] ?? 0) + (int) ($ruleSync['changed_columns'] ?? 0);
            }

            $persist([
                'processed_rows' => $result['processedRows'],
                'processed_bytes' => $result['processedBytes'],
                'inserted' => $result['totals']['inserted'],
                'updated' => $result['totals']['updated'],
                'status_detail' => 'Reconciling deletions',
                'run_phase' => 'columns_upserted',
                'checkpoint' => [
                    'processed_rows' => $result['processedRows'],
                    'processed_bytes' => $result['processedBytes'],
                    'columns_upserted_at' => CarbonImmutable::now()->toIso8601String(),
                ],
            ]);

            // Full imports allow deletion reconciliation; partial imports do not.
            $isFullImport = ($upload->import_type ?? 'partial') === 'full';

            if ($isFullImport) {
                $persist([
                    'run_phase' => 'reconciling_deletions',
                ]);
                $deletionScope = $this->resolveFullImportDeletionScope($upload, $result['touchedTableIdentities']);

                $deletedCount = $this->softDeleteMissingColumns(
                    $deletionScope,
                    $result['processedColumnIdentitiesTempTable'],
                    $runAt
                );

                if ($deletedCount > 0) {
                    $result['totals']['deleted'] = $deletedCount;
                }
            } else {
                $result['totals']['deleted'] = $result['totals']['deleted'] ?? 0;
            }

            // Relationship rebuild can fail independently; record warnings but continue.
            if (! empty($result['touchedColumnIdsTempTable'])) {
                $persist([
                    'status_detail' => 'Reconciling relationships',
                    'run_phase' => 'reconciling_relationships',
                ]);

                try {
                    $this->rebuildColumnRelationships($result['touchedColumnIdsTempTable'], $runAt);
                } catch (Throwable $relationshipException) {
                    $warnings = (array) ($upload->warnings ?? []);
                    $warnings[] = [
                        'phase' => 'reconciling_relationships',
                        'message' => $relationshipException->getMessage(),
                        'class' => get_class($relationshipException),
                        'at' => CarbonImmutable::now()->toIso8601String(),
                    ];
                    $persist([
                        'warnings_count' => count($warnings),
                        'warnings' => $warnings,
                        'status_detail' => 'Import completed with warnings (relationships)',
                    ]);
                }
            }

            // Clean up temporary tables used during reconciliation.
            if (isset($result['touchedColumnIdsTempTable'])) {
                DB::statement("DROP TABLE IF EXISTS {$result['touchedColumnIdsTempTable']}");
            }
            if (isset($result['processedColumnIdentitiesTempTable'])) {
                DB::statement("DROP TABLE IF EXISTS {$result['processedColumnIdentitiesTempTable']}");
            }

            $persist([
                'deleted' => $result['totals']['deleted'] ?? 0,
                'status_detail' => $isFullImport ? 'Import completed' : 'Import completed (no delete reconcile for partial import)',
                'run_phase' => 'completed',
            ]);

            $upload->update([
                'status' => 'completed',
                'status_detail' => $isFullImport ? 'Import completed' : 'Import completed (no delete reconcile for partial import)',
                'run_phase' => 'completed',
                'error' => null,
                'failed_phase' => null,
                'inserted' => $result['totals']['inserted'],
                'updated' => $result['totals']['updated'],
                'deleted' => $result['totals']['deleted'] ?? 0,
                'processed_rows' => $result['processedRows'],
                'processed_bytes' => $result['processedBytes'],
                'progress_updated_at' => CarbonImmutable::now(),
                'retention_until' => CarbonImmutable::now()->addDays(max(1, (int) config('anonymizer.upload_retention_days', 30))),
            ]);

            // Only clean up staging after a successful run so failed uploads can be resumed.
            $this->cleanupStaging($upload->id);
        } catch (AnonymousUploadCancelledException $exception) {
            $upload->refresh();

            $upload->update([
                'status' => 'failed',
                'status_detail' => 'Cancelled by user',
                'run_phase' => 'cancelled',
                'failed_phase' => $currentPhase,
                'error' => null,
                'error_context' => null,
                'progress_updated_at' => CarbonImmutable::now(),
                'retention_until' => CarbonImmutable::now()->addDays(max(1, (int) config('anonymizer.upload_retention_days', 30))),
            ]);

            return;
        } catch (Throwable $exception) {
            $exceptionMessage = $this->sanitizeUtf8Value($exception->getMessage());

            $upload->update([
                'status' => 'failed',
                'status_detail' => 'Failed',
                'failed_phase' => $currentPhase,
                'error' => is_string($exceptionMessage) ? $exceptionMessage : 'Import failed',
                'error_context' => [
                    'phase' => $currentPhase,
                    'message' => $exceptionMessage,
                    'class' => get_class($exception),
                    'at' => CarbonImmutable::now()->toIso8601String(),
                ],
                'progress_updated_at' => CarbonImmutable::now(),
                'retention_until' => CarbonImmutable::now()->addDays(max(1, (int) config('anonymizer.upload_retention_days', 30))),
            ]);

            throw $exception;
        }
    }

    protected function persistProgress(int $uploadId, array $attributes): void
    {
        $this->throwIfCancellationRequested($uploadId, (string) ($attributes['run_phase'] ?? 'processing'));

        $attributes = $this->sanitizeUtf8Value($attributes);
        $attributes['progress_updated_at'] = $attributes['progress_updated_at'] ?? CarbonImmutable::now();

        AnonymousUpload::whereKey($uploadId)->update($attributes);
    }

    protected function throwIfCancellationRequested(int $uploadId, string $phase = ''): void
    {
        $upload = AnonymousUpload::query()->whereKey($uploadId)->first(['status', 'run_phase']);

        if ($upload && $this->isCancellationRequested($upload)) {
            $message = 'Upload cancelled by user';
            if ($phase !== '') {
                $message .= " during {$phase}";
            }

            throw new AnonymousUploadCancelledException($message);
        }
    }

    protected function isCancellationRequested(AnonymousUpload $upload): bool
    {
        return (string) ($upload->run_phase ?? '') === 'cancelled'
            || (string) ($upload->status ?? '') === 'cancelled';
    }

    /**
     * Ensure values persisted to JSON-cast columns are UTF-8 safe.
     */
    protected function sanitizeUtf8Value(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizeUtf8Value($item);
            }

            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $value);

            if ($converted !== false && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

            if (is_string($converted) && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? 'Invalid text encoding';
    }

    protected function determineUploadSize(AnonymousUpload $upload): ?int
    {
        try {
            return Storage::disk($upload->file_disk)->size($upload->path);
        } catch (Throwable) {
            return null;
        }
    }

    protected function purgeTemporaryStagingBeforeRun(int $currentUploadId): int
    {
        $activeUploadIds = AnonymousUpload::query()
            ->whereIn('status', ['queued', 'processing'])
            ->pluck('id')
            ->all();

        $baseQuery = DB::table(self::STAGING_TABLE)
            ->where('upload_id', '!=', $currentUploadId);

        if ($activeUploadIds !== []) {
            $baseQuery->whereNotIn('upload_id', $activeUploadIds);
        }

        $deleted = 0;
        $lastId = 0;
        $rowChunk = 5000;

        do {
            $ids = (clone $baseQuery)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($rowChunk)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            $deleted += (int) DB::table(self::STAGING_TABLE)
                ->whereIn('id', $ids)
                ->delete();

            $lastId = (int) end($ids);
        } while (count($ids) === $rowChunk);

        return $deleted;
    }
}
