<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('resetPassword')
                ->label('Reset Password')
                ->modalHeading('Reset User Password')
                ->form([
                    Forms\Components\TextInput::make('new_password')
                        ->label('New Password')
                        ->password()
                        ->required(),
                    Forms\Components\TextInput::make('confirm_password')
                        ->label('Confirm Password')
                        ->password()
                        ->required()
                        ->same('new_password'),
                ])
                ->action(function ($data) {
                    $this->record->update([
                        'password' => Hash::make($data['new_password']),
                    ]);
                    Notification::make()
                        ->title("Password reset successfully!")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
        ];
    }
}
