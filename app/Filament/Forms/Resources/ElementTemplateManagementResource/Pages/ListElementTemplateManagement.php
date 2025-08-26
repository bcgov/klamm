<?php

namespace App\Filament\Forms\Resources\ElementTemplateManagementResource\Pages;

use App\Filament\Forms\Resources\ElementTemplateManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListElementTemplateManagement extends ListRecords
{
    protected static string $resource = ElementTemplateManagementResource::class;

    protected static ?string $title = 'Element Templates';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Element Template')
                ->icon('heroicon-o-plus')
                ->visible(fn () => auth()->check() && auth()->user()->hasRole('admin')),
        ];
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('admin'), 403);
    }
}
