<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Actions;

use App\Models\FormApprovalRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Filament\Actions\StaticAction;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use App\Notifications\ApprovalRequestNotification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Gate;

class FormApprovalActions
{
    public static function makeReadyForReviewAction($record, &$additionalApprovers): Action
    {
        return Action::make('readyForReview')
            ->label('Send for Review')
            ->modalHeading('Request approval')
            ->modalDescription(fn() => new HtmlString(
                'Form: ' . $record->form->form_title . '<br>' .
                    'Version: ' . $record->version_number
            ))
            ->form([
                CheckboxList::make('approval_types')
                    ->options([
                        'webform' => 'Webform',
                        'pdf' => 'PDF',
                    ])
                    ->required()
                    ->minItems(1)
                    ->columns(1)
                    ->label('Select version(s) for approval'),
                Textarea::make('requester_note')
                    ->label('Note for approver')
                    ->required(),
                Radio::make('approver')
                    ->label('Select approver')
                    ->options(function () use ($record, &$additionalApprovers) {
                        $businessAreaUsers = $record->form->businessAreas->flatMap->users
                            ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                            ->toArray();

                        $allOptions = $businessAreaUsers;
                        foreach ($additionalApprovers as $key => $value) {
                            $allOptions[$key] = $value;
                        }

                        return $allOptions;
                    })
                    ->required(),
            ])
            ->action(function (array $data, $livewire) use ($record): void {
                self::processApprovalRequest($data, $record);
                $record->refresh();
                $livewire->refreshFormData(['status']);
            })
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn(StaticAction $action) => $action->label('Submit request'))
            ->extraModalFooterActions([
                self::makeAddNewApproverAction($record, $additionalApprovers)
            ])
            ->visible(fn() => $record->status === 'draft' && Gate::allows('form-developer'));
    }

    public static function makeChangeApproverAction($approvalRequest): Action
    {
        return Action::make('changeApprover')
            ->label('Change Approver')
            ->icon('heroicon-o-arrow-path')
            ->modalHeading('Change Approver')
            ->modalDescription(fn() => new HtmlString(
                'Form: ' . $approvalRequest->formVersion->form->form_title . '<br>' .
                    'Version: ' . $approvalRequest->formVersion->version_number . '<br>' .
                    'Current Approver: ' . $approvalRequest->approver_name
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
                    ->default(function () use ($approvalRequest) {
                        return $approvalRequest->is_klamm_user ? 'klamm' : 'non_klamm';
                    }),
                Select::make('klamm_user')
                    ->searchable()
                    ->label('Select User')
                    ->options(function () use ($approvalRequest) {
                        $businessAreaUsers = $approvalRequest->formVersion->form->businessAreas->flatMap->users
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
                    ->default(function () use ($approvalRequest) {
                        return $approvalRequest->is_klamm_user ? $approvalRequest->approver_id : null;
                    }),
                TextInput::make('name')
                    ->required()
                    ->visible(fn(Get $get) => $get('approver_type') === 'non_klamm')
                    ->default(function () use ($approvalRequest) {
                        return !$approvalRequest->is_klamm_user ? $approvalRequest->approver_name : null;
                    }),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->visible(fn(Get $get) => $get('approver_type') === 'non_klamm')
                    ->default(function () use ($approvalRequest) {
                        return !$approvalRequest->is_klamm_user ? $approvalRequest->approver_email : null;
                    }),
            ])
            ->action(function (array $data) use ($approvalRequest): void {
                self::processChangeApprover($data, $approvalRequest);
            })
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn(StaticAction $action) => $action->label('Change Approver'))
            ->visible(fn() => $approvalRequest->status === 'pending' && Gate::allows('form-developer'));
    }

    public static function makeAddNewApproverAction($record, &$additionalApprovers): Action
    {
        return Action::make('addNewApprover')
            ->label('Add new approver')
            ->modalWidth('lg')
            ->modalHeading('Add a new approver')
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
                    ->live(),
                Select::make('klamm_user')
                    ->searchable()
                    ->label('Select User')
                    ->options(function () use ($record, &$additionalApprovers) {
                        $businessAreaUserIds = $record->form->businessAreas->flatMap->users->pluck('id')->toArray();
                        $additionalKlammUserIds = collect($additionalApprovers)
                            ->keys()
                            ->filter(fn($key) => is_numeric($key))
                            ->toArray();

                        $excludedIds = array_merge($businessAreaUserIds, $additionalKlammUserIds);

                        return User::whereNotIn('id', $excludedIds)
                            ->get()
                            ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                            ->toArray();
                    })
                    ->getSearchResultsUsing(function (string $search) use ($record, &$additionalApprovers) {
                        $businessAreaUserIds = $record->form->businessAreas->flatMap->users->pluck('id')->toArray();
                        $additionalKlammUserIds = collect($additionalApprovers)
                            ->keys()
                            ->filter(fn($key) => is_numeric($key))
                            ->toArray();

                        $excludedIds = array_merge($businessAreaUserIds, $additionalKlammUserIds);

                        return User::where(function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                            ->whereNotIn('id', $excludedIds)
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                            ->toArray();
                    })
                    ->required()
                    ->visible(fn(Get $get) => $get('approver_type') === 'klamm'),
                TextInput::make('name')
                    ->required()
                    ->visible(fn(Get $get) => $get('approver_type') === 'non_klamm'),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->visible(fn(Get $get) => $get('approver_type') === 'non_klamm'),
            ])
            ->action(function (array $data) use (&$additionalApprovers): void {
                if ($data['approver_type'] === 'klamm') {
                    $user = User::find($data['klamm_user']);
                    if ($user) {
                        $additionalApprovers[$user->id] = $user->name . ' (' . $user->email . ')';
                    }
                } else {
                    $key = $data['name'] . '|' . $data['email'];
                    $additionalApprovers[$key] = $data['name'] . ' (' . $data['email'] . ')';
                }
            })
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(fn(StaticAction $action) => $action->label('Add new approver'))
            ->modalCancelAction(fn(StaticAction $action) => $action->label('Back'));
    }

    private static function processApprovalRequest(array $data, $record): void
    {
        if (is_numeric($data['approver'])) {
            self::createKlammUserApprovalRequest($data, $record);
        } else {
            self::createNonKlammUserApprovalRequest($data, $record);
        }

        $record->update(['status' => 'under_review']);

        Notification::make()
            ->title('Approval request sent successfully')
            ->success()
            ->send();
    }

    public static function processChangeApprover(array $data, $approvalRequest): void
    {
        $hasChanged = false;

        if ($data['approver_type'] === 'klamm') {
            $newUser = User::find($data['klamm_user']);

            if (!$newUser) {
                Notification::make()
                    ->title('Error: User not found')
                    ->danger()
                    ->send();
                return;
            }

            $hasChanged = !$approvalRequest->is_klamm_user ||
                $approvalRequest->approver_id !== $newUser->id;

            if ($hasChanged) {
                $approvalRequest->update([
                    'approver_id' => $newUser->id,
                    'approver_name' => $newUser->name,
                    'approver_email' => $newUser->email,
                    'is_klamm_user' => true,
                    'token' => null,
                ]);

                $newUser->notify(new ApprovalRequestNotification($approvalRequest));
            }
        } else {
            $hasChanged = $approvalRequest->is_klamm_user ||
                $approvalRequest->approver_name !== $data['name'] ||
                $approvalRequest->approver_email !== $data['email'];

            $shouldGenerateNewToken = !$approvalRequest->is_klamm_user || $hasChanged;

            if ($hasChanged) {
                $approvalRequest->update([
                    'approver_id' => null,
                    'approver_name' => $data['name'],
                    'approver_email' => $data['email'],
                    'is_klamm_user' => false,
                    'token' => Str::uuid(),
                ]);

                NotificationFacade::route('mail', $data['email'])
                    ->notify(new ApprovalRequestNotification($approvalRequest));
            } elseif ($shouldGenerateNewToken) {
                $approvalRequest->update([
                    'token' => Str::uuid(),
                ]);

                NotificationFacade::route('mail', $data['email'])
                    ->notify(new ApprovalRequestNotification($approvalRequest));

                $hasChanged = true;
            }
        }

        if ($hasChanged) {
            Notification::make()
                ->title('Approver changed successfully')
                ->body('A notification has been sent to the new approver.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('No changes made')
                ->body('The approver information is the same as before.')
                ->warning()
                ->send();
        }
    }

    private static function createKlammUserApprovalRequest(array $data, $record): void
    {
        $user = User::find($data['approver']);

        if (!$user) {
            Notification::make()
                ->title('Error: User not found')
                ->danger()
                ->send();
            return;
        }

        $approvalRequestData = [
            'form_version_id' => $record->id,
            'requester_id' => Auth::user()->id,
            'approver_id' => $user->id,
            'approver_name' => $user->name,
            'approver_email' => $user->email,
            'requester_note' => $data['requester_note'],
            'webform_approval' => in_array('webform', $data['approval_types']),
            'pdf_approval' => in_array('pdf', $data['approval_types']),
            'is_klamm_user' => true,
            'status' => 'pending',
        ];

        $approvalRequest = FormApprovalRequest::create($approvalRequestData);

        $user->notify(new ApprovalRequestNotification($approvalRequest));
    }

    private static function createNonKlammUserApprovalRequest(array $data, $record): void
    {
        $approverData = explode('|', $data['approver']);

        if (count($approverData) !== 2) {
            Notification::make()
                ->title('Error: Invalid approver data')
                ->danger()
                ->send();
            return;
        }

        $approvalRequestData = [
            'form_version_id' => $record->id,
            'requester_id' => Auth::user()->id,
            'approver_name' => $approverData[0],
            'approver_email' => $approverData[1],
            'requester_note' => $data['requester_note'],
            'webform_approval' => in_array('webform', $data['approval_types']),
            'pdf_approval' => in_array('pdf', $data['approval_types']),
            'is_klamm_user' => false,
            'status' => 'pending',
            'token' => Str::uuid(),
        ];

        $approvalRequest = FormApprovalRequest::create($approvalRequestData);

        NotificationFacade::route('mail', $approverData[1])
            ->notify(new ApprovalRequestNotification($approvalRequest));
    }
}
