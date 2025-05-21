<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Helpers\UniqueIDsHelper;
use App\Models\Container;
use App\Models\FieldGroupInstance;
use App\Models\FormInstanceField;
use App\Models\FormInstanceFieldConditionals;
use App\Models\FormInstanceFieldDateFormat;
use App\Models\FormInstanceFieldValidation;
use App\Models\FormInstanceFieldValue;
use App\Models\SelectOptionInstance;
use App\Models\StyleInstance;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();
        $data['updater_name'] = $user->name;
        $data['updater_email'] = $user->email;

        Session::put('all_instance_ids', UniqueIDsHelper::extractInstanceIds($data['components'] ?? []));

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

        if (!is_array($components)) {
            $components = [];
        }

        $formVersion->load([
            'formInstanceFields' => function ($query) {
                $query->whereNull('field_group_instance_id')->whereNull('container_id');
            },
            'fieldGroupInstances' => function ($query) {
                $query->whereNull('container_id');
            },
            'containers'
        ]);

        foreach ($components as $order => $block) {
            if (!is_array($block)) {
                continue;
            }

            if ($block['type'] === 'form_field') {
                $fieldId = $block['data']['form_field_id'] ?? null;
                $instanceId = $block['data']['instance_id'] ?? null;

                if ($fieldId) {
                    $field = $formVersion->formInstanceFields
                        ->where('form_field_id', $fieldId)
                        ->where('instance_id', $instanceId)
                        ->whereNull('field_group_instance_id')
                        ->whereNull('container_id')
                        ->first();

                    if ($field) {
                        $field->order = $order;
                        $field->save();
                    } else {
                        $this->createField($formVersion, $order, $block['data'], null, null);
                    }
                }
            } elseif ($block['type'] === 'field_group') {
                $groupId = $block['data']['field_group_id'] ?? null;
                $instanceId = $block['data']['instance_id'] ?? null;

                if ($groupId) {
                    $group = $formVersion->fieldGroupInstances
                        ->where('field_group_id', $groupId)
                        ->where('instance_id', $instanceId)
                        ->whereNull('container_id')
                        ->first();

                    if ($group) {
                        $group->order = $order;
                        $group->save();
                    } else {
                        $this->createGroup($formVersion, $order, $block['data'], null);
                    }
                }
            } elseif ($block['type'] === 'container') {
                $instanceId = $block['data']['instance_id'] ?? null;

                $container = $formVersion->containers
                    ->where('instance_id', $instanceId)
                    ->first();

                if ($container) {
                    $container->order = $order;
                    $container->save();
                } else {
                    $this->createContainer($formVersion, $order, $block['data']);
                }
            }
        }
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
                    ->select('id', 'form_version_id', 'field_group_id', 'order', 'instance_id', 'custom_instance_id', 'custom_group_label', 'customize_group_label')
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
                    'field_group_id' => $group->field_group_id,
                    'instance_id' => $group->instance_id,
                    'custom_instance_id' => $group->custom_instance_id,
                    'customize_instance_id' => !empty($group->custom_instance_id),
                    'custom_group_label' => $group->custom_group_label,
                    'customize_group_label' => $group->customize_group_label,
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

    private function createStyles(array $component, int $id, string $instanceType): void
    {
        foreach ($component['webStyles'] ?? [] as $styleId) {
            StyleInstance::create([
                'style_id' => $styleId,
                'type' => 'web',
                $instanceType => $id,
            ]);
        }

        foreach ($component['pdfStyles'] ?? [] as $styleId) {
            StyleInstance::create([
                'style_id' => $styleId,
                'type' => 'pdf',
                $instanceType => $id,
            ]);
        }
    }

    private function createFieldValidations(array $component, FormInstanceField $formInstanceField): void
    {
        foreach ($component['validations'] ?? [] as $validationData) {
            FormInstanceFieldValidation::create([
                'form_instance_field_id' => $formInstanceField->id,
                'type' => $validationData['type'],
                'value' => $validationData['value'] ?? null,
                'error_message' => $validationData['error_message'] ?? null,
            ]);
        }
    }

    private function createFieldConditionals(array $component, FormInstanceField $formInstanceField): void
    {
        foreach ($component['conditionals'] ?? [] as $conditionalData) {
            FormInstanceFieldConditionals::create([
                'form_instance_field_id' => $formInstanceField->id,
                'type' => $conditionalData['type'],
                'value' => $conditionalData['value'] ?? null,
            ]);
        }
    }

    private function createFieldValue(array $component, FormInstanceField $formInstanceField): void
    {
        if (!empty($component['customize_field_value'])) {
            FormInstanceFieldValue::create([
                'form_instance_field_id' => $formInstanceField->id,
                'custom_value' => $component['custom_field_value'] ?? null,
            ]);
        }
    }

    private function createFieldDateFormat(array $component, FormInstanceField $formInstanceField): void
    {
        if (!empty($component['customize_date_format'])) {
            FormInstanceFieldDateFormat::create([
                'form_instance_field_id' => $formInstanceField->id,
                'custom_date_format' => $component['custom_date_format'] ?? null,
            ]);
        }
    }

    private function createSelectOptionInstance(array $component, FormInstanceField $formInstanceField): void
    {
        if (!empty($component['select_option_instances'])) {
            foreach ($component['select_option_instances'] as $index => $instance) {
                SelectOptionInstance::create([
                    'form_instance_field_id' => $formInstanceField->id,
                    'select_option_id' => $instance['data']['select_option_id'] ?? null,
                    'order' => $index + 1,
                ]);
            }
        }
    }

    private function createField($formVersion, int $order, array $component, ?int $fieldGroupInstanceID, ?int $containerID): FormInstanceField
    {
        $formInstanceField = FormInstanceField::create([
            'form_version_id' => $formVersion->id,
            'form_field_id' => $component['form_field_id'],
            'field_group_instance_id' => $fieldGroupInstanceID,
            'container_id' => $containerID,
            'order' => $order,
            'custom_label' => ($component['customize_label'] ?? '') === 'customize' ? ($component['custom_label'] ?? null) : null,
            'customize_label' => $component['customize_label'] ?? null,
            'custom_data_binding_path' => (!empty($component['customize_data_binding_path'])) ? ($component['custom_data_binding_path'] ?? null) : null,
            'custom_data_binding' => (!empty($component['customize_data_binding'])) ? ($component['custom_data_binding'] ?? null) : null,
            'custom_help_text' => (!empty($component['customize_help_text'])) ? ($component['custom_help_text'] ?? null) : null,
            'custom_mask' => (!empty($component['customize_mask'])) ? ($component['custom_mask'] ?? null) : null,
            'instance_id' => $component['instance_id'] ?? null,
            'custom_instance_id' => (!empty($component['customize_instance_id'])) ? ($component['custom_instance_id'] ?? null) : null,
        ]);

        if (isset($component['webStyles']) || isset($component['pdfStyles'])) {
            $this->createStyles($component, $formInstanceField->id, 'form_instance_field_id');
        }

        if (isset($component['validations'])) {
            $this->createFieldValidations($component, $formInstanceField);
        }

        if (isset($component['conditionals'])) {
            $this->createFieldConditionals($component, $formInstanceField);
        }

        if (isset($component['customize_field_value']) && $component['customize_field_value']) {
            $this->createFieldValue($component, $formInstanceField);
        }

        if (isset($component['customize_date_format']) && $component['customize_date_format']) {
            $this->createFieldDateFormat($component, $formInstanceField);
        }

        if (isset($component['select_option_instances']) && !empty($component['select_option_instances'])) {
            $this->createSelectOptionInstance($component, $formInstanceField);
        }

        return $formInstanceField;
    }

    private function createGroup($formVersion, int $order, array $component, ?int $containerID): FieldGroupInstance
    {
        $fieldGroupInstance = FieldGroupInstance::create([
            'form_version_id' => $formVersion->id,
            'field_group_id' => $component['field_group_id'],
            'container_id' => $containerID,
            'order' => $order,
            'repeater' => $component['repeater'] ?? false,
            'clear_button' => $component['clear_button'] ?? false,
            'custom_group_label' => ($component['customize_group_label'] ?? '') === 'customize' ? ($component['custom_group_label'] ?? null) : null,
            'customize_group_label' => $component['customize_group_label'] ?? null,
            'custom_repeater_item_label' => (!empty($component['customize_repeater_item_label'])) ? ($component['custom_repeater_item_label'] ?? null) : null,
            'custom_data_binding_path' => (!empty($component['customize_data_binding_path'])) ? ($component['custom_data_binding_path'] ?? null) : null,
            'custom_data_binding' => (!empty($component['customize_data_binding'])) ? ($component['custom_data_binding'] ?? null) : null,
            'visibility' => $component['visibility'] ?? null,
            'instance_id' => $component['instance_id'] ?? null,
            'custom_instance_id' => (!empty($component['customize_instance_id'])) ? ($component['custom_instance_id'] ?? null) : null,
        ]);

        if (isset($component['webStyles']) || isset($component['pdfStyles'])) {
            $this->createStyles($component, $fieldGroupInstance->id, 'field_group_instance_id');
        }

        if (isset($component['form_fields']) && is_array($component['form_fields'])) {
            foreach ($component['form_fields'] as $fieldOrder => $field) {
                $this->createField($formVersion, $fieldOrder, $field['data'], $fieldGroupInstance->id, null);
            }
        }

        return $fieldGroupInstance;
    }

    private function createContainer($formVersion, int $order, array $component): Container
    {
        $container = Container::create([
            'form_version_id' => $formVersion->id,
            'order' => $order,
            'instance_id' => $component['instance_id'] ?? null,
            'clear_button' => $component['clear_button'] ?? false,
            'custom_instance_id' => (!empty($component['customize_instance_id'])) ? $component['custom_instance_id'] : null,
            'visibility' => (!empty($component['visibility'])) ? $component['visibility'] : null,
        ]);

        $this->createStyles($component, $container->id, 'container_id');

        $blocks = $component['components'] ?? [];
        foreach ($blocks as $blockOrder => $block) {
            if (!is_array($block)) {
                continue;
            }

            if ($block['type'] === 'form_field') {
                $this->createField($formVersion, $blockOrder, $block['data'], null, $container->id);
            } elseif ($block['type'] === 'field_group') {
                $this->createGroup($formVersion, $blockOrder, $block['data'], $container->id);
            }
        }

        return $container;
    }
}
