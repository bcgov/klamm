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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Form Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('formVersion.form.form_title')
                                    ->label('Form Title')
                                    ->icon('heroicon-o-document-text')
                                    ->url(fn(FormApprovalRequest $record): string => FormResource::getUrl('view', ['record' => $record->formVersion->form_id])),
                                TextEntry::make('formVersion.version_number')
                                    ->label('Version Number')
                                    ->icon('heroicon-o-inbox-stack')
                                    ->url(fn(FormApprovalRequest $record): string => FormVersionResource::getUrl('view', ['record' => $record->formVersion->id])),
                                TextEntry::make('formVersion.form.form_id')
                                    ->label('Form ID'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'completed' => 'success',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),

                Section::make('Request Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('requester.name')
                                    ->label('Requested By'),
                                TextEntry::make('approver_name')
                                    ->label('Approver'),
                                TextEntry::make('created_at')
                                    ->label('Request Date')
                                    ->dateTime(),
                                TextEntry::make('requester_note')
                                    ->label('Request Note')
                                    ->columnSpanFull()
                                    ->markdown(),
                            ]),
                    ]),

                Section::make('Approval Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('webform_approval')
                                    ->label('Webform Approval')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'danger'),
                                TextEntry::make('pdf_approval')
                                    ->label('PDF Approval')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'danger'),
                                TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->dateTime()
                                    ->placeholder('Not approved yet'),
                                TextEntry::make('rejected_at')
                                    ->label('Rejected At')
                                    ->dateTime()
                                    ->placeholder('Not rejected'),
                                TextEntry::make('approver_note')
                                    ->label('Approver Note')
                                    ->columnSpanFull()
                                    ->markdown()
                                    ->placeholder('No note provided'),
                            ]),
                    ]),
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
}
