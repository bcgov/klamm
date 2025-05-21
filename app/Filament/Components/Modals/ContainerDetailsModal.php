<?php

namespace App\Filament\Components\Modals;

use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;

class ContainerDetailsModal
{
    public static function getSchema(): array
    {
        return [
            Fieldset::make('Container Properties')
                ->schema([
                    Placeholder::make('instance_id')
                        ->label('Instance ID')
                        ->content(fn(Get $get) => $get('instance_id')),
                ]),
        ];
    }
}
