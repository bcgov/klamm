<?php

namespace App\Filament\Forms\Resources\FormResource\RelationManagers;

use App\Models\FormApprovalRequest;
use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class FormApprovalRequestRelationManager extends RelationManager
{
    protected static string $relationship = 'approvalRequests';

    protected static ?string $recordTitleAttribute = 'status';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Form Approval Requests')
            ->deferLoading()
            ->modifyQueryUsing(function (Builder $query) {
                // Get all approval requests for this form through its versions
                return $query->whereHas('formVersion', function (Builder $subQuery) {
                    $subQuery->where('form_id', $this->ownerRecord->id);
                });
            })
            ->columns([
                TextColumn::make('formVersion.version_number')
                    ->label('Version')
                    ->sortable()
                    ->url(
                        fn(FormApprovalRequest $record): string =>
                        FormVersionResource::getUrl('view', ['record' => $record->formVersion->id])
                    ),
                TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'rejected',
                        'gray' => 'cancelled',
                    ])
                    ->sortable(),
                TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->sortable(),
                TextColumn::make('approver_name')
                    ->label('Approver')
                    ->sortable(),
                TextColumn::make('webform_approval')
                    ->badge()
                    ->label('Webform')
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                    ->colors([
                        'success' => fn($state) => $state === true,
                        'danger' => fn($state) => $state === false,
                    ]),
                TextColumn::make('pdf_approval')
                    ->badge()
                    ->label('PDF')
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                    ->colors([
                        'success' => fn($state) => $state === true,
                        'danger' => fn($state) => $state === false,
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
                    ->placeholder(fn($record) => $record->status === 'cancelled' ? 'Cancelled' : 'Pending'),
            ])
            ->filters([
                SelectFilter::make('form_version_id')
                    ->label('Version ID')
                    ->multiple()
                    ->options(
                        $this->ownerRecord->versions->pluck('version_number', 'id')->toArray()
                    ),
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ])
                    ->attribute('form_approval_requests.status'),
                Filter::make('created_at')
                    ->label('Requested At')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Requested from'),
                        DatePicker::make('created_until')
                            ->label('Requested until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('form_approval_requests.created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('form_approval_requests.created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort(function (Builder $query) {
                return $query->orderByRaw("CASE WHEN form_approval_requests.status = 'pending' THEN 0 ELSE 1 END")
                    ->orderBy('form_approval_requests.created_at', 'desc');
            })
            ->actions([
                ViewAction::make()
                    ->url(
                        fn(FormApprovalRequest $record): string =>
                        \App\Filament\Forms\Resources\ApprovalRequestResource::getUrl('view', ['record' => $record])
                    ),
            ]);
    }
}
