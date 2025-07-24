<?php

namespace App\Models\FormBuilding;

use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CheckboxInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'labelText',
        'hideLabel',
        'defaultChecked',
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
            SchemaHelper::getLabelTextField($disabled)
                ->label('Checkbox Label')
                ->autocomplete(false)
                ->required(),
            SchemaHelper::getHideLabelToggle($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.defaultChecked')
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
        ];
    }
}
