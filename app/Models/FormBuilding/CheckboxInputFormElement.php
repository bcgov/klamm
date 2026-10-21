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
        'label_text',
        'hide_label',
        'default_checked',
        'enable_var_sub',
    ];

    protected $casts = [
        'hide_label' => 'boolean',
        'default_checked' => 'boolean',
    ];

    protected $attributes = [
        'hide_label' => false,
        'default_checked' => false,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            SchemaHelper::getCommonCarbonFields($disabled, true),
            Toggle::make('elementable_data.default_checked')
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
            'label_text' => $this->label_text,
            'hide_label' => $this->hide_label,
            'default_checked' => $this->default_checked,
            'enable_var_sub' => $this->enable_var_sub,
        ];
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'hide_label' => false,
            'default_checked' => false,
            'label_text' => '',
            'enable_var_sub' => false,
        ];
    }
}
