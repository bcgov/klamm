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

    public function __construct(public int $uploadId) {}

    // Allow the queue worker to manage timeouts (set via worker flag) without a hard 10-minute cap here.
    public int $timeout = 0;

    public function handle(): void
    {
        $upload = AnonymousUpload::findOrFail($this->uploadId);

        $runAt = CarbonImmutable::now();
        $totalBytes = $this->determineUploadSize($upload);

        $upload->update([
            'status' => 'processing',
            'status_detail' => 'Preparing import',
            'error' => null,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'total_bytes' => $totalBytes,
            'processed_bytes' => 0,
            'processed_rows' => 0,
            'progress_updated_at' => $runAt,
        ]);

        try {
            $this->persistProgress($upload->id, [
                'status_detail' => 'Loading staging',
                'processed_rows' => 0,
                'processed_bytes' => 0,
                'inserted' => 0,
                'updated' => 0,
            ]);

            $stagedRows = $this->ingestToStaging($upload);

            $this->persistProgress($upload->id, [
                'status_detail' => 'Reconciling metadata',
                'processed_rows' => 0,
                'processed_bytes' => 0,
                'inserted' => 0,
                'updated' => 0,
            ]);

            $metadata = $this->synchronizeSiebelMetadataFromStaging($upload->id, $runAt);

            if ($metadata['databases'] === [] || $metadata['schemas'] === [] || $metadata['tables'] === []) {
                throw new RuntimeException('The uploaded CSV did not contain resolvable Siebel metadata.');
            }

            $this->persistProgress($upload->id, [
                'status_detail' => 'Upserting columns',
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

            $this->persistProgress($upload->id, [
                'processed_rows' => $result['processedRows'],
                'processed_bytes' => $result['processedBytes'],
                'inserted' => $result['totals']['inserted'],
                'updated' => $result['totals']['updated'],
                'status_detail' => 'Reconciling deletions',
            ]);

            $isFullImport = ($upload->import_type ?? 'partial') === 'full';

            if ($isFullImport) {
                $deletedCount = $this->softDeleteMissingColumns(
                    $result['touchedTableIdentities'],
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
                $this->persistProgress($upload->id, [
                    'status_detail' => 'Reconciling relationships',
                ]);

                $this->rebuildColumnRelationships($result['touchedColumnIdsTempTable'], $runAt);
            }

            // Clean up temporary tables
            if (isset($result['touchedColumnIdsTempTable'])) {
                DB::statement("DROP TABLE IF EXISTS {$result['touchedColumnIdsTempTable']}");
            }
            if (isset($result['processedColumnIdentitiesTempTable'])) {
                DB::statement("DROP TABLE IF EXISTS {$result['processedColumnIdentitiesTempTable']}");
            }

            $this->persistProgress($upload->id, [
                'deleted' => $result['totals']['deleted'] ?? 0,
                'status_detail' => $isFullImport ? 'Import completed' : 'Import completed (no delete reconcile for partial import)',
            ]);

            $upload->update([
                'status' => 'completed',
                'status_detail' => $isFullImport ? 'Import completed' : 'Import completed (no delete reconcile for partial import)',
                'error' => null,
                'inserted' => $result['totals']['inserted'],
                'updated' => $result['totals']['updated'],
                'deleted' => $result['totals']['deleted'] ?? 0,
                'processed_rows' => $result['processedRows'],
                'processed_bytes' => $result['processedBytes'],
                'progress_updated_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'status_detail' => 'Failed',
                'error' => $exception->getMessage(),
                'progress_updated_at' => CarbonImmutable::now(),
            ]);

            throw $exception;
        } finally {
            $this->cleanupStaging($upload->id);
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
