<?php

namespace App\Console\Commands;

use App\Models\Anonymizer\AnonymousUpload;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeAnonymizationUploads extends Command
{
    protected $signature = 'anonymization:purge-uploads {--dry-run : Report what would be deleted without deleting files} {--limit=500 : Max records to process per run}';

    protected $description = 'Deletes stored anonymization CSV uploads after their retention period has elapsed.';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $query = AnonymousUpload::query()
            ->whereNotNull('path')
            ->whereNull('file_deleted_at')
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', $now)
            ->orderBy('retention_until')
            ->limit(max(1, $limit));

        $uploads = $query->get();

        if ($uploads->isEmpty()) {
            $this->info('No expired uploads to purge.');
            return self::SUCCESS;
        }

        $purged = 0;
        $missing = 0;
        $failed = 0;

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

        if ($dryRun) {
            $this->info(sprintf('Dry run complete. Candidates: %d', $uploads->count()));
            return self::SUCCESS;
        }

        $this->info(sprintf('Purged: %d, missing: %d, failed: %d', $purged, $missing, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
