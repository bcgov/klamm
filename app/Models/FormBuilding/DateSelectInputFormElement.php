<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class DateSelectInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'placeholder_text',
        'label',
        'visible_label',
        'repeater_item_label',
        'min_date',
        'max_date',
        'default_date',
        'date_format',
        'include_time',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
        'min_date' => 'date',
        'max_date' => 'date',
        'default_date' => 'date',
        'include_time' => 'boolean',
    ];

    protected $attributes = [
        'visible_label' => true,
        'include_time' => false,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.label')
                ->label('Field Label')
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.visible_label')
                ->label('Show Label')
                ->default(true)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.placeholder_text')
                ->label('Placeholder Text')
                ->disabled($disabled),
            \Filament\Forms\Components\Select::make('elementable_data.date_format')
                ->label('Date Format')
                ->options(static::getDateFormats())
                ->default('Y-m-d')
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.include_time')
                ->label('Include Time')
                ->helperText('Allow users to select time in addition to date')
                ->default(false)
                ->disabled($disabled),
            \Filament\Forms\Components\DatePicker::make('elementable_data.min_date')
                ->label('Minimum Date')
                ->helperText('Earliest date users can select')
                ->disabled($disabled),
            \Filament\Forms\Components\DatePicker::make('elementable_data.max_date')
                ->label('Maximum Date')
                ->helperText('Latest date users can select')
                ->disabled($disabled),
            \Filament\Forms\Components\DatePicker::make('elementable_data.default_date')
                ->label('Default Date')
                ->helperText('Pre-selected date')
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.repeater_item_label')
                ->label('Repeater Item Label')
                ->helperText('Used when this element is part of a repeater')
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
