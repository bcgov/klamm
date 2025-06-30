<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SelectInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'visible_label',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
    ];

    /**
     * Get the form element that owns this select input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    /**
     * Get the options for this select input.
     */
    public function options(): MorphMany
    {
        return $this->morphMany(SelectOptionFormElement::class, 'optionable')->orderBy('order');
    }
}
