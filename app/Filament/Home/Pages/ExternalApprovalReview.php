<?php

namespace App\Filament\Home\Pages;

use App\Models\FormApprovalRequest;
use App\Traits\HandlesApprovalReview;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;

class ExternalApprovalReview extends Page implements HasForms, HasActions
{
    use InteractsWithForms, InteractsWithActions, HandlesApprovalReview;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string $view = 'filament.home.pages.external-approval-review';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'external-review/{record}/{token}';

    public ?string $heading = 'Review requested update';
    public ?string $subheading = 'Please review the updates made and either approve or reject them.';

    public $webformApprovalState = null;
    public $pdfApprovalState = null;
    public $webformRejectionReason = '';
    public $pdfRejectionReason = '';

    public FormApprovalRequest $record;
    public string $token;

    protected static function formatLabel(string $text): string
    {
        return '<span class="block text-lg font-bold">' . $text . '</span>';
    }

    protected static function formatRequesterNote(string $note): string
    {
        if (strlen($note) <= 300) {
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

    public function mount(FormApprovalRequest $record, string $token): void
    {
        $this->record = $record;
        $this->token = $token;

        // Validate token
        if ($this->record->token !== $token) {
            abort(403, 'Invalid or expired approval token.');
        }

        // Check if this is an external approval (no approver_id)
        if ($this->record->approver_id !== null) {
            abort(403, 'This approval request is not for external review.');
        }

        // Check if the approval request is still pending
        if ($this->record->status !== 'pending') {
            abort(403, 'A review has already been submitted for this request.');
        }
    }

    public function form(Form $form): Form
    {
        return $form->schema($this->getApprovalReviewForm());
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
            'requester_note' => new HtmlString(self::formatRequesterNote($record->requester_note ?? 'No note provided')),
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
        session()->flash('success', 'Your approval has been submitted successfully. Thank you for your review.');
        $this->redirect('/welcome');
    }
}
