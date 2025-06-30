<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class NumberInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder_text',
        'label',
        'visible_label',
        'min',
        'max',
        'step',
        'default_value',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
        'min' => 'integer',
        'max' => 'integer',
        'step' => 'integer',
        'default_value' => 'integer',
    ];

    /**
     * Get the form element that owns this number input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
