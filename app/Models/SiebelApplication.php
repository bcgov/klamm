<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelApplication extends Model
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
        'menu',
        'scripted',
        'acknowledgment_web_page',
        'container_web_page',
        'error_web_page',
        'login_web_page',
        'logoff_acknowledgment_web_page',
        'acknowledgment_web_view',
        'default_find',
        'inactive',
        'comments',
        'object_locked',
        'object_language_locked',
        'project_id',
        'task_screen_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'scripted' => 'boolean',
        'inactive' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'task_screen_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function taskScreen(): BelongsTo
    {
        return $this->belongsTo(SiebelScreen::class);
    }
}
