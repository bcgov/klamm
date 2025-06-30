<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ButtonInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'button_type',
    ];

    protected $casts = [
        'button_type' => 'string',
    ];

    /**
     * Get the form element that owns this button input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    /**
     * Get available button types.
     */
    public static function getButtonTypes(): array
    {
        return [
            'submit' => 'Submit',
            'reset' => 'Reset',
            'button' => 'Button',
        ];
    }
}
