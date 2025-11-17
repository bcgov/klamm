<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Toggle;

class CheckboxInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'labelText',
        'hideLabel',
        'defaultChecked',
        'enableVarSub',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'defaultChecked' => 'boolean',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'defaultChecked' => false,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            Fieldset::make('Field Label')
                ->schema([
                    SchemaHelper::getLabelTextField($disabled)
                        ->label('Checkbox Label')
                        ->autocomplete(false)
                        ->required(),
                    SchemaHelper::getEnableVariableSubstitutionToggle($disabled),
                    SchemaHelper::getHideLabelToggle($disabled),
                ])
                ->columns(1),
                Toggle::make('elementable_data.defaultChecked')
                    ->label('Default Checked')
                    ->default(false)
                    ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this checkbox input element.
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
            'defaultChecked' => $this->defaultChecked,
            'enableVarSub' => $this->enableVarSub,
        ];
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'hideLabel' => false,
            'defaultChecked' => false,
            'labelText' => '',
            'enableVarSub' => false,
        ];
    }
}
