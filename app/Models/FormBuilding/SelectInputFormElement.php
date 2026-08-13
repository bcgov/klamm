<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Fieldset;

class SelectInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'label_text',
        'hide_label',
        'enable_var_sub',
        'default_selected',
    ];

    protected $casts = [
        'hide_label' => 'boolean',
    ];

    protected $attributes = [
        'hide_label' => false,
        'label_text' => '',
        'default_selected' => null,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            SchemaHelper::getCommonCarbonFields($disabled, true),
            Fieldset::make('Values')
                ->schema([
                    SchemaHelper::getOptionsDefaultSelectedSelect($disabled),
                    SchemaHelper::getOptionsRepeater($disabled),
                ])
                ->columns(1),
        ];
    }

    /**
     * Get the form element that owns this select input element.
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
            'label_text' => $this->label_text,
            'hide_label' => $this->hide_label,
            'enable_var_sub' => $this->enable_var_sub,
            'default_selected' => $this->default_selected,
        ];
    }

    /**
     * Get the options for this select input.
     */
    public function options(): MorphMany
    {
        return $this->morphMany(SelectOptionFormElement::class, 'optionable')->orderBy('order');
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'hide_label' => false,
            'label_text' => '',
            'enable_var_sub' => false,
            'default_selected' => null,
            'options' => [
                ['label' => 'True', 'value' => 'true'],
                ['label' => 'False', 'value' => 'false'],
            ],
        ];
    }
}
