<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnonymousUpload extends Model
{
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
}
