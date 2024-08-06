<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelIntegrationObject extends Model
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
        'adapter_info',
        'base_object_type',
        'external_major_version',
        'external_minor_version',
        'external_name',
        'xml_tag',
        'inactive',
        'comments',
        'object_locked',
        'object_language_locked',
        'project_id',
        'business_object_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'inactive' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'business_object_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function businessObject(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessObject::class);
    }
}
