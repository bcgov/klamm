<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;

class CurrencyInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'enableVarSub',
        'min',
        'max',
        'defaultValue',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'min' => 'integer',
        'max' => 'integer',
        'defaultValue' => 'float',
    ];

    protected $attributes = [
        'hideLabel' => false,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return array_merge(
            SchemaHelper::getCommonCarbonFields($disabled),
            [
                Fieldset::make('Value')
                    ->schema([
                        SchemaHelper::getPlaceholderTextField($disabled)
                            ->columnSpan(3),
                        TextInput::make('elementable_data.defaultValue')
                            ->label('Default Value')
                            ->numeric()
                            ->step(.01)
                            ->disabled($disabled),
                        TextInput::make('elementable_data.min')
                            ->label('Minimum Value')
                            ->numeric()
                            ->step(.01)
                            ->disabled($disabled),
                        TextInput::make('elementable_data.max')
                            ->label('Maximum Value')
                            ->numeric()
                            ->step(.01)
                            ->disabled($disabled),
                    ])
                    ->columns(3),
            ]
        );
    }

    /**
     * Get the form element that owns this currency input element.
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
            'enableVarSub' => $this->enableVarSub,
            'min' => $this->min,
            'max' => $this->max,
            'defaultValue' => $this->defaultValue,
        ];
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'placeholder' => '',
            'labelText' => '',
            'hideLabel' => false,
            'enableVarSub' => false,
            'min' => null,
            'max' => null,
            'defaultValue' => null,
        ];
    }
}
