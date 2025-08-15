<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ButtonInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'text',
        'kind',
    ];

    protected $casts = [
        'kind' => 'string',
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.text')
                ->label('Button Text')
                ->default('Submit')
                ->required(true)
                ->autocomplete(false)
                ->disabled($disabled),
            \Filament\Forms\Components\Select::make('elementable_data.kind')
                ->label('Button Kind')
                ->options(static::getButtonTypes())
                ->default('primary')
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
            'text' => $this->text,
            'kind' => $this->kind,
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

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'text' => 'Submit',
            'kind' => 'primary',
        ];
    }
}
