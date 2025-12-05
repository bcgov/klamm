<?php

namespace App\Filament\Fodig\Resources\AnonymousUploadResource\Pages;

use App\Filament\Fodig\Resources\AnonymousUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnonymousUploads extends ListRecords
{
    protected static string $resource = AnonymousUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Import Metadata')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(fn() => AnonymousUploadResource::getUrl('import')),
        ];
    }
}
