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
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.placeholder_text')
                ->label('Placeholder Text')
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.label')
                ->label('Field Label')
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.visible_label')
                ->label('Show Label')
                ->default(true)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.min')
                ->label('Minimum Value')
                ->numeric()
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.max')
                ->label('Maximum Value')
                ->numeric()
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.step')
                ->label('Step Size')
                ->numeric()
                ->default(1)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.default_value')
                ->label('Default Value')
                ->numeric()
                ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this number input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
