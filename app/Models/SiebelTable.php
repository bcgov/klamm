<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelTable extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'object_language_locked',
        'object_locked',
        'object_locked_by_name',
        'object_locked_date',
        'name',
        'changed',
        'repository_name',
        'user_name',
        'alias',
        'type',
        'file',
        'abbreviation_1',
        'abbreviation_2',
        'abbreviation_3',
        'append_data',
        'dflt_mapping_col_name_prefix',
        'seed_filter',
        'seed_locale_filter',
        'seed_usage',
        'group',
        'owner_organization_specifier',
        'status',
        'volatile',
        'inactive',
        'node_type',
        'partition_indicator',
        'comments',
        'external_api_write',
        'project_id',
        'base_table_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'object_locked' => 'boolean',
        'object_locked_date' => 'timestamp',
        'changed' => 'boolean',
        'file' => 'boolean',
        'append_data' => 'boolean',
        'volatile' => 'boolean',
        'inactive' => 'boolean',
        'partition_indicator' => 'boolean',
        'external_api_write' => 'boolean',
        'project_id' => 'integer',
        'base_table_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function baseTable(): BelongsTo
    {
        return $this->belongsTo(SiebelTable::class);
    }
}
