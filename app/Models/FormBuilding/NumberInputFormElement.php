<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class NumberInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'min',
        'max',
        'step',
        'defaultValue',
        'helperText',
        'formatStyle',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'min' => 'decimal:2',
        'max' => 'decimal:2',
        'step' => 'decimal:2',
        'defaultValue' => 'decimal:2',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'step' => 1,
        'formatStyle' => 'decimal',
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return array_merge(
            SchemaHelper::getCommonCarbonFields($disabled),
            [
                \Filament\Forms\Components\TextInput::make('elementable_data.min')
                    ->label('Minimum Value')
                    ->numeric()
                    ->step(1)
                    ->disabled($disabled),
                \Filament\Forms\Components\TextInput::make('elementable_data.max')
                    ->label('Maximum Value')
                    ->numeric()
                    ->step(1)
                    ->disabled($disabled),
                \Filament\Forms\Components\TextInput::make('elementable_data.step')
                    ->label('Step Size')
                    ->numeric()
                    ->step(1)
                    ->default(1)
                    ->minValue(0)
                    ->disabled($disabled),
                \Filament\Forms\Components\TextInput::make('elementable_data.defaultValue')
                    ->label('Default Value')
                    ->numeric()
                    ->step(1)
                    ->disabled($disabled),
                \Filament\Forms\Components\Select::make('elementable_data.formatStyle')
                    ->label('Format Style')
                    ->options([
                        'decimal' => 'Decimal',
                        'currency' => 'Currency',
                        'integer' => 'Integer',
                    ])
                    ->default('decimal')
                    ->live()
                    ->disabled($disabled),
            ]
        );
    }

    /**
     * Get the form element that owns this number input element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    /**
     * Return this element's data as an array
     */
    public function getData(): array
    {
        return [
            'placeholder' => $this->placeholder,
            'labelText' => $this->labelText,
            'hideLabel' => $this->hideLabel,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'defaultValue' => $this->defaultValue,
            'helperText' => $this->helperText,
            'formatStyle' => $this->formatStyle,
        ];
    }
}
