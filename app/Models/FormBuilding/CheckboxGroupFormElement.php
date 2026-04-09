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

class CheckboxGroupFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'labelText',
        'hideLabel',
        'enableVarSub',
        'defaultSelected',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'defaultSelected' => 'array',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'labelText' => '',
        'defaultSelected' => null,
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
                    SchemaHelper::getOptionsDefaultSelectedSelect($disabled, true),
                    SchemaHelper::getOptionsRepeater($disabled),
                ])
                ->columns(1),
        ];
    }

    /**
     * Get the form element that owns this checkbox group input element.
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
            'labelText' => $this->labelText,
            'hideLabel' => $this->hideLabel,
            'enableVarSub' => $this->enableVarSub,
            'defaultSelected' => $this->defaultSelected ?? [],
        ];
    }

    /**
     * Get the options for this checkbox group.
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
            'hideLabel' => false,
            'labelText' => '',
            'enableVarSub' => false,
            'defaultSelected' => [],
            'options' => [
                ['label' => 'Option 1', 'value' => 'option_1'],
                ['label' => 'Option 2', 'value' => 'option_2'],
            ],
        ];
    }
}
