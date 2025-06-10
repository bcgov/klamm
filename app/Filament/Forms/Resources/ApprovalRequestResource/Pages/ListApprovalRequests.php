<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use App\Filament\Forms\Resources\FormVersionResource\Actions\FormApprovalActions;
use App\Models\FormApprovalRequest;
use App\Models\User;
use App\Traits\HasBusinessAreaAccess;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
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
    use HasBusinessAreaAccess;

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

        $developerTabs = [
            'my_requests' => Tab::make('My Requests')
                ->modifyQueryUsing(fn(Builder $query) => $query->where(function ($subQuery) use ($user) {
                    $subQuery->where('requester_id', Auth::id())
                        ->orWhereHas('formVersion', function (Builder $formQuery) use ($user) {
                            $formQuery->where('form_developer_id', Auth::id());
                        });
                })
                    ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                    ->orderBy('created_at', 'desc'))
                ->badge(FormApprovalRequest::where('status', 'pending')
                    ->where(function ($query) use ($user) {
                        $query->where('requester_id', Auth::id())
                            ->orWhereHas('formVersion', function (Builder $subQuery) use ($user) {
                                $subQuery->where('form_developer_id', Auth::id());
                            });
                    })->count())
                ->badgeColor('warning'),
        ];

        $adminTabs = [
            'all_requests' => Tab::make('All Requests')
                ->modifyQueryUsing(
                    fn() =>
                    FormApprovalRequest::query()
                        ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                        ->orderBy('created_at', 'desc')
                ),
        ];

        $userBusinessAreaIds = $this->getUserBusinessAreaIds();

        $businessAreaRequests = FormApprovalRequest::where('status', 'pending')
            ->where(function ($query) use ($user, $userBusinessAreaIds) {
                $query->where('approver_id', $user->id)
                    ->orWhere(function ($businessAreaQuery) use ($userBusinessAreaIds) {
                        if (!empty($userBusinessAreaIds)) {
                            $businessAreaQuery->whereHas('formVersion.form.businessAreas', function ($formBusinessAreaQuery) use ($userBusinessAreaIds) {
                                $formBusinessAreaQuery->whereIn('business_areas.id', $userBusinessAreaIds);
                            });
                        }
                    });
            });

        return [
            ...($user && Gate::allows('form-developer') || Gate::allows('admin') ? $developerTabs : []),
            'assigned_to_me' => Tab::make('Assigned to Me')
                ->modifyQueryUsing(fn() => FormApprovalRequest::where('approver_id', Auth::id())->where('status', 'pending')
                    ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                    ->orderBy('created_at', 'desc'))
                ->badge(FormApprovalRequest::where('approver_id', Auth::id())->where('status', 'pending')->count())
                ->badgeColor('warning'),

            ...!empty($userBusinessAreaIds) ? ['business_areas' => Tab::make('My Business Areas')
                ->modifyQueryUsing(fn() => $businessAreaRequests
                    ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                    ->orderBy('created_at', 'desc'))
                ->badge(fn() => $businessAreaRequests->count())] : [],

            'reviewed_forms' => Tab::make('Reviewed Forms')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approver_id', Auth::id())->where('status', '!=', 'pending')),
            ...($user && Gate::allows('admin') ? $adminTabs : []),
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
                    ->visible(fn() => !$isFormDeveloper || $this->activeTab === 'all_requests' || $this->activeTab === 'business_areas'),
                TextColumn::make('approver_name')
                    ->label('Approver')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => $isFormDeveloper || $this->activeTab === 'business_areas'),
                TextColumn::make('request_decision')
                    ->label('Decision')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->approved_at) {
                            return 'approved';
                        }
                        if ($record->rejected_at) {
                            return 'rejected';
                        }
                        if ($record->status === 'cancelled') {
                            return 'cancelled';
                        }
                        return 'pending';
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'cancelled',
                    ])
                    ->placeholder(fn($record) => $record->status === 'cancelled' ? 'cancelled' : 'pending'),
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
                Tables\Filters\SelectFilter::make('requested_by')
                    ->label('Requested By')
                    ->options(function () {
                        return FormApprovalRequest::query()
                            ->selectRaw('requester_id')
                            ->distinct()
                            ->get()
                            ->mapWithKeys(function ($row) {
                                $user = User::find($row->requester_id);
                                $label = $user ? $user->name : 'Unknown';
                                return [$row->requester_id => $label];
                            })
                            ->toArray();
                    })
                    ->visible(fn() => !$isFormDeveloper || $this->activeTab === 'all_requests' || $this->activeTab === 'business_areas')
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value']) || !$data['value']) {
                            return $query;
                        }
                        return $query->where('requester_id', $data['value']);
                    }),

                Tables\Filters\SelectFilter::make('approver')
                    ->label('Approver')
                    ->options(function () {
                        return FormApprovalRequest::query()
                            ->selectRaw('COALESCE(CAST(approver_id AS CHAR), approver_email) as key_val, approver_name, approver_email')
                            ->distinct()
                            ->get()
                            ->mapWithKeys(function ($row) {
                                $label = $row->approver_name ?: ($row->approver_email ?: 'Unknown');
                                return [$row->key_val => $label . ($row->approver_email ? " ({$row->approver_email})" : '')];
                            })
                            ->toArray();
                    })
                    ->visible(fn() => $isFormDeveloper || $this->activeTab === 'business_areas')
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value']) || !$data['value']) {
                            return $query;
                        }
                        if (is_numeric($data['value'])) {
                            return $query->where('approver_id', $data['value']);
                        }
                        return $query->where('approver_email', $data['value']);
                    }),

                Tables\Filters\SelectFilter::make('decision')
                    ->label('Decision')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value']) || $data['value'] === null) {
                            return $query;
                        }
                        $decision = $data['value'];
                        if ($decision === 'approved') {
                            return $query->whereNotNull('approved_at');
                        }
                        if ($decision === 'rejected') {
                            return $query->whereNotNull('rejected_at');
                        }
                        if ($decision === 'cancelled') {
                            return $query->where('status', 'cancelled');
                        }
                        return $query->whereNull('approved_at')->whereNull('rejected_at')->where('status', '!=', 'cancelled');
                    }),
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
                        $this->activeTab == 'assigned_to_me' &&
                            $record->approver_id === Auth::id() &&
                            $record->status === 'pending'
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
                    // Give admins and form developers ability to change approver
                    ->visible(
                        fn($record) => ($isFormDeveloper || Gate::allows('admin')) &&
                            $record->status === 'pending' &&
                            ($record->formVersion->form_developer_id === Auth::id() || $record->requester_id === Auth::id() || Gate::allows('admin'))
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
