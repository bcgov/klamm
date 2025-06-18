<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class RadioInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'visible_label',
        'options',
        'default_value',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
        'options' => 'array',
    ];

    // Polymorphic relationship back to FormElement
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    // Polymorphic many-to-many relationship with validators
    public function validators(): MorphToMany
    {
        return $this->morphToMany(FormFieldValidator::class, 'validatorable', 'form_field_validatorables');
    }
}
