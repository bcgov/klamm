<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelWorkflowProcess extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'auto_persist',
        'process_name',
        'simulate_workflow_process',
        'status',
        'workflow_mode',
        'changed',
        'group',
        'version',
        'description',
        'error_process_name',
        'state_management_type',
        'web_service_enabled',
        'pass_by_ref_hierarchy_argument',
        'repository_name',
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
        'auto_persist' => 'boolean',
        'changed' => 'boolean',
        'web_service_enabled' => 'boolean',
        'pass_by_ref_hierarchy_argument' => 'boolean',
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
