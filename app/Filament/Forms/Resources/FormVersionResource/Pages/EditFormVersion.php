<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;
use App\Models\FormInstanceFieldValidation;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
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
                $formInstanceField = FormInstanceField::create([
                    'form_version_id' => $formVersion->id,
                    'form_field_id' => $component['form_field_id'],
                    'order' => $order,
                    'label' => $component['label'] ?? null,
                    'data_binding' => $component['data_binding'] ?? null,
                    'conditional_logic' => $component['conditional_logic'] ?? null,
                    'styles' => $component['styles'] ?? null,
                ]);

                $validations = $component['validations'] ?? [];
                foreach ($validations as $validationData) {
                    FormInstanceFieldValidation::create([
                        'form_instance_field_id' => $formInstanceField->id,
                        'type' => $validationData['type'],
                        'value' => $validationData['value'] ?? null,
                        'error_message' => $validationData['error_message'] ?? null,
                    ]);
                }
            } elseif ($component['component_type'] === 'field_group') {
                $fieldGroupInstance = FieldGroupInstance::create([
                    'form_version_id' => $formVersion->id,
                    'field_group_id' => $component['field_group_id'],
                    'order' => $order,
                    'label' => $component['group_label'] ?? null,
                    'repeater' => $component['repeater'] ?? false,
                ]);

                $fieldGroup = $fieldGroupInstance->fieldGroup;

                foreach ($fieldGroup->formFields as $fieldOrder => $formField) {
                    FormInstanceField::create([
                        'form_version_id' => $formVersion->id,
                        'form_field_id' => $formField->id,
                        'field_group_instance_id' => $fieldGroupInstance->id,
                        'order' => $fieldOrder,
                        'label' => $formField->label,
                        'data_binding' => $formField->data_binding,
                        'conditional_logic' => $formField->conditional_logic,
                        'styles' => $formField->styles,
                    ]);
                }
            }
        }
    }



    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = array_merge($this->record->toArray(), $data);

        $components = [];

        $formFields = $this->record->formInstanceFields()
            ->whereNull('field_group_instance_id')
            ->get();

        foreach ($formFields as $field) {
            $validations = [];
            foreach ($field->validations as $validation) {
                $validations[] = [
                    'type' => $validation->type,
                    'value' => $validation->value,
                    'error_message' => $validation->error_message,
                ];
            }

            $components[] = [
                'component_type' => 'form_field',
                'form_field_id' => $field->form_field_id,
                'label' => $field->label,
                'data_binding' => $field->data_binding,
                'conditional_logic' => $field->conditional_logic,
                'styles' => $field->styles,
                'validations' => $validations,
                'order' => $field->order,
            ];
        }

        $fieldGroups = $this->record->fieldGroupInstances()->get();

        foreach ($fieldGroups as $group) {
            $components[] = [
                'component_type' => 'field_group',
                'field_group_id' => $group->field_group_id,
                'group_label' => $group->label,
                'repeater' => $group->repeater,
                'order' => $group->order,
            ];
        }

        usort($components, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        foreach ($components as &$component) {
            unset($component['order']);
        }

        $data['components'] = $components;

        return $data;
    }
}
