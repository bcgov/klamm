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

    protected static function formatLabel(string $text): string
    {
        return '<span class="block text-lg font-bold">' . $text . '</span>';
    }

    protected static function formatRequesterNote(string $note): string
    {
        if (strlen($note) <= 200) {
            return $note;
        }

        $truncated = substr($note, 0, 300);

        return '<div>
            <span id="note-preview">' . $truncated . '...</span>
            <span id="note-full" style="display: none;">' . $note . '</span>
            <br>
            <button type="button" id="toggle-note" class="text-primary-600 hover:text-primary-500 underline text-sm mt-1" onclick="toggleNote()">
                Show more
            </button>
        </div>
        <script>
            function toggleNote() {
                const preview = document.getElementById("note-preview");
                const full = document.getElementById("note-full");
                const button = document.getElementById("toggle-note");
                
                if (preview.style.display === "none") {
                    preview.style.display = "inline";
                    full.style.display = "none";
                    button.textContent = "Show more";
                } else {
                    preview.style.display = "none";
                    full.style.display = "inline";
                    button.textContent = "Show less";
                }
            }
        </script>';
    }

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
            'requester_note' => new HtmlString(self::formatRequesterNote($record->requester_note ?? 'No note provided', 200)),
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
