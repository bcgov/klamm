<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelScreen extends Model
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
        'bitmap_category',
        'viewbar_text',
        'viewbar_text_string_reference',
        'viewbar_text_string_override',
        'unrestricted_viewbar',
        'help_identifier',
        'inactive',
        'comments',
        'upgrade_behavior',
        'object_locked',
        'object_language_locked',
        'project_id',
        'default_view_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'unrestricted_viewbar' => 'boolean',
        'inactive' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'default_view_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function defaultView(): BelongsTo
    {
        return $this->belongsTo(SiebelView::class);
    }
}
