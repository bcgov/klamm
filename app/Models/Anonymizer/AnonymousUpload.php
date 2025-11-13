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
        'status',
        'inserted',
        'updated',
        'deleted',
        'error',
    ];

    protected $casts = [
        'inserted' => 'integer',
        'updated' => 'integer',
        'deleted' => 'integer',
    ];

    public function stagings()
    {
        return $this->hasMany(AnonymousSiebelStaging::class, 'upload_id');
    }
}
