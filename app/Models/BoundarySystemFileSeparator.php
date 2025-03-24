<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoundarySystemFileSeparator extends Model
{
    protected $fillable = [
        'separator',
        'description',
    ];
}
