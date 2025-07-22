<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
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
        return array_merge(
            SchemaHelper::getCommonCarbonFields($disabled),
            [
                \Filament\Forms\Components\Select::make('elementable_data.dateFormat')
                    ->label('Date Format')
                    ->options(static::getDateFormats())
                    ->default('YYYY-MM-DD')
                    ->disabled($disabled),
                \Filament\Forms\Components\DatePicker::make('elementable_data.minDate')
                    ->label('Minimum Date')
                    ->helperText('Earliest date users can select')
                    ->disabled($disabled),
                \Filament\Forms\Components\DatePicker::make('elementable_data.maxDate')
                    ->label('Maximum Date')
                    ->helperText('Latest date users can select')
                    ->disabled($disabled),
            ]
        );
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
        ];
    }

    /**
     * Get available date formats.
     */
    public static function getDateFormats(): array
    {
        return [
            'DD/MM/YY' => 'DD/MM/YY',
            'D-MMM-YY' => 'D-MMM-YY',
            'MMMM D, YYYY' => 'MMMM D, YYYY',
            'EEEE, MMMM D, YYYY' => 'EEEE, MMMM D, YYYY',
            'YYYY-MM-DD' => 'YYYY-MM-DD',
            'DD/MM/YYYY' => 'DD/MM/YYYY',
            'D/M/YY' => 'D/M/YY',
            'YY-MM-DD' => 'YY-MM-DD',
            'M/DD/YY' => 'M/DD/YY',
            'DD-MMM-YY' => 'DD-MMM-YY',
            'DD-MMM-YYYY' => 'DD-MMM-YYYY',
            'M/D/YYYY' => 'M/D/YYYY',
            'M/D/YY' => 'M/D/YY',
            'MM/DD/YY' => 'MM/DD/YY',
            'MM/DD/YYYY' => 'MM/DD/YYYY',
            'EEEE, MMMM DD, YYYY' => 'EEEE, MMMM DD, YYYY',
            'MMMM-DD-YY' => 'MMMM-DD-YY',
            'MMMM DD, YYYY' => 'MMMM DD, YYYY',
            'MMMM, YYYY' => 'MMMM, YYYY',
        ];
    }
}
