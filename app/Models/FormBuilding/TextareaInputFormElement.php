<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TextareaInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'placeholder_text',
        'label',
        'visible_label',
        'rows',
        'cols',
        'maxlength',
        'minlength',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
        'rows' => 'integer',
        'cols' => 'integer',
        'maxlength' => 'integer',
        'minlength' => 'integer',
    ];

    /**
     * Get the form element that owns this textarea input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
