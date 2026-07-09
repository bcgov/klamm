<?php

namespace App\Models\FormBuilding;

use App\Filament\Forms\Helpers\NumericRules;
use App\Helpers\SchemaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;

use Closure;

class CurrencyInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'placeholder',
        'label_text',
        'hide_label',
        'enable_var_sub',
        'min',
        'max',
        'default_value',
    ];

    protected $casts = [
        'hide_label' => 'boolean',
        'min' => 'integer',
        'max' => 'integer',
        'default_value' => 'float',
    ];

    protected $attributes = [
        'hide_label' => false,
    ];

    /*
     * Format the value as currency
     */
    protected static function formatCurrency(string $target): Closure
    {
        return function ($state, callable $set) use ($target): void {
            if ($state === null)
                return;

            $raw = trim((string) $state);

            // Reject sci-notation, commas, or spaces (let validation show error)
            if (preg_match('/[eE, ]/', $raw)) {
                return;
            }

            // Collapse lone sign/dot or all-zero forms to "0.00"
            $isZeroish = static function (string $s): bool {
                $s = trim($s);
                if ($s === '')
                    return false;

                // remove leading sign
                $s = ltrim($s, "+-");

                // empty after sign or just a dot -> zero-ish
                if ($s === '' || $s === '.')
                    return true;

                // keep only digits and a single dot
                $clean = preg_replace('/[^\d.]/', '', $s) ?? '';
                if ($clean === '' || substr_count($clean, '.') > 1)
                    return false;

                // if all remaining digits are zeros, it's zero-ish
                $digitsOnly = str_replace('.', '', $clean);
                return $digitsOnly !== '' && preg_match('/^0+$/', $digitsOnly) === 1;
            };

            if ($isZeroish($raw)) {
                $set($target, '0.00');
                return;
            }

            // Shape check: optional sign, digits, optional single dot and digits
            // (allows ".5", "5.", "-.5", etc.)
            if (!preg_match('/^[+-]?\d*(?:\.\d*)?$/', $raw)) {
                // Not a plain numeric string -> leave it; validation will flag it.
                return;
            }

            // Extract sign once; operate on a signless body
            $neg = ($raw !== '' && $raw[0] === '-');
            $body = ltrim($raw, '+-');

            // Split into integer + fractional segments
            $int = $body;
            $frac = '';
            if (strpos($body, '.') !== false) {
                [$int, $frac] = explode('.', $body, 2);
            }

            // Normalize integer: strip extra leading zeros, keep at least one '0'
            $int = ltrim($int, '0');
            if ($int === '')
                $int = '0';

            // Fraction handling:
            // - If <= 2 digits: pad to exactly 2
            // - If > 2 digits: leave as-is (no rounding/trimming)
            if ($frac === '') {
                $frac = '00';
            } else {
                $len = strlen($frac);
                if ($len === 1) {
                    $frac = str_pad($frac, 2, '0'); // '5' -> '50'
                } elseif ($len >= 3) {
                    // leave as-is
                } // len == 2 -> keep as-is
            }

            // Final negative-zero normalization:
            // If magnitude is exactly zero (int == '0' and fraction all zeros),
            // drop the negative sign.
            $isZeroMagnitude = ($int === '0') && preg_match('/^0+$/', $frac) === 1;
            $prefix = ($neg && !$isZeroMagnitude) ? '-' : '';

            // Build output
            // - For <= 2 original fraction, it's exactly 2 now.
            // - For > 2, it's unchanged as typed.
            $out = $int . '.' . $frac;

            // Ensure canonical "0.00" for true zero when <= 2 frac
            if ($isZeroMagnitude && strlen($frac) <= 2) {
                $out = '0.00';
            }

            $set($target, $prefix . $out);
        };
    }

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {
        $noSci = 'not_regex:/[eE]/'; // forbid scientific notation
        $currencyRegex = 'regex:/^-?\d+(\.\d{1,2})?$/';

        return [
            SchemaHelper::getCommonCarbonFields($disabled),
            Fieldset::make('Value')
                ->schema([
                    SchemaHelper::getPlaceholderTextField($disabled)
                        ->columnSpan(3),
                    TextInput::make('elementable_data.default_value')
                        ->label('Default Value')
                        ->numeric()
                        ->nullable()
                        ->step(.01)
                        ->live(onBlur: true)
                        ->afterStateUpdated(self::formatCurrency('elementable_data.default_value'))
                        ->rules([$currencyRegex, $noSci])
                        ->rule(NumericRules::compareWith(
                            minPath: 'elementable_data.min',
                            maxPath: 'elementable_data.max',
                            options: [
                                'format' => fn(float $n) => number_format($n, 2, '.', ''),
                            ]
                        ))
                        ->columnSpan(1)
                        ->disabled($disabled),
                    TextInput::make('elementable_data.min')
                        ->label('Minimum Value')
                        ->numeric()
                        ->nullable()
                        ->step(.01)
                        ->live(onBlur: true)
                        ->afterStateUpdated(self::formatCurrency('elementable_data.min'))
                        ->rules([$currencyRegex, $noSci])
                        ->rule(NumericRules::compareWith(
                            minPath: null,
                            maxPath: 'elementable_data.max',
                            options: [
                                'format' => fn(float $n) => number_format($n, 2, '.', ''),
                            ]
                        ))
                        ->columnSpan(1)
                        ->disabled($disabled),
                    TextInput::make('elementable_data.max')
                        ->label('Maximum Value')
                        ->numeric()
                        ->nullable()
                        ->step(.01)
                        ->live(onBlur: true)
                        ->afterStateUpdated(self::formatCurrency('elementable_data.max'))
                        ->rules([$currencyRegex, $noSci])
                        ->rule(NumericRules::compareWith(
                            minPath: 'elementable_data.min',
                            maxPath: null,
                            options: [
                                'format' => fn(float $n) => number_format($n, 2, '.', ''),
                            ]
                        ))
                        ->columnSpan(1)
                        ->disabled($disabled),
                ])
                ->columns(3),
        ];
    }

    /**
     * Get the form element that owns this currency input element.
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
            'label_text' => $this->label_text,
            'hide_label' => $this->hide_label,
            'enable_var_sub' => $this->enable_var_sub,
            'min' => $this->min,
            'max' => $this->max,
            'default_value' => $this->default_value,
        ];
    }

    /**
     * Get default data for this element type when creating new instances.
     */
    public static function getDefaultData(): array
    {
        return [
            'placeholder' => '',
            'label_text' => '',
            'hide_label' => false,
            'enable_var_sub' => false,
            'min' => null,
            'max' => null,
            'default_value' => null,
        ];
    }
}
