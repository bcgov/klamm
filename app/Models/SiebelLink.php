<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelLink extends Model
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
        'source_field',
        'destination_field',
        'inter_parent_column',
        'inter_child_column',
        'inter_child_delete',
        'primary_id_field',
        'cascade_delete',
        'search_specification',
        'association_list_sort_specification',
        'no_associate',
        'no_delete',
        'no_insert',
        'no_inter_delete',
        'no_update',
        'visibility_auto_all',
        'visibility_rule_applied',
        'visibility_type',
        'inactive',
        'comments',
        'object_locked',
        'object_language_locked',
        'project_id',
        'parent_business_component_id',
        'child_business_component_id',
        'inter_table_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'inter_child_delete' => 'boolean',
        'no_associate' => 'boolean',
        'no_delete' => 'boolean',
        'no_insert' => 'boolean',
        'no_inter_delete' => 'boolean',
        'no_update' => 'boolean',
        'visibility_auto_all' => 'boolean',
        'inactive' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'parent_business_component_id' => 'integer',
        'child_business_component_id' => 'integer',
        'inter_table_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function parentBusinessComponent(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessComponent::class);
    }

    public function childBusinessComponent(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessComponent::class);
    }

    public function interTable(): BelongsTo
    {
        return $this->belongsTo(SiebelTable::class);
    }
}
