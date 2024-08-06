<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelBusinessService extends Model
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
        'cache',
        'display_name',
        'display_name_string_reference',
        'display_name_string_override',
        'external_use',
        'hidden',
        'server_enabled',
        'state_management_type',
        'web_service_enabled',
        'inactive',
        'comments',
        'object_locked',
        'object_language_locked',
        'project_id',
        'class_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'cache' => 'boolean',
        'external_use' => 'boolean',
        'hidden' => 'boolean',
        'server_enabled' => 'boolean',
        'web_service_enabled' => 'boolean',
        'inactive' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'class_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SiebelClass::class);
    }
}
