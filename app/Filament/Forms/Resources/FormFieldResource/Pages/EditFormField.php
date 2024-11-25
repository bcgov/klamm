<?php

namespace App\Filament\Forms\Resources\FormFieldResource\Pages;

use App\Filament\Forms\Resources\FormFieldResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use App\Models\FormFieldValue;
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
                    if ($this->record->formVersions()->exists()) {

                        $formVersions = $this->record->formVersions()->with('form')->get();

                        $versionsList = $formVersions->map(function ($version) {
                            $formName = $version->form->form_title ? $version->form->form_id : 'Unknown Form';
                            $trimmedFormName = strlen($formName) > 20
                                ? substr($formName, 0, 17) . '...'
                                : $formName;
                            $versionNumber = $version->version_number ?? 'Unknown Version';
                            return 'Form: ' . $trimmedFormName . ', V: ' . $versionNumber . '<br />';
                        })->implode("");

                        Notification::make()
                            ->danger()
                            ->title('Cannot Delete Form Field')
                            ->body('This form field is in use by the following form versions and cannot be deleted:' . "<br /></br />" . $versionsList)
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = array_merge($this->record->toArray(), $data);    

        $formFieldValueObj = $this->record->formFieldValue()->first();        
        $data['value'] = $formFieldValueObj?->value;

        return $data;
    }

    protected function afterSave(): void
    {
        $formField = $this->record;
        $formFieldValue = $this->form->getState()['value'] ?? null;   
        
        if (method_exists($this, 'getRecord')) {
            $formField->formFieldValue()->delete();            
        }
        
        if($formFieldValue) {
            FormFieldValue::create([
                'form_field_id' => $formField->id,                        
                'value' => $formFieldValue ?? null,                        
            ]);
        }        
    }
}
