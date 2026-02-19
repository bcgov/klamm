<?php

namespace App\Console\Commands;

use App\Models\Anonymizer\AnonymousUpload;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeAnonymizationUploads extends Command
{
    protected $signature = 'anonymization:purge-uploads {--dry-run : Report what would be deleted without deleting files} {--limit=500 : Max records to process per run} {--staging-limit=1000 : Max uploads to scan for staging pruning per run} {--staging-upload-chunk=200 : Upload IDs per staging delete pass} {--row-chunk=5000 : Staging row IDs deleted per statement}';

    protected $description = 'Deletes retained anonymization upload files and prunes stale staging rows.';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $stagingLimit = max(1, (int) $this->option('staging-limit'));
        $stagingUploadChunk = max(1, (int) $this->option('staging-upload-chunk'));
        $rowChunk = max(100, (int) $this->option('row-chunk'));
        $stagingRetentionDays = max(1, (int) config('anonymizer.staging_retention_days', 7));
        $stagingCutoff = $now->subDays($stagingRetentionDays);

        $query = AnonymousUpload::query()
            ->whereNotNull('path')
            ->whereNull('file_deleted_at')
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', $now)
            ->orderBy('retention_until')
            ->limit(max(1, $limit));

        $uploads = $query->get();

        $purged = 0;
        $missing = 0;
        $failed = 0;

        if ($uploads->isNotEmpty()) {
            foreach ($uploads as $upload) {
                $disk = $upload->file_disk ?: config('filesystems.default', 'local');
                $path = $upload->path;

                if (! $path) {
                    continue;
                }

                $storage = Storage::disk($disk);

                try {
                    $exists = $storage->exists($path);

                    if ($dryRun) {
                        $this->line(sprintf('[dry-run] %s:%s (%s)', $disk, $path, $exists ? 'exists' : 'missing'));
                        continue;
                    }

                    if ($exists) {
                        $storage->delete($path);
                        $purged++;
                    } else {
                        $missing++;
                    }

                    $upload->forceFill([
                        'file_deleted_at' => $now,
                        'file_deleted_reason' => 'retention',
                    ])->save();
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                    $this->error(sprintf('Failed to purge upload #%d (%s:%s): %s', $upload->id, $disk, $path, $exception->getMessage()));
                }
            }
        }

        $stagingUploadIds = AnonymousUpload::query()
            ->whereIn('status', ['completed', 'failed'])
            ->where('updated_at', '<=', $stagingCutoff)
            ->orderBy('id')
            ->limit($stagingLimit)
            ->pluck('id')
            ->all();

        $stagingRows = 0;
        $stagingUploads = count($stagingUploadIds);

        if ($stagingUploadIds !== []) {
            if ($dryRun) {
                $stagingRows = (int) DB::table('anonymous_siebel_stagings')
                    ->whereIn('upload_id', $stagingUploadIds)
                    ->count();
            } else {
                foreach (array_chunk($stagingUploadIds, $stagingUploadChunk) as $idChunk) {
                    do {
                        $rowIds = DB::table('anonymous_siebel_stagings')
                            ->whereIn('upload_id', $idChunk)
                            ->orderBy('id')
                            ->limit($rowChunk)
                            ->pluck('id')
                            ->all();

                        if ($rowIds === []) {
                            break;
                        }

                        $stagingRows += (int) DB::table('anonymous_siebel_stagings')
                            ->whereIn('id', $rowIds)
                            ->delete();
                    } while (count($rowIds) === $rowChunk);
                }
            }
        }

        if ($dryRun) {
            $this->info(sprintf(
                'Dry run complete. File candidates: %d. Staging candidates: %d uploads, %d rows (older than %d days).',
                $uploads->count(),
                $stagingUploads,
                $stagingRows,
                $stagingRetentionDays
            ));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Purged files: %d, missing files: %d, failed file purges: %d, pruned staging uploads: %d, pruned staging rows: %d (older than %d days)',
            $purged,
            $missing,
            $failed,
            $stagingUploads,
            $stagingRows,
            $stagingRetentionDays
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
