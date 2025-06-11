<?php

namespace App\Filament\Forms\Resources\FormFieldResource\Pages;

use App\Filament\Forms\Resources\FormFieldResource;
use App\Helpers\DateFormatHelper;
use App\Models\FormFieldDateFormat;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use App\Models\FormFieldValue;
use App\Models\SelectOptionInstance;
use Filament\Notifications\Notification;
use App\Helpers\FormTemplateHelper;

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

        $dateFormatObj = $this->record->formFieldDateFormat()->first();
        $data['date_format'] = array_search($dateFormatObj?->date_format, DateFormatHelper::dateFormats());

        $data['select_option_instances'] = $this->record->selectOptionInstances->map(fn($instance) => [
            'type' => 'select_option_instance',
            'data' => [
                'select_option_id' => $instance->select_option_id,
                'order' => $instance->order,
            ],
        ])->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        $formField = $this->record;

        $this->createFormFieldValue($formField);
        $this->createSelectOptionInstance($formField);

        FormTemplateHelper::clearAllFormTemplateCaches();
        $this->createFormFieldDateFormat($formField);
    }

    private function createFormFieldValue($formField)
    {
        if (method_exists($this, 'getRecord')) {
            $formField->formFieldValue()->delete();
        }

        $formFieldValue = $this->form->getState()['value'] ?? null;
        if ($formFieldValue) {
            FormFieldValue::create([
                'form_field_id' => $formField->id,
                'value' => $formFieldValue ?? null,
            ]);
        }
    }

    private function createSelectOptionInstance($formField)
    {
        if (method_exists($this, 'getRecord')) {
            $formField->selectOptionInstances()->delete();
        }

        $selectOptions = $this->form->getState()['select_option_instances'] ?? [];
        foreach ($selectOptions as $index => $instance) {
            SelectOptionInstance::create([
                'form_field_id' => $formField->id,
                'select_option_id' => $instance['data']['select_option_id'],
                'order' => $index + 1,
            ]);
        }
    }

    private function createFormFieldDateFormat($formField)
    {
        if (method_exists($this, 'getRecord')) {
            $formField->formFieldDateFormat()->delete();
        }

        $dateFormat = $this->form->getState()['date_format'] ?? null;
        if ($dateFormat) {
            FormFieldDateFormat::create([
                'form_field_id' => $formField->id,
                'date_format' => $dateFormat ?? null,
            ]);
        }
    }
}
