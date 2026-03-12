<?php

namespace App\Filament\Forms\Helpers;


use Closure;
use Filament\Forms\Get;

class NumericRules
{
    /**
     * Returns a Filament closure rule that compares the current numeric field
     * against optional min/max fields in the form state. Works robustly with Livewire partial
     * validation (on blur) because it reads sibling values from $get (form state).
     *
     * @param  string|null  $minPath   Dot-notated path to the min field (e.g., 'elementable_data.min') or null to skip.
     * @param  string|null  $maxPath   Dot-notated path to the max field (e.g., 'elementable_data.max') or null to skip.
     * @param  array{
     *     // Custom messages; you can localize or pass trans() here:
     *     msgLessThanMin?: string,  // message when current < min
     *     msgGreaterThanMax?: string, // message when current > max
     * } $options
     *
     * @return Closure(Get): Closure(string, mixed, Closure): void
     */

    public static function compareWith(?string $minPath, ?string $maxPath, array $options = []): Closure
    {
        $defaults = [
            // When both min & max are present and the value is out of range:
            'msgBetween' => __('The :attribute must be between :min and :max.'),

            // When only min is present (or you want distinct messages regardless):
            'msgLessThanMin' => __('The :attribute must be greater than or equal to the minimum value :min.'),

            // When only max is present (or you want distinct messages regardless):
            'msgGreaterThanMax' => __('The :attribute must be less than or equal to the maximum value :max.'),

            // Optional number formatting for messages (e.g., show 2 decimals).
            // Signature: fn(float $n): string
            'format' => null, // e.g., fn (float $n) => number_format($n, 2, '.', '')
        ];
        $opts = array_replace($defaults, $options);

        $fmt = $opts['format'] instanceof Closure
            ? $opts['format']
            : (function (float $n): string {
                return (string) $n;
            });

        return function (Get $get) use ($minPath, $maxPath, $opts, $fmt) {
            return function (string $attribute, $value, Closure $fail) use ($get, $minPath, $maxPath, $opts, $fmt) {
                if ($value === null || $value === '') {
                    // Let 'nullable' / 'required' handle empties; nothing to compare.
                    return;
                }

                $current = (float) $value;

                // Pull sibling values (if any)
                $minRaw = $minPath ? $get($minPath) : null;
                $maxRaw = $maxPath ? $get($maxPath) : null;

                $hasMin = ($minRaw !== null && $minRaw !== '');
                $hasMax = ($maxRaw !== null && $maxRaw !== '');

                // If both min and max exist, do a single between check.
                if ($hasMin && $hasMax) {
                    $min = (float) $minRaw;
                    $max = (float) $maxRaw;

                    // Optionally normalize order if min > max (defensive)
                    if ($min > $max) {
                        [$min, $max] = [$max, $min];
                    }

                    $inRange = ($current >= $min && $current <= $max);

                    if (! $inRange) {
                        $message = $opts['msgBetween'];

                        // Replace placeholders with formatted values (if present in the string)
                        $message = strtr($message, [
                            ':min' => $fmt($min),
                            ':max' => $fmt($max),
                        ]);

                        $fail($message);
                    }

                    return; // We’re done—single combined message for the between check.
                }

                // If only min exists → check lower bound
                if ($hasMin) {
                    $min = (float) $minRaw;

                    $ok = ($current >= $min);
                    if (! $ok) {
                        $message = $opts['msgLessThanMin'];
                        // If you want to show the concrete min value, support :min here too:
                        $message = strtr($message, [
                            ':min' => $fmt($min),
                        ]);
                        $fail($message);
                        return;
                    }
                }

                // If only max exists → check upper bound
                if ($hasMax) {
                    $max = (float) $maxRaw;

                    $ok = ($current <= $max);
                    if (! $ok) {
                        $message = $opts['msgGreaterThanMax'];
                        // If you want to show the concrete max value, support :max here too:
                        $message = strtr($message, [
                            ':max' => $fmt($max),
                        ]);
                        $fail($message);
                        return;
                    }
                }
            };
        };
    }
}
