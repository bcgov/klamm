<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnonymousUpload extends Model
{
    use HasFactory;

    protected $table = 'anonymization_uploads';

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
        'import_type',
        'inserted',
        'updated',
        'deleted',
        'total_bytes',
        'processed_bytes',
        'processed_rows',
        'progress_updated_at',
        'error',
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
    ];

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
}
