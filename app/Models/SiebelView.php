<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelView extends Model
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
        'visibility_applet',
        'visibility_applet_type',
        'admin_mode_flag',
        'thread_applet',
        'thread_field',
        'thread_title',
        'thread_title_string_reference',
        'thread_title_string_override',
        'inactive',
        'comments',
        'bitmap_category',
        'drop_sectors',
        'explicit_login',
        'help_identifier',
        'no_borders',
        'screen_menu',
        'sector0_applet',
        'sector1_applet',
        'sector2_applet',
        'sector3_applet',
        'sector4_applet',
        'sector5_applet',
        'sector6_applet',
        'sector7_applet',
        'secure',
        'status_text',
        'status_text_string_reference',
        'status_text_string_override',
        'title',
        'title_string_reference',
        'title_string_override',
        'vertical_line_position',
        'upgrade_behavior',
        'icl_upgrade_path',
        'add_to_history',
        'task',
        'type',
        'default_applet_focus',
        'disable_pdq',
        'object_locked',
        'object_language_locked',
        'business_object_id',
        'project_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'admin_mode_flag' => 'boolean',
        'inactive' => 'boolean',
        'explicit_login' => 'boolean',
        'no_borders' => 'boolean',
        'screen_menu' => 'boolean',
        'secure' => 'boolean',
        'add_to_history' => 'boolean',
        'disable_pdq' => 'boolean',
        'object_locked' => 'boolean',
        'business_object_id' => 'integer',
        'project_id' => 'integer',
    ];

    public function businessObject(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessObject::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }
}
