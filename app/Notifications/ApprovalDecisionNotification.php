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

        return (new MailMessage)
            ->subject('Form Approval Decision: ' . $formTitle)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You are receiving this email because a form you submitted for approval has been reviewed.')
            ->line('**Form:** ' . $formTitle)
            ->line('**Version:** ' . ($this->approvalRequest->formVersion->version_number ?? 'N/A'))
            ->line('**Decision:** ' . $statusCapitalized)
            ->line('The form you submitted for approval has been **' . $status . '**.')
            ->action('View Approval Details', $viewUrl)
            ->line('You can view the full approval details and any comments by clicking the button above.')
            ->line('Thank you!');
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
