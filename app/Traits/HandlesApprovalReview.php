<?php

namespace App\Traits;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\HtmlString;
use App\Notifications\ApprovalDecisionNotification;
use App\Forms\Components\ShowMorePlaceholder;

trait HandlesApprovalReview
{
    public $webformApprovalState = null;
    public $pdfApprovalState = null;
    public $webformRejectionReason = '';
    public $pdfRejectionReason = '';

    protected static function formatLabel(string $text): string
    {
        return '<span class="block text-lg font-bold">' . $text . '</span>';
    }

    protected function getApprovalReviewForm(): array
    {
        $record = $this->getApprovalRecord();

        return [
            Section::make('Requested updates')
                ->schema([
                    Grid::make(1)
                        ->schema([
                            Placeholder::make('form_name')
                                ->label(new HtmlString(self::formatLabel('Form Name')))
                                ->content($this->getFormContent('form_name', $record)),
                            Placeholder::make('form_id')
                                ->label(new HtmlString(self::formatLabel('Form ID')))
                                ->content($this->getFormContent('form_id', $record)),
                            Placeholder::make('version')
                                ->label(new HtmlString(self::formatLabel('Version')))
                                ->content($this->getFormContent('version', $record)),
                            Placeholder::make('request_date')
                                ->label(new HtmlString(self::formatLabel('Request Date')))
                                ->content($this->getFormContent('request_date', $record)),
                        ]),
                    ShowMorePlaceholder::make('requester_note')
                        ->label(new HtmlString(self::formatLabel('Requester Note')))
                        ->showMoreContent(fn() => $this->getFormContent('requester_note', $record))
                        ->maxLength(300)
                        ->columnSpanFull(),
                ]),

            Section::make('Review updated form')
                ->schema([
                    Fieldset::make('Webform')
                        ->schema([
                            Placeholder::make('webform_link')
                                ->label('')
                                ->extraAttributes(['class' => 'prose'])
                                ->content(new HtmlString('<a href="/" class="text-primary-600 hover:text-primary-500 underline">View Webform</a>'))
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
                        ->visible($this->getWebformApprovalVisibility($record)),

                    Fieldset::make('PDF')
                        ->schema([
                            Placeholder::make('pdf_link')
                                ->label('')
                                ->extraAttributes(['class' => 'prose'])
                                ->content(new HtmlString('<a href="/" class="text-primary-600 hover:text-primary-500 underline">View PDF</a>'))
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
                        ->visible($this->getPdfApprovalVisibility($record)),

                    FormActions::make([
                        FormAction::make('submit')
                            ->label('Submit Review')
                            ->color('primary')
                            ->disabled(function () {
                                $record = $this->getApprovalRecord();
                                $webformRequired = $record->webform_approval;
                                $pdfRequired = $record->pdf_approval;

                                $webformComplete = !$webformRequired || ($this->webformApprovalState !== null);
                                $pdfComplete = !$pdfRequired || ($this->pdfApprovalState !== null);

                                $isDisabled = !($webformComplete && $pdfComplete);

                                return $isDisabled;
                            })
                            ->action(function () {
                                $this->handleApprovalSubmission();
                            }),
                    ])
                        ->columnSpanFull()
                        ->alignment('end'),
                ]),
        ];
    }

    protected function handleApprovalSubmission(): void
    {
        $record = $this->getApprovalRecord();
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

        $record->update($updateData);

        $record->formVersion->update([
            'status' => $formVersionStatus,
        ]);

        // Send notification to form developer who requested the approval
        if ($record->requester_id) {
            $formDeveloper = $record->requester;
            if ($formDeveloper) {
                $formDeveloper->notify(new ApprovalDecisionNotification($record, $isApproved));
            }
        }

        // Fall back to the form version developer if requester not found
        elseif ($record->formVersion->form_developer_id) {
            $formDeveloper = $record->formVersion->formDeveloper;
            if ($formDeveloper) {
                $formDeveloper->notify(new ApprovalDecisionNotification($record, $isApproved));
            }
        }

        $this->handlePostSubmissionActions();
    }

    protected function buildApproverNote(): string
    {
        $notes = [];
        $formState = $this->form->getState();
        $record = $this->getApprovalRecord();

        if ($record->webform_approval) {
            if ($this->webformApprovalState === 'approved') {
                $notes[] = 'Webform: Approved';
            } elseif ($this->webformApprovalState === 'rejected') {
                $rejectionReason = $formState['webformRejectionReason'] ?? 'No reason provided';
                $notes[] = 'Webform: Rejected - ' . $rejectionReason;
            }
        }

        if ($record->pdf_approval) {
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
        $record = $this->getApprovalRecord();
        $webformRequired = $record->webform_approval;
        $pdfRequired = $record->pdf_approval;

        $hasRejection = false;

        if ($webformRequired && $this->webformApprovalState === 'rejected') {
            $hasRejection = true;
        }

        if ($pdfRequired && $this->pdfApprovalState === 'rejected') {
            $hasRejection = true;
        }

        return $hasRejection ? 'draft' : 'approved';
    }

    abstract protected function getApprovalRecord();
    abstract protected function getFormContent(string $field, $record);
    abstract protected function getWebformApprovalVisibility($record): bool;
    abstract protected function getPdfApprovalVisibility($record): bool;
    abstract protected function handlePostSubmissionActions(): void;
}
