<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\ApprovalRequestResource\Pages;
use App\Models\FormApprovalRequest;
use App\Traits\HasBusinessAreaAccess;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ApprovalRequestResource extends Resource
{
    use HasBusinessAreaAccess;

    protected static ?string $model = FormApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Approval Requests';

    protected static ?string $title = 'Approval Requests';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'approval-requests';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        if ($user && (Gate::allows('form-developer') || Gate::allows('admin'))) {
            return true;
        }

        $instance = new static();
        $userBusinessAreaIds = $instance->getUserBusinessAreaIds();

        return static::getModel()::where(function ($query) use ($user, $userBusinessAreaIds) {
            $query->where('approver_id', $user->id)
                ->orWhere('requester_id', $user->id);

            if (!empty($userBusinessAreaIds)) {
                $query->orWhereHas('formVersion.form.businessAreas', function ($subQuery) use ($userBusinessAreaIds) {
                    $subQuery->whereIn('business_areas.id', $userBusinessAreaIds);
                });
            }
        })->exists();
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if ($user && Gate::allows('form-developer')) {
            $count = static::getModel()::where('status', 'pending')
                ->where(function ($query) {
                    $query->where('requester_id', Auth::id())
                        ->orWhereHas('formVersion', function (Builder $subQuery) {
                            $subQuery->where('form_developer_id', Auth::id());
                        });
                })
                ->count();
        } else {
            $count = static::getModel()::where('approver_id', Auth::id())
                ->where('status', 'pending')
                ->count();
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $user = Auth::user();

        if ($user && Gate::allows('form-developer')) {
            return 'Pending form approvals for your forms';
        }

        return 'Pending form approvals assigned to you';
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        // Admins can see all approval requests
        if ($user && Gate::allows('admin')) {
            return parent::getEloquentQuery();
        }

        $instance = new static();
        $userBusinessAreaIds = $instance->getUserBusinessAreaIds();

        if ($user && Gate::allows('form-developer')) {
            return parent::getEloquentQuery()
                ->where(function ($query) use ($user, $userBusinessAreaIds) {
                    $query->where('requester_id', $user->id)
                        ->orWhere('approver_id', $user->id)
                        ->orWhereHas('formVersion', function (Builder $subQuery) use ($user) {
                            $subQuery->where('form_developer_id', $user->id);
                        });

                    if (!empty($userBusinessAreaIds)) {
                        $query->orWhereHas('formVersion.form.businessAreas', function ($subQuery) use ($userBusinessAreaIds) {
                            $subQuery->whereIn('business_areas.id', $userBusinessAreaIds);
                        });
                    }
                });
        }

        return parent::getEloquentQuery()
            ->where(function ($query) use ($user, $userBusinessAreaIds) {
                $query->where('approver_id', $user->id)
                    ->orWhere('requester_id', $user->id);
                if (!empty($userBusinessAreaIds)) {
                    $query->orWhereHas('formVersion.form.businessAreas', function ($subQuery) use ($userBusinessAreaIds) {
                        $subQuery->whereIn('business_areas.id', $userBusinessAreaIds);
                    });
                }
            });
    }

    private static function parseApprovalStatus(string $text, string $type): array
    {
        $type = strtolower($type);
        preg_match_all('/(Webform|PDF):\s*(Rejected|Approved)\s*-?\s*([^P]*)?/i', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $label = strtolower($match[1]);
            $status = strtolower($match[2]);
            $reason = trim($match[3] ?? '');

            if ($label === $type) {
                return [
                    'approved' => $status === 'approved',
                    'reason' => $reason,
                ];
            }
        }
        return [
            'approved' => null,
            'reason' => null,
        ];
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $approvalStatusWebform = $infolist->getRecord()->approver_note ? static::parseApprovalStatus($infolist->getRecord()->approver_note, 'webform') : null;
        $approvalStatusPdf = $infolist->getRecord()->approver_note ? static::parseApprovalStatus($infolist->getRecord()->approver_note, 'pdf') : null;

        return $infolist
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('approved_at')
                                    ->label('')
                                    ->getStateUsing(function ($record) {
                                        if ($record->status !== 'completed') {
                                            return $record->status === 'pending' ? 'Pending Review' : 'Request Cancelled';
                                        }
                                        return $record->approved_at ? 'Review Approved' : 'Review Rejected';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        if ($record->status === 'cancelled') {
                                            return 'danger';
                                        }
                                        if ($record->status === 'completed') {
                                            return $record->approved_at ? 'success' : 'danger';
                                        }
                                        return 'warning';
                                    }),
                                TextEntry::make('formVersion.form.form_title')
                                    ->label('Form')
                                    ->icon('heroicon-o-document-text')
                                    ->url(fn(FormApprovalRequest $record): string => FormResource::getUrl('view', ['record' => $record->formVersion->form_id])),
                                TextEntry::make('formVersion.version_number')
                                    ->label('Version')
                                    ->icon('heroicon-o-inbox-stack')
                                    ->url(fn(FormApprovalRequest $record): string => FormVersionResource::getUrl('view', ['record' => $record->formVersion->id])),
                            ]),
                    ]),

                Section::make('Request Details')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('requester.name')
                                    ->label('Requester'),
                                TextEntry::make('created_at')
                                    ->label('Request Date')
                                    ->dateTime(),
                                TextEntry::make('requester_note')
                                    ->label('Request Note')
                                    ->columnSpanFull()
                                    ->markdown(),
                            ]),
                    ])
                    ->collapsible(true),

                Section::make('Review Details')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('approver_name')
                                    ->label('Reviewer')
                                    ->url(fn($record) => $record->approver_email ? 'mailto:' . $record->approver_email : null)
                                    ->formatStateUsing(fn($state, $record) => $state ?: ($record->approver_email ?: 'Unknown'))
                                    ->tooltip(fn($record) => $record->approver_email ? 'Email ' . $record->approver_email : null)
                                    ->extraAttributes([
                                        'aria-label' => 'Email reviewer',
                                        'tabindex' => 0,
                                    ]),
                                TextEntry::make('decision_date')
                                    ->label('Review Date')
                                    ->getStateUsing(function ($record) {
                                        return $record->approved_at ?? $record->rejected_at;
                                    })
                                    ->dateTime()
                                    ->placeholder(fn($record) => $record->status === 'cancelled' ? 'Cancelled' : 'Pending'),
                                TextEntry::make('webform_approval')
                                    ->label('Webform Approval Requested')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'danger')
                                    ->visible(fn($record) => $record->status === 'pending' && $record->webform_approval !== null),
                                TextEntry::make('pdf_approval')
                                    ->label('PDF Approval Requested')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'danger')
                                    ->visible(fn($record) => $record->status === 'pending' && $record->pdf_approval !== null),
                                TextEntry::make('approver_note')
                                    ->label('Webform')
                                    ->columnSpanFull()
                                    ->formatStateUsing(fn() => $approvalStatusWebform['approved'] ? 'Review Approved' : ' Review Rejected')
                                    ->badge()
                                    ->color(fn() => $approvalStatusWebform['approved'] ? 'success' : 'danger')
                                    ->markdown()
                                    ->placeholder('No note provided')
                                    ->visible(fn($record) => $record->status === 'completed' && $record->webform_approval),
                                TextEntry::make('approver_note')
                                    ->label('Reasons for Rejection')
                                    ->columnSpanFull()
                                    ->formatStateUsing(fn() => $approvalStatusWebform['reason'] ?? 'No reason provided')
                                    ->markdown()
                                    ->placeholder('No note provided')
                                    ->visible(fn($record) => $record->status === 'completed' &&  $approvalStatusWebform['approved'] === false),
                                TextEntry::make('approver_note')
                                    ->label('PDF')
                                    ->columnSpanFull()
                                    ->formatStateUsing(fn() => $approvalStatusPdf['approved'] ? 'Review Approved' : ' Review Rejected')
                                    ->badge()
                                    ->color(fn() => $approvalStatusPdf['approved'] ? 'success' : 'danger')
                                    ->markdown()
                                    ->placeholder('No note provided')
                                    ->visible(fn($record) => $record->status === 'completed' && $record->pdf_approval),
                                TextEntry::make('approver_note')
                                    ->label('Reasons for Rejection')
                                    ->columnSpanFull()
                                    ->markdown()
                                    ->formatStateUsing(fn() => $approvalStatusPdf['reason'] ?? 'No reason provided')
                                    ->placeholder('No note provided')
                                    ->visible(fn($record) => $record->status === 'completed' &&   $approvalStatusPdf['approved'] === false),

                            ]),
                    ])
                    ->collapsible(true),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalRequests::route('/'),
            'view' => Pages\ViewApprovalRequest::route('/{record}'),
            'edit' => Pages\EditApprovalRequest::route('/{record}/review'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Approval Request';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Approval Requests';
    }
}
