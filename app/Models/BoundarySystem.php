<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoundarySystem extends Model
{
    protected $fillable = [
        'ministry_id',
        'interface_name',
        'active',
        'comments',
    ];

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }
}
