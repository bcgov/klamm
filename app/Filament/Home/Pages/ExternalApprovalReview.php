<?php

namespace App\Filament\Home\Pages;

use App\Models\FormApprovalRequest;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class ExternalApprovalReview extends Page implements HasForms
{
    use InteractsWithForms;

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
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Requested updates')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Placeholder::make('form_name')
                                    ->label(new HtmlString(self::formatLabel('Form Name')))
                                    ->content(fn() => $this->record->formVersion->form->form_title ?? 'N/A'),
                                Placeholder::make('form_id')
                                    ->label(new HtmlString(self::formatLabel('Form ID')))
                                    ->content(fn() => $this->record->formVersion->form->form_id ?? 'N/A'),
                                Placeholder::make('version')
                                    ->label(new HtmlString(self::formatLabel('Version')))
                                    ->content(fn() => $this->record->formVersion->version_number ?? 'N/A'),
                                Placeholder::make('request_date')
                                    ->label(new HtmlString(self::formatLabel('Request Date')))
                                    ->content(fn() => $this->record->created_at->format('M j, Y g:i A')),
                            ]),
                        Placeholder::make('requester_note')
                            ->label(new HtmlString(self::formatLabel('Requester Note')))
                            ->content(fn() => new HtmlString(self::formatRequesterNote($this->record->requester_note ?? 'No note provided')))
                            ->columnSpanFull(),
                    ]),

                Section::make('Review updated form')
                    ->schema([
                        Fieldset::make('Webform')
                            ->schema([
                                Placeholder::make('webform_link')
                                    ->label('')
                                    ->extraAttributes(['class' => 'prose'])
                                    ->content(new HtmlString('<a href="https://filamentphp.com/docs" class="text-primary-600 hover:text-primary-500 underline">View Webform</a>'))
                                    ->columnSpanFull(),
                                Placeholder::make('webform_question')
                                    ->label('')
                                    ->content('Do you approve the changes that have been made?')
                                    ->columnSpanFull(),
                                FormActions::make([
                                    FormAction::make('reject_webform')
                                        ->label('Reject')
                                        ->color('danger')
                                        ->outlined(fn() => $this->webformApprovalState !== 'rejected')
                                        ->icon('heroicon-o-x-circle')
                                        ->action(function () {
                                            if ($this->webformApprovalState === 'rejected') {
                                                $this->webformApprovalState = null;
                                            } else {
                                                $this->webformApprovalState = 'rejected';
                                            }
                                        }),
                                    FormAction::make('approve_webform')
                                        ->label('Approve')
                                        ->color('success')
                                        ->outlined(fn() => $this->webformApprovalState !== 'approved')
                                        ->icon('heroicon-o-check-circle')
                                        ->action(function () {
                                            if ($this->webformApprovalState === 'approved') {
                                                $this->webformApprovalState = null;
                                            } else {
                                                $this->webformApprovalState = 'approved';
                                            }
                                        }),
                                ])
                                    ->columnSpanFull()
                                    ->alignment('start'),
                                Textarea::make('webformRejectionReason')
                                    ->label('Reasons for rejection (Required)')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->visible(fn() => $this->webformApprovalState === 'rejected'),
                            ])
                            ->visible(fn() => $this->record->webform_approval === true),

                        Fieldset::make('PDF')
                            ->schema([
                                Placeholder::make('pdf_link')
                                    ->label('')
                                    ->extraAttributes(['class' => 'prose'])
                                    ->content(new HtmlString('<a href="https://filamentphp.com/docs" class="text-primary-600 hover:text-primary-500 underline">View PDF</a>'))
                                    ->columnSpanFull(),
                                Placeholder::make('pdf_question')
                                    ->label('')
                                    ->content('Do you approve the changes that have been made?')
                                    ->columnSpanFull(),
                                FormActions::make([
                                    FormAction::make('reject_pdf')
                                        ->label('Reject')
                                        ->color('danger')
                                        ->outlined(fn() => $this->pdfApprovalState !== 'rejected')
                                        ->icon('heroicon-o-x-circle')
                                        ->action(function () {
                                            if ($this->pdfApprovalState === 'rejected') {
                                                $this->pdfApprovalState = null;
                                            } else {
                                                $this->pdfApprovalState = 'rejected';
                                            }
                                        }),
                                    FormAction::make('approve_pdf')
                                        ->label('Approve')
                                        ->color('success')
                                        ->outlined(fn() => $this->pdfApprovalState !== 'approved')
                                        ->icon('heroicon-o-check-circle')
                                        ->action(function () {
                                            if ($this->pdfApprovalState === 'approved') {
                                                $this->pdfApprovalState = null;
                                            } else {
                                                $this->pdfApprovalState = 'approved';
                                            }
                                        }),
                                ])
                                    ->columnSpanFull()
                                    ->alignment('start'),
                                Textarea::make('pdfRejectionReason')
                                    ->label('Reasons for rejection (Required)')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->visible(fn() => $this->pdfApprovalState === 'rejected'),
                            ])
                            ->visible(fn() => $this->record->pdf_approval === true),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit Review')
                ->color('primary')
                ->disabled(function () {
                    $webformRequired = $this->record->webform_approval;
                    $pdfRequired = $this->record->pdf_approval;

                    $webformComplete = !$webformRequired || ($this->webformApprovalState !== null);
                    $pdfComplete = !$pdfRequired || ($this->pdfApprovalState !== null);

                    return !($webformComplete && $pdfComplete);
                })
                ->action(function () {
                    $formVersionStatus = $this->determineFormVersionStatus();
                    $isApproved = $formVersionStatus === 'approved';

                    $updateData = [
                        'status' => 'completed',
                        'approver_note' => $this->buildApproverNote(),
                    ];

                    if ($isApproved) {
                        $updateData['approved_at'] = now();
                    } else {
                        $updateData['rejected_at'] = now();
                    }

                    $this->record->update($updateData);

                    $this->record->formVersion->update([
                        'status' => $formVersionStatus,
                    ]);

                    session()->flash('success', 'Your approval has been submitted successfully. Thank you for your review.');

                    return redirect('/welcome');
                }),
        ];
    }

    protected function buildApproverNote(): string
    {
        $notes = [];
        $formState = $this->form->getState();

        if ($this->record->webform_approval) {
            if ($this->webformApprovalState === 'approved') {
                $notes[] = 'Webform: Approved';
            } elseif ($this->webformApprovalState === 'rejected') {
                $rejectionReason = $formState['webformRejectionReason'] ?? 'No reason provided';
                $notes[] = 'Webform: Rejected - ' . $rejectionReason;
            }
        }

        if ($this->record->pdf_approval) {
            if ($this->pdfApprovalState === 'approved') {
                $notes[] = 'PDF: Approved';
            } elseif ($this->pdfApprovalState === 'rejected') {
                $rejectionReason = $formState['pdfRejectionReason'] ?? 'No reason provided';
                $notes[] = 'PDF: Rejected - ' . $rejectionReason;
            }
        }

        return implode("\n\n", $notes);
    }

    protected function determineFormVersionStatus(): string
    {
        $webformRequired = $this->record->webform_approval;
        $pdfRequired = $this->record->pdf_approval;

        $hasRejection = false;

        if ($webformRequired && $this->webformApprovalState === 'rejected') {
            $hasRejection = true;
        }

        if ($pdfRequired && $this->pdfApprovalState === 'rejected') {
            $hasRejection = true;
        }

        return $hasRejection ? 'draft' : 'approved';
    }
}
