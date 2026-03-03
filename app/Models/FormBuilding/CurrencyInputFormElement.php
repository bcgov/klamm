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
use Closure;

class CurrencyInputFormElement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'placeholder',
        'labelText',
        'hideLabel',
        'enableVarSub',
        'min',
        'max',
        'defaultValue',
    ];

    protected $casts = [
        'hideLabel' => 'boolean',
        'min' => 'integer',
        'max' => 'integer',
        'defaultValue' => 'float',
    ];

    protected $attributes = [
        'hideLabel' => false,
    ];
    /*
     * Format the value as currency
    */
    protected static function formatCurrency(string $target): \Closure
    {
        return function ($state, callable $set) use ($target) {
            if ($state === null) return;

            $raw = trim((string) $state);

            // Normalize incomplete forms to 0.00
            if (in_array($raw, ['-', '.', '-.'], true)) {
                $set($target, '0.00');
                return;
            }

            // Only allow plain decimals: optional leading '-', digits, optional single dot, digits
            // (Rejects scientific notation, commas, spaces, letters, etc.)
            if (!preg_match('/^-?\d*\.?\d*$/', $raw)) {
                return; // let validation flag invalid format
            }

            // Separate sign and body
            $neg = str_starts_with($raw, '-');
            $body = ltrim($raw, '-');

            // Split into integer and fractional parts
            $int = $body;
            $frac = '';
            if (strpos($body, '.') !== false) {
                [$int, $frac] = explode('.', $body, 2);
            }

            // Trim leading zeros in the integer part but keep at least one
            $int = ltrim($int, '0');
            if ($int === '') {
                $int = '0';
            }

            // If no fractional part → pad to 2 decimals
            if ($frac === '') {
                $out = ($neg && $int === '0') ? '0.00' : (($neg ? '-' : '') . $int . '.00');
                $set($target, $out);
                return;
            }

            // If 1 fractional digit → pad to 2
            if (preg_match('/^\d$/', $frac)) {
                $out = ($neg && $int === '0' && $frac === '0')
                    ? '0.00'
                    : (($neg ? '-' : '') . $int . '.' . $frac . '0');
                $set($target, $out);
                return;
            }

            // If exactly 2 fractional digits → keep as-is (normalize -0.00)
            if (preg_match('/^\d{2}$/', $frac)) {
                $out = ($neg ? '-' : '') . $int . '.' . $frac;
                if ($out === '-0.00') $out = '0.00';
                $set($target, $out);
                return;
            }

            // More than 2 fractional digits:
            // - If extras are all zeros, trim to 2 (e.g., 0.2500 → 0.25)
            // - Else leave unchanged (validation should flag), and DO NOT round
            if (strlen($frac) > 2) {
                $first2 = substr($frac, 0, 2);
                $extra  = substr($frac, 2);
                if (preg_match('/^0+$/', $extra)) {
                    $out = ($neg ? '-' : '') . $int . '.' . $first2;
                    if ($out === '-0.00') $out = '0.00';
                    $set($target, $out);
                }
                // else: non-zero beyond 2 decimals → do nothing (let validation show error)
                return;
            }
        };
    }

    /**
     * Get the Filament form schema for this element type.
     */
    public static function getFilamentSchema(bool $disabled = false): array
    {

        $noSci = 'not_regex:/[eE]/'; // forbid scientific notation
        $currencyRegex = 'regex:/^-?\d+(\.\d{1,2})?$/';

        return array_merge(
            SchemaHelper::getCommonCarbonFields($disabled),
            [
                Fieldset::make('Value')
                    ->schema([
                        SchemaHelper::getPlaceholderTextField($disabled)
                            ->columnSpan(3),

                        TextInput::make('elementable_data.defaultValue')
                            ->label('Default Value')
                            ->numeric()
                            ->nullable()
                            ->step(.01)
                            ->live(onBlur: true)
                            ->afterStateUpdated(self::formatCurrency('elementable_data.defaultValue'))
                            // Keep your base rules; add min/max comparisons only if present
                            ->rules(function (Get $get) use ($currencyRegex, $noSci) {
                                $rules = ['numeric', $currencyRegex, $noSci];

                                if (filled($get('elementable_data.min'))) {
                                    $rules[] = 'gte:elementable_data.min';
                                }
                                if (filled($get('elementable_data.max'))) {
                                    $rules[] = 'lte:elementable_data.max';
                                }

                                return $rules;
                            })
                            ->columnSpan(1)
                            ->disabled($disabled),

                        TextInput::make('elementable_data.min')
                            ->label('Minimum Value')
                            ->numeric()
                            ->nullable()
                            ->step(.01)
                            ->live(onBlur: true)
                            ->afterStateUpdated(self::formatCurrency('elementable_data.min'))
                            ->rules(function (Get $get) use ($currencyRegex, $noSci) {
                                $rules = ['numeric', $currencyRegex, $noSci];

                                // Only enforce min <= max if max is provided
                                if (filled($get('elementable_data.max'))) {
                                    $rules[] = 'lte:elementable_data.max';
                                }

                                return $rules;
                            })
                            ->columnSpan(1)
                            ->disabled($disabled),

                        TextInput::make('elementable_data.max')
                            ->label('Maximum Value')
                            ->numeric()
                            ->nullable()
                            ->step(.01)
                            ->live(onBlur: true)
                            ->afterStateUpdated(self::formatCurrency('elementable_data.max'))
                            ->rules(function (Get $get) use ($currencyRegex, $noSci) {
                                $rules = ['numeric', $currencyRegex, $noSci];

                                // Only enforce max >= min if min is provided
                                if (filled($get('elementable_data.min'))) {
                                    $rules[] = 'gte:elementable_data.min';
                                }

                                return $rules;
                            })
                            ->columnSpan(1)
                            ->disabled($disabled),
                    ])
                    ->columns(3),
            ]
        );
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
            'labelText' => $this->labelText,
            'hideLabel' => $this->hideLabel,
            'enableVarSub' => $this->enableVarSub,
            'min' => $this->min,
            'max' => $this->max,
            'defaultValue' => $this->defaultValue,
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
            'defaultValue' => null,
        ];
    }
}
