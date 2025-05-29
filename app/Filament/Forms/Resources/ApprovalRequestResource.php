<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\ApprovalRequestResource\Pages;
use App\Models\FormApprovalRequest;
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
    protected static ?string $model = FormApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Approval Requests';

    protected static ?string $title = 'Approval Requests';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'approval-requests';

    public static function shouldRegisterNavigation(): bool
    {
        return static::getModel()::where('approver_id', Auth::id())
            ->orWhere('requester_id', Auth::id())
            ->exists();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('approver_id', Auth::id())
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending form approvals assigned to you';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($query) {
                $query->where('approver_id', Auth::id())
                    ->orWhere('requester_id', Auth::id());
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
                                    ->label('Form Title'),
                                TextEntry::make('formVersion.version_number')
                                    ->label('Version Number'),
                                TextEntry::make('formVersion.form.form_id')
                                    ->label('Form ID'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
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
            'edit' => Pages\EditApprovalRequest::route('/{record}/edit'),
        ];
    }
}
