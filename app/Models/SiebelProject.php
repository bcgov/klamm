<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiebelProject extends Model
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
        'parent_repository',
        'inactive',
        'locked',
        'locked_by_name',
        'locked_date',
        'language_locked',
        'ui_freeze',
        'comments',
        'allow_object_locking',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'inactive' => 'boolean',
        'locked' => 'boolean',
        'locked_date' => 'timestamp',
        'ui_freeze' => 'boolean',
        'allow_object_locking' => 'boolean',
    ];
}
