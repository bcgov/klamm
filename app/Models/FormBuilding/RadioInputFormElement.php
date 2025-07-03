<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RadioInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'visible_label',
        'default_value',
    ];

    protected $casts = [
        'visible_label' => 'boolean',
    ];

    protected $attributes = [
        'visible_label' => true,
        'label' => '',
    ];

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('elementable_data.label')
                ->label('Field Label')
                ->required()
                ->disabled($disabled),
            \Filament\Forms\Components\Toggle::make('elementable_data.visible_label')
                ->label('Show Label')
                ->default(true)
                ->disabled($disabled),
            \Filament\Forms\Components\TextInput::make('elementable_data.default_value')
                ->label('Default Selected Value')
                ->disabled($disabled),
            \Filament\Forms\Components\Repeater::make('elementable_data.options')
                ->label('Options')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('label')
                        ->label('Option Label')
                        ->required()
                        ->columnSpan(2),
                    \Filament\Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->columnSpan(2),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->addActionLabel('Add Option')
                ->reorderableWithButtons()
                ->collapsible()
                ->itemLabel(fn(array $state): ?string => $state['label'] ?? 'Option')
                ->disabled($disabled)
                ->minItems(1),
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
            'label' => $this->label,
            'visible_label' => $this->visible_label,
            'default+value' => $this->default_value,
        ];
    }

    /**
     * Get the options for this radio input.
     */
    public function options(): MorphMany
    {
        return $this->morphMany(SelectOptionFormElement::class, 'optionable')->orderBy('order');
    }
}
