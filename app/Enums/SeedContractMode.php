<?php

namespace App\Enums;

enum SeedContractMode: string
{
    case NONE = 'none';
    case SOURCE = 'source';
    case CONSUMER = 'consumer';
    case COMPOSITE = 'composite';
    case EXTERNAL = 'external';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Not declared',
            self::SOURCE => 'Produces canonical seed',
            self::CONSUMER => 'Consumes parent seed',
            self::COMPOSITE => 'Builds composite seed',
            self::EXTERNAL => 'Externally managed seed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NONE => 'Column has not been wired into the seed graph yet.',
            self::SOURCE => 'Column emits the deterministic value that downstream foreign keys must reuse.',
            self::CONSUMER => 'Column expects an upstream value and must not diverge from its parent.',
            self::COMPOSITE => 'Column composes a new seed bundle from multiple parent values.',
            self::EXTERNAL => 'Seed is handled outside KLAMM (e.g. legacy package) but still tracked for audits.',
        };
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
