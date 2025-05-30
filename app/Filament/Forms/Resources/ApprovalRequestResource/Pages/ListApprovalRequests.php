<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use App\Models\FormApprovalRequest;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Components\Tab;
use Illuminate\Support\Facades\Gate;

class ListApprovalRequests extends ListRecords
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            //
        ];
    }

    public function getTabs(): array
    {
        $user = Auth::user();

        if ($user && Gate::allows('form-developer')) {
            return [];
        }

        return [
            'all_forms' => Tab::make('All Forms')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approver_id', Auth::id())
                    ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                    ->orderBy('created_at', 'desc'))
                ->badge(FormApprovalRequest::where('approver_id', Auth::id())->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'reviewed_forms' => Tab::make('Reviewed Forms')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approver_id', Auth::id())->where('status', '!=', 'pending')),
        ];
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $isFormDeveloper = $user && Gate::allows('form-developer');

        return $table
            ->columns([
                TextColumn::make('formVersion.form.form_title')
                    ->label('Form Title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('formVersion.version_number')
                    ->label('Version')
                    ->sortable(),
                TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => !$isFormDeveloper),
                TextColumn::make('approver_name')
                    ->label('Approver')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => $isFormDeveloper),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('decision_date')
                    ->label('Decision Date')
                    ->getStateUsing(function ($record) {
                        return $record->approved_at ?? $record->rejected_at;
                    })
                    ->dateTime()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("COALESCE(approved_at, rejected_at) $direction");
                    })
                    ->placeholder('Pending'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Review')
                    ->icon('heroicon-o-check-circle')
                    ->button()
                    ->outlined(false)
                    ->color('primary')
                    ->extraAttributes(['style' => 'background-color: #013366; border-color: #013366;'])
                    ->visible(
                        fn($record) =>
                        !$isFormDeveloper &&
                            $record->approver_id === Auth::id() &&
                            $record->status === 'pending' &&
                            ($this->activeTab === 'all_forms' || $this->activeTab === null)
                    ),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                //
            ])
            ->recordUrl(
                fn($record) => ApprovalRequestResource::getUrl('view', ['record' => $record])
            )
            ->defaultSort('created_at', 'desc');
    }
}
