<?php

namespace App\Notifications;

use App\Models\FormApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

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
        $approval = $this->approvalRequest;
        $formVersion = $approval->formVersion;
        $form = $formVersion->form ?? null;

        $formTitle = $form->form_title ?? 'Unknown Form';
        $status = $this->isApproved ? 'approved' : 'rejected';
        $statusCapitalized = ucfirst($status);
        $viewUrl = url('/forms/approval-requests/' . $approval->id);
        $approverName = $approval->approver_id ? $notifiable->name : ($approval->approver_name ?? 'Reviewer');

        $previewUrl = $formVersion->id
            ? rtrim(env('FORM_PREVIEW_URL', ''), '/') . '/preview-v2-dev/' . $formVersion->id
            : null;

        $approverNote = $approval->approver_note ?? '';

        $parseStatus = function (string $text, string $type): ?string {
            preg_match_all('/(Webform|PDF):\s*(Rejected|Approved)\s*-?\s*([^P]*)?/i', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (strtolower($match[1]) === strtolower($type)) {
                    return strtolower($match[2]);
                }
            }
            return null;
        };

        $buildDecisionLine = function (?string $type, ?string $status) use ($previewUrl): string {
            if (!$status || !$type || !$previewUrl) return '';

            $label = ucfirst($type);
            if ($status === 'approved') {
                $tag = '<span style="background:#F6FFF8;border:1px solid #42814A;border-radius:2px;color:#2D2D2D;padding:2px 8px;display:inline-block;">Approved</span>';
            } else {
                $tag = '<span style="background:#F4E1E2;border:1px solid #CE3E39;border-radius:2px;color:#2D2D2D;padding:2px 8px;display:inline-block;">Rejected</span>';
            }

            return "<a href=\"{$previewUrl}\" style=\"text-decoration:underline;color:#2563eb;\">{$label} updates:</a> {$tag}<br>";
        };

        $webformStatus = $parseStatus($approverNote, 'webform');
        $pdfStatus = $parseStatus($approverNote, 'pdf');

        $decisionDetails = $buildDecisionLine('Web', $webformStatus);
        if ($webformStatus && $pdfStatus) {
            $decisionDetails .= '<br>';
        }
        $decisionDetails .= $buildDecisionLine('PDF', $pdfStatus);

        if (!$decisionDetails) {
            $decisionDetails = 'Decision: ' . $statusCapitalized . '<br>';
        }

        return (new MailMessage)
            ->subject("Form Review Completed - See the Decision: {$formTitle}")
            ->markdown('mail.forms-default-template', [
                'slot' => (new MailMessage)
                    ->greeting("Hello {$notifiable->name},")
                    ->line('')
                    ->line("{$approverName} has reviewed the form below and provided their decisions as outlined")
                    ->line("**Form:** {$formTitle}")
                    ->line("**Version:** " . ($formVersion->version_number ?? 'N/A'))
                    ->line(new HtmlString($decisionDetails))
                    ->action('Review decision details', $viewUrl)
                    ->line('Regards,')
                    ->salutation('**Forms Modernization Team**')
                    ->render(),
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
