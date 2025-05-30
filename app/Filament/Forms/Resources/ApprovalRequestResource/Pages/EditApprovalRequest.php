<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use App\Traits\HandlesApprovalReview;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class EditApprovalRequest extends EditRecord
{
    use HandlesApprovalReview;

    protected static string $resource = ApprovalRequestResource::class;

    protected ?string $heading = 'Review requested update';

    protected ?string $subheading = 'You are receiving this request because you previously requested changes to a form. Please review the updates made and either approve or reject them.';

    public $webformApprovalState = null;
    public $pdfApprovalState = null;
    public $webformRejectionReason = '';
    public $pdfRejectionReason = '';

    public function getBreadcrumbs(): array
    {
        return [
            //
        ];
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        // If user is the approver, show the page
        if ($this->record->approver_id === Auth::id()) {
            return;
        }

        // If user is not the approver but has form-developer or admin role, redirect to view page
        if (optional(Auth::user())->hasRole('form-developer') || optional(Auth::user())->hasRole('admin')) {
            $this->redirect(ApprovalRequestResource::getUrl('view', ['record' => $this->record]));
            return;
        }

        // If user is not the approver and doesn't have required roles, show 403 page
        abort(403, 'You are not authorized to review this approval request.');
    }

    public function form(Form $form): Form
    {
        return $form->schema($this->getApprovalReviewForm());
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    protected function getFormActions(): array
    {
        return [
            //
        ];
    }

    protected function getApprovalRecord()
    {
        return $this->record;
    }

    protected function getFormContent(string $field, $record)
    {
        return match ($field) {
            'form_name' => $record->formVersion->form->form_title ?? 'N/A',
            'form_id' => $record->formVersion->form->form_id ?? 'N/A',
            'version' => $record->formVersion->version_number ?? 'N/A',
            'request_date' => $record->created_at->format('M j, Y g:i A'),
            'requester_note' => $record->requester_note ?? 'No note provided',
        };
    }

    protected function getWebformApprovalVisibility($record): bool
    {
        return $record->webform_approval === true;
    }

    protected function getPdfApprovalVisibility($record): bool
    {
        return $record->pdf_approval === true;
    }

    protected function handlePostSubmissionActions(): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Approval submitted successfully')
            ->success()
            ->send();

        $this->redirect(ApprovalRequestResource::getUrl('index'));
    }
}
