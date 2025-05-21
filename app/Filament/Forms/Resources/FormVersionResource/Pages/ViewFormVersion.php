<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormVersion extends ViewRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make('view')
                ->url(fn($record) => route('filament.forms.resources.forms.view', ['record' => $record->form_id]))
                ->icon('heroicon-o-eye')
                ->label('View Form Metadata'),
            Actions\EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load([
            'formInstanceFields' => function ($query) {
                $query->whereNull('field_group_instance_id')->whereNull('container_id')
                    ->select('id', 'form_version_id', 'form_field_id', 'order', 'instance_id', 'custom_instance_id', 'custom_label', 'customize_label')
                    ->with(['formField:id,label,data_type_id', 'formField.dataType:id,name']);
            },
            'fieldGroupInstances' => function ($query) {
                $query->whereNull('container_id')
                    ->select(
                        'id',
                        'form_version_id',
                        'field_group_id',
                        'order',
                        'instance_id',
                        'custom_instance_id',
                        'custom_group_label',
                        'customize_group_label',
                        'repeater',
                        'clear_button',
                        'custom_repeater_item_label',
                        'visibility',
                        'custom_data_binding',
                        'custom_data_binding_path'
                    )
                    ->with(['fieldGroup:id,label']);
            },
            'containers' => function ($query) {
                $query->select('id', 'form_version_id', 'order', 'instance_id', 'custom_instance_id');
            }
        ]);

        $data = array_merge($this->record->toArray(), $data);

        $components = array_merge(
            $this->fillSimplifiedFields($this->record->formInstanceFields),
            $this->fillSimplifiedGroups($this->record->fieldGroupInstances),
            $this->fillSimplifiedContainers($this->record->containers),
        );

        usort($components, function ($a, $b) {
            return $a['data']['order'] <=> $b['data']['order'];
        });

        foreach ($components as &$component) {
            unset($component['data']['order']);
        }

        $data['components'] = $components;

        return $data;
    }

    private function fillSimplifiedFields($formFields): array
    {
        $components = [];
        foreach ($formFields as $field) {
            $components[] = [
                'type' => 'form_field',
                'data' => [
                    'id' => $field->id,
                    'form_field_id' => $field->form_field_id,
                    'instance_id' => $field->instance_id,
                    'custom_instance_id' => $field->custom_instance_id,
                    'customize_instance_id' => !empty($field->custom_instance_id),
                    'custom_label' => $field->custom_label,
                    'customize_label' => $field->customize_label,
                    'order' => $field->order,
                    'formField' => $field->formField ? [
                        'id' => $field->formField->id,
                        'label' => $field->formField->label,
                        'data_type' => $field->formField->dataType ? [
                            'id' => $field->formField->dataType->id,
                            'name' => $field->formField->dataType->name
                        ] : null
                    ] : null
                ],
            ];
        }
        return $components;
    }

    private function fillSimplifiedGroups($fieldGroups): array
    {
        $components = [];
        foreach ($fieldGroups as $group) {
            $components[] = [
                'type' => 'field_group',
                'data' => [
                    'id' => $group->id,
                    'field_group_id' => $group->field_group_id,
                    'instance_id' => $group->instance_id,
                    'custom_instance_id' => $group->custom_instance_id,
                    'customize_instance_id' => !empty($group->custom_instance_id),
                    'custom_group_label' => $group->custom_group_label,
                    'customize_group_label' => $group->customize_group_label,
                    'repeater' => $group->repeater,
                    'clear_button' => $group->clear_button,
                    'custom_repeater_item_label' => $group->custom_repeater_item_label,
                    'visibility' => $group->visibility,
                    'custom_data_binding' => $group->custom_data_binding,
                    'custom_data_binding_path' => $group->custom_data_binding_path,
                    'form_fields' => [],
                    'order' => $group->order,
                    'fieldGroup' => $group->fieldGroup ? [
                        'id' => $group->fieldGroup->id,
                        'label' => $group->fieldGroup->label
                    ] : null
                ],
            ];
        }
        return $components;
    }

    private function fillSimplifiedContainers($containers): array
    {
        $components = [];
        foreach ($containers as $container) {
            $components[] = [
                'type' => 'container',
                'data' => [
                    'id' => $container->id,
                    'instance_id' => $container->instance_id,
                    'custom_instance_id' => $container->custom_instance_id,
                    'customize_instance_id' => !empty($container->custom_instance_id),
                    'components' => [],
                    'order' => $container->order,
                ],
            ];
        }
        return $components;
    }
}
