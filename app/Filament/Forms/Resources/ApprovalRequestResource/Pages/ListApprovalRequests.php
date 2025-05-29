<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use App\Models\FormApprovalRequest;
use Filament\Actions;
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

    public function getTabs(): array
    {
        return [
            'approvals_for_you' => Tab::make('Approvals for You')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approver_id', Auth::id()))
                ->badge(FormApprovalRequest::where('approver_id', Auth::id())->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'your_requests' => Tab::make('Your Requests')
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
                    ->visible(fn() => $this->activeTab === 'approvals_for_you'),
                TextColumn::make('approver_name')
                    ->label('Approver')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => $this->activeTab === 'your_requests'),
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
                TextColumn::make('requester_note')
                    ->label('Request Note')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->visible(fn() => $this->activeTab === 'approvals_for_you'),
                TextColumn::make('approver_note')
                    ->label('Approver Note')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->visible(fn() => $this->activeTab === 'your_requests'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->approver_id === Auth::id()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
