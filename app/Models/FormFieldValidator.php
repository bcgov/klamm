<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class FormFieldValidator extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'regex',
        'description',
    ];

    // Polymorphic many-to-many relationship with various form element types
    public function textInputFormElements(): MorphToMany
    {
        return $this->morphedByMany(TextInputFormElement::class, 'validatorable', 'form_field_validatorables');
    }

    public function checkboxInputFormElements(): MorphToMany
    {
        return $this->morphedByMany(CheckboxInputFormElement::class, 'validatorable', 'form_field_validatorables');
    }

    public function numberInputFormElements(): MorphToMany
    {
        return $this->morphedByMany(NumberInputFormElement::class, 'validatorable', 'form_field_validatorables');
    }

    public function textareaInputFormElements(): MorphToMany
    {
        return $this->morphedByMany(TextareaInputFormElement::class, 'validatorable', 'form_field_validatorables');
    }

    public function selectInputFormElements(): MorphToMany
    {
        return $this->morphedByMany(SelectInputFormElement::class, 'validatorable', 'form_field_validatorables');
    }

    public function radioInputFormElements(): MorphToMany
    {
        return $this->morphedByMany(RadioInputFormElement::class, 'validatorable', 'form_field_validatorables');
    }

    public function dateSelectInputFormElements(): MorphToMany
    {
        return $this->morphedByMany(DateSelectInputFormElement::class, 'validatorable', 'form_field_validatorables');
    }
}
