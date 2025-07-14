<?php

namespace App\Notifications;

use App\Models\FormApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequestNotification extends Notification
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

        $isInternal = $this->approvalRequest->approver_id !== null;

        if ($isInternal) {
            $reviewUrl = url('/forms/approval-requests/' . $this->approvalRequest->id . '/review');
            $approverName = $notifiable->name;
        } else {
            $reviewUrl = url('/external-review/' . $this->approvalRequest->id . '/' . $this->approvalRequest->token);
            $approverName = $this->approvalRequest->approver_name ?? 'Reviewer';
        }

        return (new MailMessage)
            ->subject('Form Review Requested: ' . $formTitle)

            ->markdown('mail.forms-default-template', [
                'slot' => (new MailMessage)
                    ->greeting('Hello ' . $approverName . ',')
                    ->line('')
                    ->line('You are receiving this email because you are part of the approval process for a form.')
                    ->line('')
                    ->line('Please review the recent updates to the form and respond with your decision to approve or reject them.')
                    ->line('Your timely feedback will help keep the process efficient and on track.')
                    ->line('')
                    ->line('**Form:** ' . $formTitle)
                    // ->line('**Version:** ' . ($this->approvalRequest->formVersion->version_number ?? 'N/A'))
                    // ->line('**Requested by:** ' . $requesterName)
                    // ->line('**Request Date:** ' . $this->approvalRequest->created_at->format('M j, Y g:i A'))
                    // ->when($this->approvalRequest->requester_note, function ($mail) {
                    //     return $mail->line('**Request Note:** ' . $this->approvalRequest->requester_note);
                    // })
                    ->action('Review Form updates', $reviewUrl)
                    ->when(!$isInternal, function ($mail) {
                        return $mail->line('*Note: This is a secure link that is unique to you. Please do not share this link with others.*');
                    })
                    ->line('')
                    ->line('Thanks for your collaboration.')
                    ->line('')
                    ->line('Regards,')
                    ->salutation('**Forms Modernization Team**')
                    ->render()
            ]);
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
            'requester_name' => $this->approvalRequest->requester->name,
            'is_internal' => $this->approvalRequest->approver_id !== null,
        ];
    }
}
