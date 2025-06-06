<?php

namespace App\Notifications;

use App\Models\FormApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalDecisionNotification extends Notification
{
    use Queueable;

    protected FormApprovalRequest $approvalRequest;
    protected bool $isApproved;

    /**
     * Create a new notification instance.
     */
    public function __construct(FormApprovalRequest $approvalRequest, bool $isApproved)
    {
        $this->approvalRequest = $approvalRequest;
        $this->isApproved = $isApproved;
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
        $status = $this->isApproved ? 'approved' : 'rejected';
        $statusCapitalized = ucfirst($status);

        $viewUrl = url('/forms/approval-requests/' . $this->approvalRequest->id);

        $isInternal = $this->approvalRequest->approver_id !== null;

        if ($isInternal) {
            $approverName = $notifiable->name;
        } else {
            $approverName = $this->approvalRequest->approver_name ?? 'Reviewer';
        }

        return (new MailMessage)
            ->subject('Form Review Completed: See the Decision: ' . $formTitle)
            ->markdown('mail.forms-default-template', [
                'slot' => (new MailMessage)
                    ->greeting('Hello ' . $notifiable->name . '!')
                    ->line('')
                    ->line($approverName . ' has reviewed the form below and provided their decisions as outlined')
                    ->line('**Form:** ' . $formTitle)
                    ->line('**Version:** ' . ($this->approvalRequest->formVersion->version_number ?? 'N/A'))
                    ->line('**Decision:** ' . $statusCapitalized)
                    ->action('Review decision details', $viewUrl)
                    ->line('Regards,')
                    ->line('**Forms Modernization Team**')
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
            'status' => $this->isApproved ? 'approved' : 'rejected',
        ];
    }
}
