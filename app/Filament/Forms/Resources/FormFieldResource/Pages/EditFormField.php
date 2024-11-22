<?php

namespace App\Filament\Forms\Resources\FormFieldResource\Pages;

use App\Filament\Forms\Resources\FormFieldResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditFormField extends EditRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    if ($this->record->formInstanceFields()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot Delete Form Field')
                            ->body('This form field is in use by one or more form instances and cannot be deleted.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
