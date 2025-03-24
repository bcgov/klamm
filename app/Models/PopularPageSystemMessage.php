<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopularPageSystemMessage extends Model
{
    protected $fillable = [
        'display_text',
        'path',
    ];

    public static function hasReachedMaximum(): bool
    {
        return self::count() >= 4;
    }
}
