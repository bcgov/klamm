<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithAnonymousSiebelSync;
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

/**
 * Job that stages anonymized Siebel metadata and reconciles canonical tables in bulk.
 */
class SyncAnonymousSiebelColumnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use InteractsWithAnonymousSiebelSync;

    public function __construct(public int $uploadId, public bool $forceRestart = false) {}

    // Allow the queue worker to manage timeouts (set via worker flag) without a hard 10-minute cap here.
    public int $timeout = 0;

    public function handle(): void
    {
        $upload = AnonymousUpload::findOrFail($this->uploadId);

        $currentPhase = (string) ($upload->run_phase ?? '');

        $persist = function (array $attributes) use ($upload, &$currentPhase): void {
            if (array_key_exists('run_phase', $attributes)) {
                $currentPhase = (string) ($attributes['run_phase'] ?? '');
            }

            $this->persistProgress($upload->id, $attributes);
        };

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

            // Clean up temporary tables
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
        } catch (Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'status_detail' => 'Failed',
                'failed_phase' => $currentPhase,
                'error' => $exception->getMessage(),
                'error_context' => [
                    'phase' => $currentPhase,
                    'message' => $exception->getMessage(),
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
        $attributes['progress_updated_at'] = $attributes['progress_updated_at'] ?? CarbonImmutable::now();

        AnonymousUpload::whereKey($uploadId)->update($attributes);
    }

    protected function determineUploadSize(AnonymousUpload $upload): ?int
    {
        try {
            return Storage::disk($upload->file_disk)->size($upload->path);
        } catch (Throwable) {
            return null;
        }
    }
}
