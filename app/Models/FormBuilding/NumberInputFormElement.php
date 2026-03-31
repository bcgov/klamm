<?php

namespace App\Models\FormBuilding;

use App\Filament\Forms\Helpers\NumericRules;
use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Get;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Closure;

class NumberInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'enableVarSub',
        'min',
        'max',
        'step',
        'defaultValue',
        'maskType',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'min' => 'integer',
        'max' => 'integer',
        'step' => 'float',
        'defaultValue' => 'float',
    ];

    protected $attributes = [
        'hideLabel' => false,
        'step' => 1,
        'maskType' => 'integer',
    ];

    /**
     * Format the number input according to the selected mask type (integer or decimal)
     */
    protected static function formatNumberByMask(string $target): Closure
    {
        return function ($state, callable $set, Get $get) use ($target) {
            if ($state === null) return;

            $raw  = trim((string) $state);
            $mask = strtolower((string) ($get('elementable_data.maskType') ?? 'integer')); // 'integer' | 'decimal'

            // Do not "fix" scientific notation or thousands separators; let validation reject them.
            if (preg_match('/[eE, ]/', $state)) {
                return;
            }

            // Treat "-", "-0", "-0.0", etc. as zero (both modes)

            $isZeroish = static function (string $s): bool {
                $s = trim($s);
                if ($s === '') return false;

                $s = ltrim($s, "+-");
                if ($s === '' || $s === '.') return true;

                $clean = preg_replace('/[^\d.]/', '', $s) ?? '';
                if ($clean === '' || substr_count($clean, '.') > 1) return false;

                $digitsOnly = str_replace('.', '', $clean);
                return $digitsOnly !== '' && preg_match('/^0+$/', $digitsOnly) === 1;
            };

            if ($isZeroish($raw)) {
                $set($target, '0');
                return;
            }

            // Only allow plain numeric form at this stage
            if (!preg_match('/^[+-]?\d*(?:\.\d*)?$/', $raw)) {
                // Not a plain numeric string → leave it; validation will flag it.
                return;
            }

            // Extract sign once; operate on a signless body
            $sign = ($raw !== '' && $raw[0] === '-') ? '-' : '';
            $body = ltrim($raw, '+-');

            // INTEGER MODE (string-only truncation; no numeric conversions)
            if ($mask === 'integer') {
                // take integer part before any dot
                $int = explode('.', $body, 2)[0] ?? '';

                // strip leading zeros; keep at least one '0'
                $int = ltrim($int, '0');
                if ($int === '') {
                    $int = '0';
                }

                // avoid "-0"
                if ($int === '0') {
                    $sign = '';
                }

                $set($target, $sign . $int);
                return;
            }

            // DECIMAL MODE (string-only, unlimited decimals, trim extras)
            if (strpos($body, '.') !== false) {
                [$int, $frac] = explode('.', $body, 2);

                // normalize leading zeros in integer part
                $int = ltrim($int, '0');
                if ($int === '') $int = '0';

                // trim trailing zeros in fractional part; drop the dot if empty
                $frac = rtrim($frac, '0');

                $out = ($frac === '') ? $int : ($int . '.' . $frac);
            } else {
                // integer-like in decimal context
                $int = preg_replace('/\D/', '', $body) ?? '';
                $int = ltrim($int, '0');
                if ($int === '') $int = '0';
                $out = $int;
            }

            // avoid "-0"
            if ($out === '0') {
                $sign = '';
            }

            $set($target, $sign . $out);
        };
    }


    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {

        $isDecimal = fn(Get $get) => strtolower($get('elementable_data.maskType') ?? 'integer') === 'decimal';

        $noSci = 'not_regex:/[eE]/';                       // forbid scientific notation
        $plainDecimal = 'regex:/^-?\d+(\.\d+)?$/';         // allow optional leading '-', digits, and one dot


        return array_merge(
            SchemaHelper::getCommonCarbonFields($disabled),
            [
                Fieldset::make('Value')
                    ->schema([
                        SchemaHelper::getPlaceholderTextField($disabled)
                            ->columnSpan(6),
                        TextInput::make('elementable_data.defaultValue')
                            ->label('Default Value')
                            ->numeric()
                            ->nullable()
                            ->step(fn(Get $get) => $get('elementable_data.step') ?? 1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(self::formatNumberByMask('elementable_data.defaultValue'))
                            ->rules(function (Get $get) use ($isDecimal, $noSci, $plainDecimal) {
                                $rules = $isDecimal($get)
                                    ? ['numeric', $noSci, $plainDecimal]
                                    : ['integer'];
                                return $rules;
                            })
                            ->rule(NumericRules::compareWith(
                                minPath: 'elementable_data.min',
                                maxPath: 'elementable_data.max',
                            ))
                            ->columnSpan(2)
                            ->disabled($disabled),
                        TextInput::make('elementable_data.min')
                            ->label('Minimum Value')
                            ->numeric()
                            ->nullable()
                            ->step(fn(Get $get) => $get('elementable_data.step') ?? 1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(self::formatNumberByMask('elementable_data.min'))
                            ->rules(function (Get $get) use ($isDecimal, $noSci, $plainDecimal) {
                                $rules = $isDecimal($get)
                                    ? ['numeric', $noSci, $plainDecimal]
                                    : ['integer'];
                                return $rules;
                            })
                            ->rule(NumericRules::compareWith(
                                minPath: null,
                                maxPath: 'elementable_data.max',
                            ))
                            ->columnSpan(2)
                            ->disabled($disabled),
                        TextInput::make('elementable_data.max')
                            ->label('Maximum Value')
                            ->numeric()
                            ->nullable()
                            ->step(fn(Get $get) => $get('elementable_data.step') ?? 1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(self::formatNumberByMask('elementable_data.max'))
                            ->rules(function (Get $get) use ($isDecimal, $noSci, $plainDecimal) {
                                $rules = $isDecimal($get)
                                    ? ['numeric', $noSci, $plainDecimal]
                                    : ['integer'];
                                return $rules;
                            })
                            ->rule(NumericRules::compareWith(
                                minPath: 'elementable_data.min',
                                maxPath: null,
                            ))
                            ->columnSpan(2)
                            ->disabled($disabled),
                        ToggleButtons::make('elementable_data.maskType')
                            ->label('Input Mask Type')
                            ->options([
                                'integer' => 'Integer',
                                'decimal' => 'Decimal',
                            ])
                            ->inline()
                            ->default('integer')
                            ->live()
                            ->columnSpan(3)
                            ->afterStateUpdated(function (string $state, callable $set, Get $get) {
                                if ($state === 'integer') {
                                    // Force step = 1 for integer mode
                                    $set('elementable_data.step', 1);

                                    // Coerce existing values to integers (if set)
                                    foreach (['defaultValue', 'min', 'max'] as $key) {
                                        $path = "elementable_data.$key";
                                        $val = $get($path);
                                        if (filled($val)) {
                                            $set($path, (int) round((float) $val));
                                        }
                                    }
                                } else {
                                    // Decimal mode: keep or set a reasonable decimal step
                                    $set('elementable_data.step', 0.01);
                                }
                            }),
                        TextInput::make('elementable_data.step')
                            ->required()
                            ->label('Step Size')
                            ->numeric()
                            ->default(1)
                            ->live(onBlur: true)
                            // Ensure UI "step" attribute makes sense (1 for integer; any step otherwise)
                            ->step(fn(Get $get) => $isDecimal($get) ? "any" : 1)
                            ->afterStateUpdated(self::formatNumberByMask('elementable_data.step'))
                            // Validation: integer & >=1 in integer mode; numeric & >0 in decimal mode
                            ->rule(fn(Get $get) => $isDecimal($get)
                                ? [
                                    'numeric',
                                    'gt:0',
                                    $noSci,
                                    'regex:/^\d+(\.\d+)?$/',      // only digits and one dot
                                ]
                                : ['integer', 'min:1'])
                            ->columnSpan(3)
                            ->disabled($disabled),
                    ])
                    ->columns(6),
            ]
        );
    }

    /**
     * Get the form element that owns this number input element.
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
            'placeholder' => $this->placeholder,
            'labelText' => $this->labelText,
            'hideLabel' => $this->hideLabel,
            'enableVarSub' => $this->enableVarSub,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'defaultValue' => $this->defaultValue,
            'maskType' => $this->maskType,
        ];
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'placeholder' => '',
            'labelText' => '',
            'hideLabel' => false,
            'enableVarSub' => false,
            'min' => null,
            'max' => null,
            'step' => 1,
            'defaultValue' => null,
            'maskType' => 'integer',
        ];
    }
}
