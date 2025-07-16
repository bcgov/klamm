<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TextInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'helperText',
        'mask',
        'maxCount',
        'defaultValue',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'maxCount' => 'integer',
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
                \Filament\Forms\Components\TextInput::make('elementable_data.mask')
                    ->label('Input Mask')
                    ->disabled($disabled),
                \Filament\Forms\Components\TextInput::make('elementable_data.maxCount')
                    ->label('Maximum Character Count')
                    ->numeric()
                    ->disabled($disabled),
                \Filament\Forms\Components\TextInput::make('elementable_data.defaultValue')
                    ->label('Default Value')
                    ->disabled($disabled),
            ]
        );
    }

    /**
     * Get the form element that owns this text input element.
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
            'mask' => $this->mask,
            'maxCount' => $this->maxCount,
            'defaultValue' => $this->defaultValue,
            'helperText' => $this->helperText,
        ];
    }
}
