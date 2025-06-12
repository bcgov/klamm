<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Container extends Element
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'element_id',
        'has_repeater',
        'has_clear_button',
        'repeater_item_label',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'has_repeater' => 'boolean',
        'has_clear_button' => 'boolean',
    ];

    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }
}
