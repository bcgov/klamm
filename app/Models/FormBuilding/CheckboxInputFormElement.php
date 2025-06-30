<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CheckboxInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'visible_label',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
    ];

    protected $attributes = [
        'visible_label' => true,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.label')
                ->label('Checkbox Label')
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.visible_label')
                ->label('Show Label')
                ->default(true)
                ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this checkbox input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
