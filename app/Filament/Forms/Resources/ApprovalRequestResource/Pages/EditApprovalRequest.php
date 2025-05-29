<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class EditApprovalRequest extends EditRecord
{
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

        if ($this->record->approver_id !== Auth::id()) {
            abort(403, 'You are not authorized to review this approval request.');
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
                                    ->content(fn($record) => $record->formVersion->form->form_title ?? 'N/A'),
                                Placeholder::make('form_id')
                                    ->label(new HtmlString(self::formatLabel('Form ID')))
                                    ->content(fn($record) => $record->formVersion->form->form_id ?? 'N/A'),
                                Placeholder::make('version')
                                    ->label(new HtmlString(self::formatLabel('Version')))
                                    ->content(fn($record) => $record->formVersion->version_number ?? 'N/A'),
                                Placeholder::make('request_date')
                                    ->label(new HtmlString(self::formatLabel('Request Date')))
                                    ->content(fn($record) => $record->created_at->format('M j, Y g:i A')),
                            ]),
                        Placeholder::make('requester_note')
                            ->label(new HtmlString(self::formatLabel('Requester Note')))
                            ->content(fn($record) => new HtmlString(self::formatRequesterNote($record->requester_note ?? 'No note provided')))
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
                            ->visible(fn($record) => $record->webform_approval === true),

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
                            ->visible(fn($record) => $record->pdf_approval === true),
                    ]),
            ]);
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
                    // TODO: Implement submit logic
                }),
        ];
    }
}
