<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class EditApprovalRequest extends EditRecord
{
    protected static string $resource = ApprovalRequestResource::class;

    protected ?string $heading = 'Review requested update';

    protected ?string $subheading = 'You are receiving this request because you previously requested changes to a form. Please review the updates made and either approve or reject them.';

    protected static function formatLabel(string $text): string
    {
        return '<span class="block text-lg font-bold">' . $text . '</span>';
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
                            ->content(fn($record) => $record->requester_note ?? 'No note provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Review updated form')
                    ->schema([
                        Fieldset::make('Webform')
                            ->schema([
                                Placeholder::make('webform_link')
                                    ->label('')
                                    ->extraAttributes(['class' => 'prose text-primary-500'])
                                    ->content(new HtmlString('<a href="https://filamentphp.com/docs">View Webform</a>'))
                                    ->columnSpanFull(),
                                Placeholder::make('webform_question')
                                    ->label('')
                                    ->content('Do you approve the changes that have been made?')
                                    ->columnSpanFull(),
                                FormActions::make([
                                    Action::make('reject_webform')
                                        ->label('Reject')
                                        ->color('danger')
                                        ->outlined()
                                        ->icon('heroicon-o-x-circle')
                                        ->action(function () {
                                            // TODO: Implement reject logic
                                        }),
                                    Action::make('approve_webform')
                                        ->label('Approve')
                                        ->color('success')
                                        ->outlined()
                                        ->icon('heroicon-o-check-circle')
                                        ->action(function () {
                                            // TODO: Implement approve logic
                                        }),
                                ])
                                    ->columnSpanFull()
                                    ->alignment('start'),
                            ])
                            ->visible(fn($record) => $record->webform_approval === true),

                        Fieldset::make('PDF')
                            ->schema([
                                Placeholder::make('pdf_link')
                                    ->label('')
                                    ->extraAttributes(['class' => 'prose text-primary-500'])
                                    ->content(new HtmlString('<a href="https://filamentphp.com/docs">View PDF</a>'))
                                    ->columnSpanFull(),
                                Placeholder::make('pdf_question')
                                    ->label('')
                                    ->content('Do you approve the changes that have been made?')
                                    ->columnSpanFull(),
                                FormActions::make([
                                    Action::make('reject_pdf')
                                        ->label('Reject')
                                        ->color('danger')
                                        ->outlined()
                                        ->icon('heroicon-o-x-circle')
                                        ->action(function () {
                                            // TODO: Implement reject logic
                                        }),
                                    Action::make('approve_pdf')
                                        ->label('Approve')
                                        ->color('success')
                                        ->outlined()
                                        ->icon('heroicon-o-check-circle')
                                        ->action(function () {
                                            // TODO: Implement approve logic
                                        }),
                                ])
                                    ->columnSpanFull()
                                    ->alignment('start'),
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
            //
        ];
    }
}
