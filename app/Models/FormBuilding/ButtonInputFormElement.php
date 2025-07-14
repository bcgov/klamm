<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ButtonInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'button_type',
    ];

    protected $casts = [
        'button_type' => 'string',
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.label')
                ->label('Button Text')
                ->default('Submit')
                ->disabled($disabled),
            \Filament\Forms\Components\Select::make('elementable_data.button_type')
                ->label('Button Type')
                ->options(static::getButtonTypes())
                ->default('submit')
                ->disabled($disabled),
        ];
    }

    /**
     * Get the form element that owns this button input element.
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
            'label' => $this->label,
            'button_type' => $this->button_type,
        ];
    }

    /**
     * Get available button types.
     */
    public static function getButtonTypes(): array
    {
        return [
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'tertiary' => 'Tertiary',
            'danger' => 'Danger',
            'danger--tertiary' => 'Danger Tertiary',
            'danger--ghost' => 'Danger Ghost',
            'ghost' => 'Ghost',
        ];
    }
}
