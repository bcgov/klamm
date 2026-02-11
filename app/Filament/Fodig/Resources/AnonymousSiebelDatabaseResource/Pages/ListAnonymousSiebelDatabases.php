<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource;
use App\Filament\Fodig\Resources\AnonymousUploadResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListAnonymousSiebelDatabases extends ListRecords
{
    protected static string $resource = AnonymousSiebelDatabaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('import')
                ->label('Import')
                ->icon('heroicon-o-wrench-screwdriver')
                ->url(fn() => AnonymousUploadResource::getUrl('import'))
                ->color('primary')
                ->outlined()
                ->visible(fn() => Gate::allows('admin')),
        ];
    }
}
