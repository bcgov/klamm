<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorEntity extends Model
{
    protected $fillable = [
        'name'
    ];

    public function formField(): BelongsTo
    {
        return $this->belongsTo(SystemMessage::class);
    }
}
