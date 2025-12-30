<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Jobs\GenerateChangeTicketsFromUpload;
use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnonymousUpload extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected $table = 'anonymization_uploads';

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymization_uploads';
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->original_name ?: ($this->file_name ?: ('#' . $this->getKey()));
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $identifier = $this->activityLogSubjectIdentifier();
        $upload = $identifier ? "Upload {$identifier}" : ('Upload #' . $this->getKey());

        return match ($eventName) {
            'created' => "{$upload} created",
            'deleted' => "{$upload} deleted",
            'restored' => "{$upload} restored",
            'updated' => "{$upload} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    /**
     * Allow mass assignment for upload bookkeeping fields.
     */
    protected $fillable = [
        'file_disk',
        'file_name',
        'path',
        'original_name',
        'scope_type',
        'scope_name',
        'status',
        'status_detail',
        'run_phase',
        'checkpoint',
        'failed_phase',
        'import_type',
        'inserted',
        'updated',
        'deleted',
        'total_bytes',
        'processed_bytes',
        'processed_rows',
        'progress_updated_at',
        'retention_until',
        'file_deleted_at',
        'file_deleted_reason',
        'error',
        'error_context',
        'warnings_count',
        'warnings',
    ];

    protected $casts = [
        'scope_type' => 'string',
        'scope_name' => 'string',
        'inserted' => 'integer',
        'updated' => 'integer',
        'deleted' => 'integer',
        'total_bytes' => 'integer',
        'processed_bytes' => 'integer',
        'processed_rows' => 'integer',
        'progress_updated_at' => 'datetime',
        'retention_until' => 'datetime',
        'file_deleted_at' => 'datetime',

        'checkpoint' => 'array',
        'error_context' => 'array',
        'warnings_count' => 'integer',
        'warnings' => 'array',



    ];

    protected static function booted(): void
    {
        static::updated(function (self $upload) {
            // Dispatch ticket generation when an upload transitions to completed
            if ($upload->wasChanged('status') && $upload->status === 'completed') {
                GenerateChangeTicketsFromUpload::dispatch($upload->id);
            }
        });
    }

    protected $appends = [
        'progress_percent',
    ];

    public function getProgressPercentAttribute(): ?int
    {
        $totalBytes = $this->total_bytes;

        if (! $totalBytes || $totalBytes <= 0) {
            return null;
        }

        $percent = (int) floor(($this->processed_bytes / $totalBytes) * 100);

        return max(0, min(100, $percent));
    }

    public function stagings()
    {
        return $this->hasMany(AnonymousSiebelStaging::class, 'upload_id');
    }

    public function storageDisk(): string
    {
        return $this->file_disk ?: config('filesystems.default', 'local');
    }

    public function hasStoredFile(): bool
    {
        if (! $this->path) {
            return false;
        }

        try {
            return Storage::disk($this->storageDisk())->exists($this->path);
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteStoredFile(string $reason = 'manual'): bool
    {
        $disk = $this->storageDisk();
        $path = $this->path;

        $deleted = false;

        if ($path) {
            try {
                $storage = Storage::disk($disk);
                if ($storage->exists($path)) {
                    $deleted = (bool) $storage->delete($path);
                }

                $errorPath = $path . '.errors.json';
                if ($storage->exists($errorPath)) {
                    $storage->delete($errorPath);
                }
            } catch (Throwable) {
                $deleted = false;
            }
        }

        $this->forceFill([
            'file_deleted_at' => now(),
            'file_deleted_reason' => $reason,
        ])->save();

        return $deleted;
    }
}
