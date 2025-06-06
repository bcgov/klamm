<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use App\Notifications\CancellationNotification;
use App\Notifications\ApprovalRequestReminderNotification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Components\Textarea;

class ViewApprovalRequest extends ViewRecord
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {

        $reminderStatus = $this->record->updated_at->isAfter(now()->subDay())
            && !Gate::allows(('admin'))
            ? false
            : true;

        $reminderTime = $this->record->updated_at->isAfter(now()->subDay())
            ? $this->record->updated_at->diffForHumans()
            : null;

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

            ActionGroup::make([

                Actions\Action::make('reminder')
                    ->label($reminderStatus ? 'Send Reminder' : 'Last reminder sent: ' . $reminderTime . '. Please wait 24 hours to send another')
                    ->action(function () {
                        try {
                            if ($this->record->approver_id) {
                                $this->record->approver->notify(new ApprovalRequestReminderNotification($this->record));
                            } elseif ($this->record->approver_email) {
                                NotificationFacade::route('mail', $this->record->approver_email)
                                    ->notify(new ApprovalRequestReminderNotification($this->record));
                            }

                            // Update timestamp to track when reminder was sent
                            $this->record->touch();

                            Notification::make()
                                ->title('Reminder Sent')
                                ->body('A reminder has been sent to the approver.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body('Failed to send reminder: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->icon('heroicon-o-bell')
                    ->color('warning')
                    ->disabled(!$reminderStatus)
                    ->requiresConfirmation()
                    ->modalHeading('Send Reminder')
                    ->modalDescription(
                        'Are you sure you want to send a reminder for this Approval Request? The approver will be notified via email.'
                    )
                    ->modalSubmitActionLabel('Yes, send reminder'),

                Actions\Action::make('cancel')
                    ->label('Cancel Request')
                    ->form([
                        Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason (Optional)')
                            ->placeholder('Provide a reason for cancelling this request...')
                            ->rows(3)
                            ->maxLength(500)
                    ])
                    ->action(function (array $data) {
                        try {
                            $this->record->formVersion->update(['status' => 'draft']);

                            $cancellerName = Auth::user()->name;
                            $reason = !empty($data['cancellation_reason']) ? $data['cancellation_reason'] : 'Not provided';
                            $cancellationNote = "Cancelled by: {$cancellerName}; Cancellation Reason: {$reason}";

                            $this->record->update([
                                'status' => 'cancelled',
                                'approver_note' => $cancellationNote
                            ]);

                            // Send notification to requester only if they're not the one cancelling (admin cancelling)
                            if ($this->record->requester_id !== Auth::id()) {
                                $this->record->requester->notify(new CancellationNotification($this->record));
                            }

                            // Send notification to approver
                            if ($this->record->approver_id && $this->record->approver) {
                                $this->record->approver->notify(new CancellationNotification($this->record));
                            } elseif ($this->record->approver_email) {
                                NotificationFacade::route('mail', $this->record->approver_email)
                                    ->notify(new CancellationNotification($this->record));
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
                                ->body('Failed to cancel request' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->modalHeading('Cancel Approval Request')
                    ->modalDescription(
                        function () {
                            $cancellationMessage = 'Are you sure you want to cancel this Approval Request? This cannot be undone. The form will be returned to draft status and the approver will be notified of the cancellation.';
                            if ($this->record->requester_id !== Auth::id()) {
                                return $cancellationMessage . ' The original requester will also be notified.';
                            };
                            return $cancellationMessage;
                        }
                    )
                    ->modalSubmitActionLabel('Yes, cancel it')
            ])
                ->label('Approval Options')
                ->icon('heroicon-m-ellipsis-vertical')
                ->dropdownWidth(!$reminderStatus ? MaxWidth::ExtraLarge : null)
                ->visible(fn() => $this->record->status === 'pending' && ($this->record->requester_id === Auth::id() || Gate::allows(('admin'))))
        ];
    }
}
