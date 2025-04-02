<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoundarySystemFileFieldMap extends Model
{
    protected $fillable = [
        'file_id',
        'field_id',
        'section',
        'field_structure',
        'mandatory',
    ];
}
