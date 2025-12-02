<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class NumberInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'enableVarSub',
        'min',
        'max',
        'step',
        'defaultValue',
        'formatStyle',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'min' => 'integer',
        'max' => 'integer',
        'step' => 'integer',
        'defaultValue' => 'decimal:2',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'step' => 1,
        'formatStyle' => 'integer',
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
                        SchemaHelper::getPlaceholderTextField($disabled),
                    TextInput::make('elementable_data.min')
                        ->label('Minimum Value')
                        ->numeric()
                        ->integer()
                        ->step(1)
                        ->disabled($disabled),
                    TextInput::make('elementable_data.max')
                        ->label('Maximum Value')
                        ->numeric()
                        ->integer()
                        ->step(1)
                        ->disabled($disabled),
                    TextInput::make('elementable_data.step')
                        ->label('Step Size')
                        ->numeric()
                        ->integer()
                        ->step(1)
                        ->default(1)
                        ->minValue(0)
                        ->disabled($disabled),
                    TextInput::make('elementable_data.defaultValue')
                        ->label('Default Value')
                        ->numeric()
                        ->step(1)
                        ->disabled($disabled),
                    Select::make('elementable_data.formatStyle')
                        ->label('Format Style')
                        ->options([
                            'decimal' => 'Decimal',
                            'currency' => 'Currency',
                            'integer' => 'Integer',
                        ])
                        ->default('decimal')
                        ->live()
                        ->disabled($disabled),
                    ])
                    ->columns(1),
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
            'enableVarSub' => $this->enableVarSub,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'defaultValue' => $this->defaultValue,
            'formatStyle' => $this->formatStyle,
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
            'step' => 1,
            'defaultValue' => null,
            'formatStyle' => 'integer',
        ];
    }
}
