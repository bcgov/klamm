<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;

class CreateFormVersion extends CreateRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function canCreate(): bool
    {
        return Gate::allows('form-developer');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        $data['updater_name'] = $user->name;
        $data['updater_email'] = $user->email;

        unset($data['components']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }

    public function mount(): void
    {
        parent::mount();

        $formId = request()->query('form_id');
        if ($formId) {
            $this->form->fill(['form_id' => $formId]);
        }
    }

    protected function beforeSave(): void
    {
        //
    }

    protected function afterSave(): void
    {
        $formVersion = $this->record;
        $components = $this->form->getState()['components'] ?? [];

        if (method_exists($this, 'getRecord')) {
            $formVersion->formInstanceFields()->delete();
            $formVersion->fieldGroupInstances()->delete();
        }

        foreach ($components as $order => $component) {
            if ($component['component_type'] === 'form_field') {
                FormInstanceField::create([
                    'form_version_id' => $formVersion->id,
                    'form_field_id' => $component['form_field_id'],
                    'order' => $order,
                ]);
            } elseif ($component['component_type'] === 'field_group') {
                $fieldGroupInstance = FieldGroupInstance::create([
                    'form_version_id' => $formVersion->id,
                    'field_group_id' => $component['field_group_id'],
                    'order' => $order,
                ]);

                $fieldGroup = $fieldGroupInstance->fieldGroup;

                foreach ($fieldGroup->formFields as $fieldOrder => $formField) {
                    FormInstanceField::create([
                        'form_version_id' => $formVersion->id,
                        'form_field_id' => $formField->id,
                        'field_group_instance_id' => $fieldGroupInstance->id,
                        'order' => $fieldOrder,
                    ]);
                }
            }
        }
    }
}
