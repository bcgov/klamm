<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use App\Notifications\CancellationNotification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class ViewApprovalRequest extends ViewRecord
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Review')
                ->icon('heroicon-o-check-circle')
                ->button()
                ->outlined(false)
                ->color('primary')
                ->extraAttributes(['style' => 'background-color: #013366; border-color: #013366;'])
                ->visible(
                    fn($record) =>
                    $record->approver_id === Auth::id() &&
                        $record->status === 'pending'
                ),

            Actions\Action::make('cancel')
                ->label('Cancel Request')
                ->action(function () {
                    try {
                        $this->record->formVersion->update(['status' => 'draft']);
                        $this->record->update(['status' => 'cancelled']);

                        // Send notification to requester only if they're not the one cancelling
                        if ($this->record->requester_id !== Auth::id()) {
                            $this->record->requester->notify(new CancellationNotification($this->record));
                        }

                        // Send notification to approver
                        if ($this->record->approver_id && $this->record->approver) {
                            $this->record->approver->notify(new CancellationNotification($this->record));
                        } elseif ($this->record->approver_email) {
                            $notification = new CancellationNotification($this->record);
                            $notifiable = (object) [
                                'id' => null,
                                'name' => $this->record->approver_name ?? 'Reviewer',
                                'email' => $this->record->approver_email
                            ];

                            $mailMessage = $notification->toMail($notifiable);
                            Mail::raw($mailMessage->render(), function ($message) use ($mailMessage, $notifiable) {
                                $message->to($notifiable->email)
                                    ->subject($mailMessage->subject);
                            });
                        }

                        Notification::make()
                            ->title('Request Cancelled')
                            ->body('Your request has been cancelled successfully.')
                            ->success()
                            ->send();
                        $this->dispatch('refresh');
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body('Failed to cancel request')
                            ->danger()
                            ->send();
                    }
                })
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Cancel Approval Request')
                ->modalDescription(
                    function () {
                        $cancellationMessage = 'Are you sure you want to cancel this Approval Request? This cannot be undone. The form will be returned to draft status and the approver will be notified of the cancellation.';
                        if ($this->record->requester_id !== Auth::id()) {
                            return $cancellationMessage . 'The original requester will also be notified. ';
                        };
                        return $cancellationMessage;
                    }
                )
                ->modalSubmitActionLabel('Yes, cancel it')
                ->visible(fn() => $this->record->status === 'pending' && ($this->record->requester_id === Auth::id() || Gate::allows(('admin')))),
            //
        ];
    }
}
