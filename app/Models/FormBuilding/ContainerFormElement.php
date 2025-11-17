<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Actions\Action;
use App\Helpers\SchemaHelper;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ContainerFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'container_type',
        'is_repeatable',
        'repeater_item_label',
        'legend',
        'enableVarSub',
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
            Fieldset::make('Container Settings')
                ->schema([
                    Select::make('elementable_data.container_type')
                        ->label('Container Type')
                        ->options(static::getContainerTypes())
                        ->default('section')
                        ->required(true)
                        ->disabled($disabled),
                    Toggle::make('elementable_data.is_repeatable')
                        ->label('Repeatable')
                        ->helperText('Allow users to add multiple instances of this container')
                        ->default(false)
                        ->live()
                        ->disabled($disabled),
                    TextInput::make('elementable_data.repeater_item_label')
                        ->label('Repeater Item Label')
                        ->helperText('Label for individual repeater items (e.g., "Item", "Entry")')
                        ->disabled($disabled)
                        ->visible(fn(callable $get) => $get('elementable_data.is_repeatable')),
                ])
                ->columns(1),
            Fieldset::make('Label')
                ->schema([
                    Select::make('elementable_data.level')
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
                    TextInput::make('elementable_data.legend')
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
                    SchemaHelper::getEnableVariableSubstitutionToggle($disabled),
                ])
                ->columns(1),
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
            'enableVarSub' => $this->enableVarSub,
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
            'enableVarSub' => false,
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
