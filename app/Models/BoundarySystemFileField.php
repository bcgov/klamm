<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoundarySystemFileField extends Model
{
    protected $fillable = [
        'field_name',
        'field_type',
        'field_length',
        'field_description',
        'validations',
    ];
}
