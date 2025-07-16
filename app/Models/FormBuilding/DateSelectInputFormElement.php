<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class DateSelectInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'minDate',
        'maxDate',
        'dateFormat',
        'helperText',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'minDate' => 'date',
        'maxDate' => 'date',
    ];

    protected $attributes = [
        'hideLabel' => false,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.labelText')
                ->label('Field Label')
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.hideLabel')
                ->label('Hide Label')
                ->default(false)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.placeholder')
                ->label('Placeholder Text')
                ->disabled($disabled),
            \Filament\Forms\Components\Select::make('elementable_data.dateFormat')
                ->label('Date Format')
                ->options(static::getDateFormats())
                ->default('Y-m-d')
                ->disabled($disabled),
            \Filament\Forms\Components\DatePicker::make('elementable_data.minDate')
                ->label('Minimum Date')
                ->helperText('Earliest date users can select')
                ->disabled($disabled),
            \Filament\Forms\Components\DatePicker::make('elementable_data.maxDate')
                ->label('Maximum Date')
                ->helperText('Latest date users can select')
                ->disabled($disabled),
            \Filament\Forms\Components\Textarea::make('elementable_data.helperText')
                ->label('Helper Text')
                ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this date select input element.
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
            'minDate' => $this->minDate,
            'maxDate' => $this->maxDate,
            'dateFormat' => $this->dateFormat,
            'helperText' => $this->helperText,
        ];
    }

    /**
     * Get available date formats.
     */
    public static function getDateFormats(): array
    {
        return [
            'Y-m-d' => 'YYYY-MM-DD',
            'd/m/Y' => 'DD/MM/YYYY',
            'm/d/Y' => 'MM/DD/YYYY',
            'd-m-Y' => 'DD-MM-YYYY',
            'm-d-Y' => 'MM-DD-YYYY',
            'Y-m-d H:i' => 'YYYY-MM-DD HH:MM',
            'd/m/Y H:i' => 'DD/MM/YYYY HH:MM',
        ];
    }
}
