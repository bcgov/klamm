<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;

class DateSelectInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'enableVarSub',
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
        'dateFormat' => 'YYYY-MMM-DD',
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
                        Select::make('elementable_data.dateFormat')
                            ->label('Date Format')
                            ->options(static::getDateFormats())
                            ->default('YYYY-MMM-DD')
                            ->required()
                            ->disabled($disabled),
                        DatePicker::make('elementable_data.minDate')
                            ->label('Minimum Date')
                            ->helperText('Earliest date users can select')
                            ->disabled($disabled),
                        DatePicker::make('elementable_data.maxDate')
                            ->label('Maximum Date')
                            ->helperText('Latest date users can select')
                            ->disabled($disabled),
                    ])
                    ->columns(1),
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
            'enableVarSub' => $this->enableVarSub,
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
            'YYYY-MMM-DD' => 'YYYY-MMM-DD',
            'YYYY/MM/DD' => 'YYYY/MM/DD',
            'YYYY/MMM/DD' => 'YYYY/MMM/DD',
            'YY-MM-DD' => 'YY-MM-DD',
            'DD/MM/YYYY' => 'DD/MM/YYYY',
            'D/M/YY' => 'D/M/YY',
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

    /**
     * Convert date format from current format to Flatpickr format.
     * 
     * @param string $format The current date format
     * @return string The Flatpickr-compatible date format
     */
    public static function convertToFlatpickrFormat(string $format): string
    {
        $formatMapping = [
            'DD/MM/YY' => 'd/m/y',
            'D-MMM-YY' => 'j-M-y',
            'MMMM D, YYYY' => 'F j, Y',
            'EEEE, MMMM D, YYYY' => 'l, F j, Y',
            'YYYY-MM-DD' => 'Y-m-d',
            'YYYY-MMM-DD' => 'Y-M-d',
            'YY-MM-DD' => 'y-m-d',
            'DD/MM/YYYY' => 'd/m/Y',
            'D/M/YY' => 'j/n/y',
            'M/DD/YY' => 'n/d/y',
            'DD-MMM-YY' => 'd-M-y',
            'DD-MMM-YYYY' => 'd-M-Y',
            'M/D/YYYY' => 'n/j/Y',
            'M/D/YY' => 'n/j/y',
            'MM/DD/YY' => 'm/d/y',
            'MM/DD/YYYY' => 'm/d/Y',
            'EEEE, MMMM DD, YYYY' => 'l, F d, Y',
            'MMMM-DD-YY' => 'F-d-y',
            'MMMM DD, YYYY' => 'F d, Y',
            'MMMM, YYYY' => 'F, Y',
        ];

        return $formatMapping[$format] ?? $format;
    }

    /**
     * Convert date format from Flatpickr format to the formats used in Klamm.
     * 
     * @return string The Flatpickr-compatible date format
     * @param string $format The Klamm date format
     */
    public static function convertFromFlatpickrFormat(string $format): string
    {
        $formatMapping = [
            'd/m/y' => 'DD/MM/YY',
            'j-M-y' => 'D-MMM-YY',
            'F j, Y' => 'MMMM D, YYYY',
            'l, F j, Y' => 'EEEE, MMMM D, YYYY',
            'Y-m-d' => 'YYYY-MM-DD',
            'Y-M-d' => 'YYYY-MMM-DD',
            'y-m-d' => 'YY-MM-DD',
            'd/m/Y' => 'DD/MM/YYYY',
            'j/n/y' => 'D/M/YY',
            'n/d/y' => 'M/DD/YY',
            'd-M-y' => 'DD-MMM-YY',
            'd-M-Y' => 'DD-MMM-YYYY',
            'n/j/Y' => 'M/D/YYYY',
            'n/j/y' => 'M/D/YY',
            'm/d/y' => 'MM/DD/YY',
            'm/d/Y' => 'MM/DD/YYYY',
            'l, F d, Y' => 'EEEE, MMMM DD, YYYY',
            'F-d-y' => 'MMMM-DD-YY',
            'F d, Y' => 'MMMM DD, YYYY',
            'F, Y' => 'MMMM, YYYY',
        ];

        return $formatMapping[$format] ?? $format;
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'placeholder' => '',
            'labelText' => 'Date Select Input',
            'hideLabel' => false,
            'enableVarSub' => false,
            'minDate' => null,
            'maxDate' => null,
            'dateFormat' => 'YYYY-MMM-DD',
        ];
    }
}
