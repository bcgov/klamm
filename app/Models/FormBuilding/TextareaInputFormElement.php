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
        'label_text',
        'hide_label',
        'enable_var_sub',
        'rows',
        'cols',
        'max_count',
        'default_value',
    ];

    protected $casts = [
        'hide_label' => 'boolean',
        'rows' => 'integer',
        'cols' => 'integer',
        'max_count' => 'integer',
    ];

    protected $attributes = [
        'hide_label' => false,
        'rows' => 3,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            SchemaHelper::getCommonCarbonFields($disabled),
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
                    TextInput::make('elementable_data.max_count')
                        ->label('Maximum Character Count')
                        ->numeric()
                        ->disabled($disabled),
                    TextInput::make('elementable_data.default_value')
                        ->label('Default Value')
                        ->maxLength(255)
                        ->disabled($disabled),
                ])
                ->columns(1),
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
            'label_text' => $this->label_text,
            'hide_label' => $this->hide_label,
            'enable_var_sub' => $this->enable_var_sub,
            'rows' => $this->rows,
            'cols' => $this->cols,
            'max_count' => $this->max_count,
            'default_value' => $this->default_value,
        ];
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'placeholder' => '',
            'label_text' => '',
            'hide_label' => false,
            'enable_var_sub' => false,
            'rows' => 3,
            'cols' => null,
            'max_count' => null,
            'default_value' => '',
        ];
    }
}
