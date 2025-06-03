<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use App\Filament\Forms\Resources\FormVersionResource\Actions\FormApprovalActions;
use App\Models\FormApprovalRequest;
use App\Models\User;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Actions\StaticAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Components\Tab;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

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
                Tables\Actions\Action::make('changeApprover')
                    ->label('Change Approver')
                    ->icon('heroicon-o-arrow-path')
                    ->modalHeading('Change Approver')
                    ->modalDescription(fn($record) => new HtmlString(
                        'Form: ' . $record->formVersion->form->form_title . '<br>' .
                            'Version: ' . $record->formVersion->version_number . '<br>' .
                            'Current Approver: ' . $record->approver_name
                    ))
                    ->form([
                        Radio::make('approver_type')
                            ->label('Approver Type')
                            ->options([
                                'klamm' => 'Klamm user',
                                'non_klamm' => 'Non Klamm user',
                            ])
                            ->descriptions([
                                'klamm' => 'Best for approvers who do a lot of reviews and want to stay updated on their form status',
                                'non_klamm' => 'Best for occasional approvers. They\'ll receive a one-time approval link and access Klamm with their IDIR credentials',
                            ])
                            ->required()
                            ->live()
                            ->default(function ($record) {
                                return $record->is_klamm_user ? 'klamm' : 'non_klamm';
                            }),
                        Select::make('klamm_user')
                            ->searchable()
                            ->label('Select User')
                            ->options(function ($record) {
                                $businessAreaUsers = $record->formVersion->form->businessAreas->flatMap->users
                                    ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                                    ->toArray();

                                $allKlammUsers = User::all()
                                    ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                                    ->toArray();

                                return array_merge($businessAreaUsers, $allKlammUsers);
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                return User::where(function ($query) use ($search) {
                                    $query->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                                    ->toArray();
                            })
                            ->required()
                            ->visible(fn(Get $get) => $get('approver_type') === 'klamm')
                            ->default(function ($record) {
                                return $record->is_klamm_user ? User::find($record->approver_id)?->name : null;
                            }),
                        TextInput::make('name')
                            ->required()
                            ->visible(fn(Get $get) => $get('approver_type') === 'non_klamm')
                            ->default(function ($record) {
                                return !$record->is_klamm_user ? $record->approver_name : null;
                            }),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->visible(fn(Get $get) => $get('approver_type') === 'non_klamm')
                            ->default(function ($record) {
                                return !$record->is_klamm_user ? $record->approver_email : null;
                            }),
                    ])
                    ->action(function (array $data, $record): void {
                        FormApprovalActions::processChangeApprover($data, $record);
                    })
                    ->closeModalByClickingAway(false)
                    ->modalSubmitAction(fn(StaticAction $action) => $action->label('Change Approver'))
                    ->visible(
                        fn($record) =>
                        $isFormDeveloper &&
                            $record->status === 'pending' &&
                            $record->formVersion->form_developer_id === Auth::id()
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
