<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymousSiebelColumns extends ListRecords
{
    protected static string $resource = AnonymousSiebelColumnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\Action::make('bulk_assign_seed_contracts')
            //     ->label('Bulk Assign Seed Contracts')
            //     ->icon('heroicon-o-funnel')
            //     ->color('info')
            //     ->url(fn() => AnonymousSiebelColumnResource::getUrl('bulk-assign')),
            // Actions\CreateAction::make(),
        ];
    }
}
