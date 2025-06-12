<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElementConditional extends Model
{
    use HasFactory;

    protected $fillable = [
        'element_id',
        'condition_type',
        'condition_value',
        'action_type',
        'action_value',
    ];

    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }
}
