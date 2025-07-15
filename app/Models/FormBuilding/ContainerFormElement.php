<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ContainerFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'container_type',
        'collapsible',
        'collapsed_by_default',
        'is_repeatable',
        'repeater_item_label',
        'legend',
    ];

    protected $casts = [
        'collapsible' => 'boolean',
        'collapsed_by_default' => 'boolean',
        'is_repeatable' => 'boolean',
    ];

    protected $attributes = [
        'collapsible' => false,
        'collapsed_by_default' => false,
        'is_repeatable' => false,
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\Select::make('elementable_data.container_type')
                ->label('Container Type')
                ->options(static::getContainerTypes())
                ->default('section')
                ->required(true)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.legend')
                ->label('Legend/Title')
                ->helperText('Optional title for the container')
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.collapsible')
                ->label('Collapsible')
                ->helperText('Allow users to expand/collapse this container')
                ->default(false)
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.collapsed_by_default')
                ->label('Collapsed by Default')
                ->helperText('Start with container collapsed')
                ->default(false)
                ->disabled($disabled)
                ->visible(fn(callable $get) => $get('elementable_data.collapsible')),
            \Filament\Forms\Components\Toggle::make('elementable_data.is_repeatable')
                ->label('Repeatable')
                ->helperText('Allow users to add multiple instances of this container')
                ->default(false)
                ->live()
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.repeater_item_label')
                ->label('Repeater Item Label')
                ->helperText('Label for individual repeater items (e.g., "Item", "Entry")')
                ->disabled($disabled)
                ->visible(fn(callable $get) => $get('elementable_data.is_repeatable')),
        ];
    }

    /**
     * Get the form element that owns this container element.
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
            'container_type' => $this->container_type,
            'collapsible' =>         $this->collapsible,
            'collapsed_by_default' =>         $this->collapsed_by_default,
            'is_repeatable' =>         $this->is_repeatable,
            'repeater_item_label' =>         $this->repeater_item_label,
            'legend' =>         $this->legend,
        ];
    }

    /**
     * Get available container types.
     */
    public static function getContainerTypes(): array
    {
        return [
            'page' => 'Page',
            'fieldset' => 'Fieldset',
            'section' => 'Section',
        ];
    }

    /**
     * Check if this container can have children.
     */
    public function canHaveChildren(): bool
    {
        return in_array($this->container_type, ['page', 'fieldset', 'section']);
    }
}
