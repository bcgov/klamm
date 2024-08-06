<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelClass extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'changed',
        'repository_name',
        'dll',
        'object_type',
        'thin_client',
        'java_thin_client',
        'handheld_client',
        'unix_support',
        'high_interactivity_enabled',
        'inactive',
        'comments',
        'object_locked',
        'object_language_locked',
        'project_id',
        'super_class_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'thin_client' => 'boolean',
        'java_thin_client' => 'boolean',
        'handheld_client' => 'boolean',
        'inactive' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'super_class_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function superClass(): BelongsTo
    {
        return $this->belongsTo(SiebelClass::class);
    }
}
