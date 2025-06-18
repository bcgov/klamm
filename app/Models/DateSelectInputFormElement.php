<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class DateSelectInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder_text',
        'label',
        'visible_label',
        'repeater_item_label',
        'min_date',
        'max_date',
        'default_date',
        'date_format',
        'include_time',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
        'include_time' => 'boolean',
        'min_date' => 'date',
        'max_date' => 'date',
        'default_date' => 'date',
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
