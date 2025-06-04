<?php

namespace App\Notifications;

use App\Models\FormApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CancellationNotification extends Notification
{
    use Queueable;

    protected FormApprovalRequest $approvalRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(FormApprovalRequest $approvalRequest)
    {
        $this->approvalRequest = $approvalRequest;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $formTitle = $this->approvalRequest->formVersion->form->form_title ?? 'Unknown Form';
        $requesterName = $this->approvalRequest->requester->name ?? 'Unknown Requester';

        $isRequester = isset($notifiable->id) && $notifiable->id === $this->approvalRequest->requester_id;
        $recipientName = $notifiable->name ?? 'User';

        if ($isRequester) {
            // Message for the requester
            return (new MailMessage)
                ->subject('Form Review Request Cancelled: ' . $formTitle)
                ->greeting('Hello ' . $recipientName . '!')
                ->line('Your form review request has been cancelled.')
                ->line('**Form:** ' . $formTitle)
                ->line('**Version:** ' . ($this->approvalRequest->formVersion->version_number ?? 'N/A'))
                ->line('**Form ID:** ' . ($this->approvalRequest->formVersion->form->form_id ?? 'N/A'))
                ->line('**Cancellation Date:** ' . now()->format('M j, Y g:i A'))
                ->when($this->approvalRequest->requester_note, function ($mail) {
                    return $mail->line('**Original Request Note:** ' . $this->approvalRequest->requester_note);
                })
                ->line('The form has been returned to draft status and can be modified if needed.');
        } else {
            // Message for the approver
            $approverName = $this->approvalRequest->approver_id ?
                $recipientName : ($this->approvalRequest->approver_name ?? 'Reviewer');

            return (new MailMessage)
                ->subject('Form Review Request Cancelled: ' . $formTitle)
                ->greeting('Hello ' . $approverName . '!')
                ->line('A form review request assigned to you has been cancelled by the requester.')
                ->line('**Form:** ' . $formTitle)
                ->line('**Version:** ' . ($this->approvalRequest->formVersion->version_number ?? 'N/A'))
                ->line('**Form ID:** ' . ($this->approvalRequest->formVersion->form->form_id ?? 'N/A'))
                ->line('**Requested by:** ' . $requesterName)
                ->line('**Request Date:** ' . $this->approvalRequest->created_at->format('M j, Y g:i A'))
                ->line('**Cancellation Date:** ' . now()->format('M j, Y g:i A'))
                ->when($this->approvalRequest->requester_note, function ($mail) {
                    return $mail->line('**Original Request Note:** ' . $this->approvalRequest->requester_note);
                })
                ->line('No further action is required from you.');
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'approval_request_id' => $this->approvalRequest->id,
            'form_title' => $this->approvalRequest->formVersion->form->form_title,
            'form_id' => $this->approvalRequest->formVersion->form->form_id,
            'version_number' => $this->approvalRequest->formVersion->version_number,
            'requester_name' => $this->approvalRequest->requester->name,
            'is_requester' => isset($notifiable->id) && $notifiable->id === $this->approvalRequest->requester_id,
            'is_internal' => $this->approvalRequest->approver_id !== null,
        ];
    }
}
