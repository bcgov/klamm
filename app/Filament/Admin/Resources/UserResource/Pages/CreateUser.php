<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->submit(null)
            ->requiresConfirmation()
            ->modalDescription('An email invitation will be sent to this user.')
            ->action(function () {
                $this->closeActionModal();
                $this->create();
            });
    }

    protected function afterCreate(): void
    {
        $this->record->notify(new \App\Notifications\AccountCreatedNotification());
    }
}
