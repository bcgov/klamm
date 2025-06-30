<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RadioInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'visible_label',
        'default_value',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
    ];

    /**
     * Get the form element that owns this radio input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    /**
     * Get the options for this radio input.
     */
    public function options(): MorphMany
    {
        return $this->morphMany(SelectOptionFormElement::class, 'optionable')->orderBy('order');
    }
}
