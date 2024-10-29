<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;

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


    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = array_merge($this->record->toArray(), $data);

        $components = [];

        $formFields = $this->record->formInstanceFields()
            ->whereNull('field_group_instance_id')
            ->get();

        $fieldGroups = $this->record->fieldGroupInstances()->get();

        $items = collect();

        foreach ($formFields as $field) {
            $items->push([
                'component_type' => 'form_field',
                'form_field_id' => $field->form_field_id,
                'order' => $field->order,
            ]);
        }

        foreach ($fieldGroups as $group) {
            $items->push([
                'component_type' => 'field_group',
                'field_group_id' => $group->field_group_id,
                'order' => $group->order,
            ]);
        }

        $items = $items->sortBy('order');

        foreach ($items as $item) {
            if ($item['component_type'] === 'form_field') {
                $components[] = [
                    'component_type' => 'form_field',
                    'form_field_id' => $item['form_field_id'],
                ];
            } elseif ($item['component_type'] === 'field_group') {
                $components[] = [
                    'component_type' => 'field_group',
                    'field_group_id' => $item['field_group_id'],
                ];
            }
        }

        $data['components'] = $components;

        return $data;
    }
}
