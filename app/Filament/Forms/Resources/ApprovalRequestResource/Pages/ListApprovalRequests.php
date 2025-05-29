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
        return [
            'pending_your_approval' => Tab::make('Pending Your Approval')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approver_id', Auth::id())->where('status', 'pending'))
                ->badge(FormApprovalRequest::where('approver_id', Auth::id())->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'closed_approvals' => Tab::make('Closed Approvals')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approver_id', Auth::id())->where('status', '!=', 'pending')),
            'requested_by_you' => Tab::make('Requested by You')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('requester_id', Auth::id())),
        ];
    }

    public function table(Table $table): Table
    {
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
                    ->visible(fn() => $this->activeTab === 'pending_your_approval' || $this->activeTab === 'closed_approvals'),
                TextColumn::make('approver_name')
                    ->label('Approver')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => $this->activeTab === 'requested_by_you'),
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
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Submit Your Review')
                    ->icon('heroicon-o-check-circle')
                    ->button()
                    ->outlined()
                    ->color('success')
                    ->visible(fn($record) => $record->approver_id === Auth::id() && $this->activeTab === 'pending_your_approval'),
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
