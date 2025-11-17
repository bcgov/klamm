<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;

class TextareaInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'enableVarSub',
        'rows',
        'cols',
        'maxCount',
        'defaultValue',
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
        return array_merge(
            SchemaHelper::getCommonCarbonFields($disabled),
            [
                Fieldset::make('Value')
                    ->schema([
                        SchemaHelper::getPlaceholderTextField($disabled),
                    TextInput::make('elementable_data.rows')
                        ->label('Number of Rows')
                        ->numeric()
                        ->default(3)
                        ->disabled($disabled),
                    TextInput::make('elementable_data.cols')
                        ->label('Number of Columns')
                        ->numeric()
                        ->disabled($disabled),
                    TextInput::make('elementable_data.maxCount')
                        ->label('Maximum Character Count')
                        ->numeric()
                        ->disabled($disabled),
                    TextInput::make('elementable_data.defaultValue')
                        ->label('Default Value')
                        ->disabled($disabled),
                    ])
                    ->columns(1),
            ]
        );
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
            'enableVarSub' => $this->enableVarSub,
            'rows' => $this->rows,
            'cols' => $this->cols,
            'maxCount' => $this->maxCount,
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
            'rows' => 3,
            'cols' => null,
            'maxCount' => null,
            'defaultValue' => '',
        ];
    }
}
