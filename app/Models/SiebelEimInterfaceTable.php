<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelEimInterfaceTable extends Model
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
        'user_name',
        'type',
        'file',
        'eim_delete_proc_column',
        'eim_export_proc_column',
        'eim_merge_proc_column',
        'inactive',
        'comments',
        'object_locked',
        'object_language_locked',
        'project_id',
        'target_table_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'file' => 'boolean',
        'inactive' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'target_table_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function targetTable(): BelongsTo
    {
        return $this->belongsTo(SiebelTable::class);
    }
}
