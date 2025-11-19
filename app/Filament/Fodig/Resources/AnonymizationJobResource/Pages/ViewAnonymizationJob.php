<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAnonymizationJob extends ViewRecord
{
    protected static string $resource = AnonymizationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('runJob')
                ->label('Run Job')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->outlined()
                ->tooltip('Job execution is orchestrated outside of the admin panel.')
                ->disabled(),
            Actions\Action::make('viewSelection')
                ->label('View Selection')
                ->icon('heroicon-o-list-bullet')
                ->color('secondary')
                ->outlined()
                ->url(fn() => AnonymizationJobResource::getUrl('selection', ['record' => $this->record])),
            Actions\EditAction::make(),
        ];
    }
}
