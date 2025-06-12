<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElementDateFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'date_format',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }
}
