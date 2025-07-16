<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TextareaInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'rows',
        'cols',
        'maxCount',
        'defaultValue',
        'helperText',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'rows' => 'integer',
        'cols' => 'integer',
        'maxCount' => 'integer',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'rows' => 3,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.placeholder')
                ->label('Placeholder Text')
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.labelText')
                ->label('Field Label')
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.hideLabel')
                ->label('Hide Label')
                ->default(false)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.rows')
                ->label('Number of Rows')
                ->numeric()
                ->default(3)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.cols')
                ->label('Number of Columns')
                ->numeric()
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.maxCount')
                ->label('Maximum Character Count')
                ->numeric()
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.defaultValue')
                ->label('Default Value')
                ->disabled($disabled),
            \Filament\Forms\Components\Textarea::make('elementable_data.helperText')
                ->label('Helper Text')
                ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this textarea input element.
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
            'rows' => $this->rows,
            'cols' => $this->cols,
            'maxCount' => $this->maxCount,
            'defaultValue' => $this->defaultValue,
            'helperText' => $this->helperText,
        ];
    }
}
