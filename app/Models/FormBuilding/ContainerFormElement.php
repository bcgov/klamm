<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Actions\Action;

class ContainerFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'container_type',
        'is_repeatable',
        'repeater_item_label',
        'legend',
        'level'
    ];

    protected $casts = [
        'is_repeatable' => 'boolean',
    ];

    protected $attributes = [
        'container_type' => 'section',
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
            \Filament\Forms\Components\Select::make('elementable_data.level')
                ->label('Label Level')
                ->options([
                    '2' => 'H2',
                    '3' => 'H3',
                    '4' => 'H4',
                    '5' => 'H5',
                    '6' => 'H6',
                ])
                ->placeholder('No override (default styling)')
                ->nullable()
                ->helperText('Optional level override for the label (e.g., h2, h3, etc.)')
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.legend')
                ->label('Legend/Title')
                ->helperText('Optional title for the container')
                ->suffixAction(Action::make('generate_label_text')
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip('Regenerate from Element Name')
                    ->action(function (callable $set, callable $get) {
                        $name = $get('name');
                        if (!empty($name)) {
                            $set('elementable_data.legend', $name);
                        }
                    }))
                ->disabled($disabled),
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
            'is_repeatable' => $this->is_repeatable,
            'repeater_item_label' => $this->repeater_item_label,
            'legend' => $this->legend,
            'level' => $this->level,
        ];
    }

    /**
     * Get available container types.
     */
    public static function getContainerTypes(): array
    {
        return [
            'section' => 'Section',
            'fieldset' => 'Fieldset',
            'page' => 'Page',
            'header' => 'Header',
            'footer' => 'Footer',
        ];
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'container_type' => 'section',
            'is_repeatable' => false,
            'legend' => '',
            'repeater_item_label' => '',
            'level' => null,
        ];
    }

    /**
     * Check if this container can have children.
     */
    public function canHaveChildren(): bool
    {
        return in_array($this->container_type, ['page', 'fieldset', 'section', 'header', 'footer']);
    }
}
