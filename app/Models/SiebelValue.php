<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiebelValue extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'inactive',
        'type',
        'display_value',
        'changed',
        'translate',
        'multilingual',
        'language_independent_code',
        'parent_lic',
        'high',
        'low',
        'order',
        'active',
        'language_name',
        'replication_level',
        'target_low',
        'target_high',
        'weighting_factor',
        'description',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'inactive' => 'boolean',
        'changed' => 'boolean',
        'translate' => 'boolean',
        'multilingual' => 'boolean',
        'active' => 'boolean',
    ];
}
