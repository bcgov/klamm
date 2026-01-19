<?php

namespace App\Filament\Fodig\Resources\ChangeTicketResource\Pages;

use App\Filament\Fodig\Resources\ChangeTicketResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ListChangeTickets extends ListRecords
{
    protected static string $resource = ChangeTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clearChangeTickets')
                ->label('Clear Change Tickets')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => app()->environment('local'))
                ->action(function (): void {
                    DB::table('change_tickets')->delete();
                    Notification::make()
                        ->title('All change tickets have been cleared')
                        ->success()
                        ->send();
                }),
        ];
    }
}
