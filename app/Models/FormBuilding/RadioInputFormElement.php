<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class RadioInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'labelText',
        'hideLabel',
        'enableVarSub',
        'defaultSelected',
        'labelPosition',
        'orientation',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'labelText' => '',
        'labelPosition' => 'right',
        'orientation' => 'vertical',
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            Fieldset::make('Field Label')
                ->schema([
                    SchemaHelper::getLabelTextField($disabled)->required(),
                    SchemaHelper::getEnableVariableSubstitutionToggle($disabled),
                    SchemaHelper::getHideLabelToggle($disabled),
                    Select::make('elementable_data.labelPosition')
                        ->label('Label Position')
                        ->options([
                            'left' => 'Left',
                            'right' => 'Right',
                        ])
                        ->default('right')
                        ->visible(fn(callable $get): bool => !$get('elementable_data.hideLabel'))
                        ->disabled($disabled),
                ])
                ->columns(1),
            Fieldset::make('Values')
                ->schema([
                    Select::make('elementable_data.orientation')
                        ->label('Orientation')
                        ->options([
                            'horizontal' => 'Horizontal',
                            'vertical' => 'Vertical',
                        ])
                        ->default('vertical')
                        ->disabled($disabled),
                    SchemaHelper::getOptionsDefaultSelectedSelect($disabled),
                    SchemaHelper::getOptionsRepeater($disabled),
                ])
                ->columns(1),
        ];
    }

    /**
     * Get the form element that owns this radio input element.
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
            'defaultSelected' => $this->defaultSelected,
            'labelPosition' => $this->labelPosition,
            'orientation' => $this->orientation,
        ];
    }

    /**
     * Get the options for this radio input.
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
            'labelPosition' => 'right',
            'orientation' => 'vertical',
            'options' => [
                ['label' => 'True', 'value' => 'true'],
                ['label' => 'False', 'value' => 'false'],
            ],
        ];
    }
}
