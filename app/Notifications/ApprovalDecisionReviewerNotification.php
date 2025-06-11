<?php

namespace App\Notifications;

use App\Models\FormApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalDecisionReviewerNotification extends Notification
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
        $approval = $this->approvalRequest;
        $form = $approval->formVersion->form ?? null;

        $formTitle = $form->form_title ?? 'Unknown Form';
        $status = $this->isApproved ? 'approved' : 'rejected';
        $statusCapitalized = ucfirst($status);
        $approverName = $approval->approver_name ?? 'Reviewer';

        $greetingName = (is_object($notifiable) && property_exists($notifiable, 'name'))
            ? $notifiable->name
            : $approverName;

        $formVersionId = $approval->formVersion->id ?? null;
        $previewUrl = $formVersionId ? rtrim(env('FORM_PREVIEW_URL', ''), '/') . '/preview/' . $formVersionId : null;

        $parseStatus = function ($text, $type) {
            preg_match_all('/(Webform|PDF):\s*(Rejected|Approved)\s*-?\s*([^P]*)?/i', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (strtolower($match[1]) === strtolower($type)) {
                    return strtolower($match[2]);
                }
            }
            return null;
        };

        $note = $approval->approver_note ?? '';
        $webformStatus = $parseStatus($note, 'webform');
        $pdfStatus = $parseStatus($note, 'pdf');

        $badge = fn($status) =>
        $status === 'approved'
            ? '<span style="background:#F6FFF8;border:1px solid #42814A;border-radius:2px;color:#2D2D2D;padding:2px 8px;display:inline-block;">Approved</span>'
            : '<span style="background:#F4E1E2;border:1px solid #CE3E39;border-radius:2px;color:#2D2D2D;padding:2px 8px;display:inline-block;">Rejected</span>';

        $decisionDetails = '';
        if ($webformStatus) {
            $decisionDetails .= "<a href=\"{$previewUrl}\" style=\"text-decoration:underline;color:#2563eb;\">Web updates:</a>  " . $badge($webformStatus) . "<br>";
        }
        if ($webformStatus && $pdfStatus) {
            $decisionDetails .= "<br>";
        }
        if ($pdfStatus) {
            $decisionDetails .= "<a href=\"{$previewUrl}\" style=\"text-decoration:underline;color:#2563eb;\">PDF updates:</a>  " . $badge($pdfStatus) . "<br>";
        }
        if (!$decisionDetails) {
            $decisionDetails = "Decision: {$statusCapitalized}<br>";
        }

        return (new MailMessage)
            ->subject("Form Review Completed - Record of your Decision: {$formTitle}")
            ->markdown('mail.forms-default-template', [
                'slot' => (new MailMessage)
                    ->greeting("Hello {$greetingName},")
                    ->line('')
                    ->line('You are receiving this email because you have reviewed a form and made a decision.')
                    ->line('')
                    ->line('Please keep this as a record of your decision for the form review request.')
                    ->line("{$approverName} has reviewed the form below and provided their decisions as outlined")
                    ->line("**Form:** {$formTitle}")
                    ->line("**Version:** " . ($approval->formVersion->version_number ?? 'N/A'))
                    ->line(new \Illuminate\Support\HtmlString($decisionDetails))
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
