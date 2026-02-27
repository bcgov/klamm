<?php

namespace App\Models\FormBuilding;

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
            if (preg_match('/[eE, ]/', $raw)) {
                return;
            }

            // Treat "-", "-0", "-0.0", etc. as zero (both modes)
            if ($raw === '-' || preg_match('/^-\s*0*(?:\.0*)?$/', $raw)) {
                $set($target, '0');
                return;
            }

            if ($mask === 'integer') {
                // ---- INTEGER MODE (truncate; do not round), negatives allowed ----
                // If it's not numeric at all, leave it for validation.
                if (!is_numeric($raw)) return;

                // Keep sign if present
                $sign = ($raw !== '' && $raw[0] === '-') ? '-' : '';
                $body = ltrim($raw, '-');

                // Keep digits and dot; then take the integer part before any dot
                $tmp = preg_replace('/[^0-9.]/', '', $body) ?? '';
                $int = explode('.', $tmp, 2)[0] ?? '';
                $int = preg_replace('/\D/', '', $int) ?? ''; // safety
                $int = ltrim($int, '0');

                // Empty integer part becomes 0
                if ($int === '') $int = '0';

                // Avoid "-0"
                if ($int === '0') $sign = '';

                $set($target, $sign . $int);
                return;
            }

            // ---- DECIMAL MODE (no forced rounding), negatives allowed ----
            // Normalize incomplete forms to zero-ish decimal
            if (in_array($raw, ['.', '-.'], true)) {
                $set($target, '0');
                return;
            }

            // If not numeric at all, let validation handle it.
            if (!is_numeric($raw)) return;

            // Keep only one leading '-' and only the first '.'
            $val = preg_replace('/[^\d.\-]/', '', $raw) ?? '';
            $val = preg_replace('/(?!^)-/', '', $val) ?? '';     // remove extra '-' not at start
            $val = preg_replace('/\.(?=.*\.)/', '', $val) ?? ''; // keep only first '.'

            // If empty or just "-", normalize to 0
            if ($val === '' || $val === '-') {
                $set($target, '0');
                return;
            }

            $sign = ($val[0] === '-') ? '-' : '';
            $tmp  = ltrim($val, '-');

            if (strpos($tmp, '.') !== false) {
                [$int, $frac] = explode('.', $tmp, 2);

                // sanitize integer and fractional parts
                $int  = preg_replace('/\D/', '', $int) ?? '';
                $frac = preg_replace('/\D/', '', $frac) ?? '';

                // Normalize leading zeros in integer part
                $int = ltrim($int, '0');
                if ($int === '') $int = '0';

                // Trim trailing zeros in fractional part; drop dot if empty
                $frac = rtrim($frac, '0');

                $normalized = $frac === ''
                    ? $sign . $int
                    : $sign . $int . '.' . $frac;
            } else {
                // No dot: integer-like in decimal context
                $int = preg_replace('/\D/', '', $tmp) ?? '';
                $int = ltrim($int, '0');
                if ($int === '') $int = '0';
                $normalized = $sign . $int;
            }

            // Normalize "-0"
            if ($normalized === '-0') {
                $normalized = '0';
            }

            $set($target, $normalized);
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
                                    ? ['nullable', 'numeric', $noSci, $plainDecimal]
                                    : ['nullable', 'integer'];

                                if (filled($get('elementable_data.min'))) {
                                    $rules[] = 'gte:elementable_data.min';
                                }
                                if (filled($get('elementable_data.max'))) {
                                    $rules[] = 'lte:elementable_data.max';
                                }

                                return $rules;
                            })
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
                                    ? ['nullable', 'numeric', $noSci, $plainDecimal]
                                    : ['nullable', 'integer'];

                                // Only require min <= max if max is provided
                                if (filled($get('elementable_data.max'))) {
                                    $rules[] = 'lte:elementable_data.max';
                                }

                                return $rules;
                            })
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
                                    ? ['nullable', 'numeric', $noSci, $plainDecimal]
                                    : ['nullable', 'integer'];

                                // Only require max >= min if min is provided
                                if (filled($get('elementable_data.min'))) {
                                    $rules[] = 'gte:elementable_data.min';
                                }

                                return $rules;
                            })
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
                                    $currentStep = $get('elementable_data.step');
                                    $set('elementable_data.step', filled($currentStep) ? $currentStep : 0.01);
                                }
                            }),
                        TextInput::make('elementable_data.step')
                            ->required()
                            ->label('Step Size')
                            ->numeric()
                            ->default(1)
                            ->live(onBlur: true)
                            // Ensure UI "step" attribute makes sense (1 for integer; any step otherwise)
                            ->step(fn(Get $get) => $isDecimal($get) ? null : 1)
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
