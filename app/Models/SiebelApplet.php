<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelApplet extends Model
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
        'title',
        'title_string_reference',
        'title_string_override',
        'search_specification',
        'associate_applet',
        'type',
        'no_delete',
        'no_insert',
        'no_merge',
        'no_update',
        'html_number_of_rows',
        'scripted',
        'inactive',
        'comments',
        'auto_query_mode',
        'background_bitmap_style',
        'html_popup_dimension',
        'height',
        'help_identifier',
        'insert_position',
        'mail_address_field',
        'mail_template',
        'popup_dimension',
        'upgrade_ancestor',
        'width',
        'upgrade_behavior',
        'icl_upgrade_path',
        'task',
        'default_applet_method',
        'default_double_click_method',
        'disable_dataloss_warning',
        'object_locked',
        'project_id',
        'business_component_id',
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
        'no_delete' => 'boolean',
        'no_insert' => 'boolean',
        'no_merge' => 'boolean',
        'no_update' => 'boolean',
        'scripted' => 'boolean',
        'inactive' => 'boolean',
        'disable_dataloss_warning' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'business_component_id' => 'integer',
        'class_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function businessComponent(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessComponent::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SiebelClass::class);
    }
}
