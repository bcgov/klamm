<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiebelBusinessComponent extends Model
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
        'cache_data',
        'data_source',
        'dirty_reads',
        'distinct',
        'enclosure_id_field',
        'force_active',
        'gen_reassign_act',
        'hierarchy_parent_field',
        'type',
        'inactive',
        'insert_update_all_columns',
        'log_changes',
        'maximum_cursor_size',
        'multirecipient_select',
        'no_delete',
        'no_insert',
        'no_update',
        'no_merge',
        'owner_delete',
        'placeholder',
        'popup_visibility_auto_all',
        'popup_visibility_type',
        'prefetch_size',
        'recipient_id_field',
        'reverse_fill_threshold',
        'scripted',
        'search_specification',
        'sort_specification',
        'status_field',
        'synonym_field',
        'upgrade_ancestor',
        'xa_attribute_value_bus_comp',
        'xa_class_id_field',
        'comments',
        'object_locked',
        'object_language_locked',
        'project_id',
        'class_id',
        'table_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'cache_data' => 'boolean',
        'dirty_reads' => 'boolean',
        'distinct' => 'boolean',
        'force_active' => 'boolean',
        'gen_reassign_act' => 'boolean',
        'inactive' => 'boolean',
        'insert_update_all_columns' => 'boolean',
        'log_changes' => 'boolean',
        'multirecipient_select' => 'boolean',
        'no_delete' => 'boolean',
        'no_insert' => 'boolean',
        'no_update' => 'boolean',
        'no_merge' => 'boolean',
        'owner_delete' => 'boolean',
        'placeholder' => 'boolean',
        'popup_visibility_auto_all' => 'boolean',
        'scripted' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'class_id' => 'integer',
        'table_id' => 'integer',
        'siebel_fields' => 'array',
        'siebel_applets' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SiebelClass::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(SiebelTable::class);
    }

    /**
     * Get the fields associated with the business component.
     */

    public function siebelFields(): HasMany
    {
        return $this->hasMany(SiebelField::class, 'business_component_id');
    }

    /**
     * Get the applets associated with the business component.
     */

    public function siebelApplets(): HasMany
    {
        return $this->hasMany(SiebelApplet::class, 'business_component_id');
    }
}
