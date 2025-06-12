<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElementValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'element_id',
        'type',
        'value',
        'error_message',
    ];

    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }
}
