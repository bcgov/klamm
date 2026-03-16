<?php

namespace App\Models\FormBuilding;

use App\Filament\Forms\Helpers\NumericRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Actions\Action;
use App\Helpers\SchemaHelper;
use Filament\Forms\Get;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

use Closure;

class ContainerFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'container_type',
        'is_repeatable',
        'repeater_item_label',
        'min_repeats',
        'max_repeats',
        'legend',
        'enableVarSub',
        'level'
    ];

    protected $casts = [
        'is_repeatable' => 'boolean',
        'min_repeats' => 'integer',
        'max_repeats' => 'integer',
    ];

    protected $attributes = [
        'container_type' => 'section',
        'is_repeatable' => false,
    ];

    protected static function formatInteger(string $target): Closure
    {
        return function ($state, callable $set, Get $get) use ($target) {
            if ($state === null) return;

            $raw = trim((string) $state);

            // Do not "fix" scientific notation or thousands separators; let validation reject them.
            if (preg_match('/[eE, ]/', $raw)) {
                return;
            }

            // Strip everything except digits
            $digits = preg_replace('/\D/', '', $raw) ?? '';

            // Remove leading zeros
            $digits = ltrim($digits, '0');

            // If nothing remains (e.g., input was "0", "000", "-", etc.), normalize to null
            if ($digits === '') {
                $set($target, null);
                return;
            }

            // Positive integer (no sign), no leading zeros
            $set($target, $digits);
        };
    }

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        $noSci = 'not_regex:/[eE]/'; // forbid scientific notation

        return [
            Fieldset::make('Container Settings')
                ->schema([
                    Select::make('elementable_data.container_type')
                        ->label('Container Type')
                        ->columnSpan(2)
                        ->options(static::getContainerTypes())
                        ->default('section')
                        ->required(true)
                        ->disabled($disabled),
                    Toggle::make('elementable_data.is_repeatable')
                        ->label('Repeatable')
                        ->columnSpan(2)
                        ->helperText('Allow users to add multiple instances of this container')
                        ->default(false)
                        ->live()
                        ->disabled($disabled),
                    TextInput::make('elementable_data.repeater_item_label')
                        ->label('Repeater Item Label')
                        ->columnSpan(2)
                        ->helperText('Label for individual repeater items (e.g., "Item", "Entry")')
                        ->disabled($disabled)
                        ->visible(fn(callable $get) => $get('elementable_data.is_repeatable')),
                    TextInput::make('elementable_data.min_repeats')
                        ->label('Minimum Repeats')
                        ->integer()
                        ->nullable()
                        ->minValue(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(self::formatInteger('elementable_data.min_repeats'))
                        ->rule($noSci)
                        ->rule(NumericRules::compareWith(
                            minPath: null,
                            maxPath: 'elementable_data.max_repeats',
                            options: [
                                // Ensure error message always show integers
                                'format' => fn(float $n) => number_format($n, 0, '.', ''),
                            ]
                        ))
                        ->disabled($disabled)
                        ->visible(fn(callable $get) => $get('elementable_data.is_repeatable')),
                    TextInput::make('elementable_data.max_repeats')
                        ->label('Maximum Repeats')
                        ->integer()
                        ->nullable()
                        ->minValue(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(self::formatInteger('elementable_data.max_repeats'))
                        ->rule($noSci)
                        ->rule(NumericRules::compareWith(
                            minPath: 'elementable_data.min_repeats',
                            maxPath: null,
                            options: [
                                // Ensure error message always show integers
                                'format' => fn(float $n) => number_format($n, 0, '.', ''),
                            ]
                        ))
                        ->disabled($disabled)
                        ->visible(fn(callable $get) => $get('elementable_data.is_repeatable')),
                ])
                ->columns(2),
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
            'min_repeats' => $this->min_repeats,
            'max_repeats' => $this->max_repeats,
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
            'min_repeats' => null,
            'max_repeats' => null,
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
