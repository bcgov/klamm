<?php

namespace App\Filament\Fodig\Resources\ChangeTicketResource\Pages;

use App\Filament\Fodig\Resources\ChangeTicketResource;
use App\Models\Anonymizer\ChangeTicket;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChangeTicket extends EditRecord
{
    protected static string $resource = ChangeTicketResource::class;

    protected function getHeaderActions(): array
    {
        return array_merge([
            Actions\Action::make('review')
                ->label(fn(): string => ChangeTicketResource::reviewActionLabel($this->record))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn(): ?string => ChangeTicketResource::reviewUrl($this->record), shouldOpenInNewTab: true)
                ->visible(fn(): bool => (bool) ChangeTicketResource::reviewUrl($this->record)),
            Actions\Action::make('mark_resolved')
                ->label('Mark resolved')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn(): bool => ! $this->record->trashed() && $this->record->status !== 'resolved')
                ->action(function (): void {
                    $ticket = $this->record;
                    $ticket->status = 'resolved';
                    $ticket->resolved_at = now();
                    $ticket->save();
                    $ticket->delete();
                    $this->redirect(ChangeTicketResource::getUrl('index'));
                }),
        ], parent::getHeaderActions());
    }
}
