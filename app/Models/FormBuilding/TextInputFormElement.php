<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TextInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder_text',
        'label',
        'visible_label',
        'mask',
        'maxlength',
        'minlength',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
        'maxlength' => 'integer',
        'minlength' => 'integer',
    ];

    /**
     * Get the form element that owns this text input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
