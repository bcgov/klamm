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
            Actions\EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Eager load required records
        $this->record->load([
            'formInstanceFields' => function ($query) {
                $query->whereNull('field_group_instance_id')->whereNull('container_id');
                $query->with([
                    'formField' => function ($query) {
                        $query->with([
                            'dataType',
                            'formFieldValue',
                            'formFieldDateFormat',
                        ]);
                    },
                    'selectOptionInstances',
                    'validations',
                    'conditionals',
                    'styleInstances',
                    'formInstanceFieldValue',
                    'formInstanceFieldDateFormat',
                ]);
            },
            'fieldGroupInstances' => function ($query) {
                $query
                    ->whereNull('container_id')
                    ->with([
                        'styleInstances',
                        'fieldGroup',
                        'formInstanceFields' => function ($query) {
                            $query->orderBy('order')
                                ->with([
                                    'formField' => function ($query) {
                                        $query->with([
                                            'dataType',
                                            'formFieldValue',
                                            'formFieldDateFormat',
                                        ]);
                                    },
                                    'selectOptionInstances',
                                    'validations',
                                    'conditionals',
                                    'styleInstances',
                                    'formInstanceFieldValue',
                                    'formInstanceFieldDateFormat',
                                ]);
                        }
                    ]);
            },
            'containers' => function ($query) {
                $query->with([
                    'styleInstances',
                    'formInstanceFields' => function ($query) {
                        $query->orderBy('order')
                            ->with([
                                'formField' => function ($query) {
                                    $query->with([
                                        'dataType',
                                        'formFieldValue',
                                        'formFieldDateFormat',
                                    ]);
                                },
                                'selectOptionInstances',
                                'validations',
                                'conditionals',
                                'styleInstances',
                                'formInstanceFieldValue',
                                'formInstanceFieldDateFormat',
                            ]);
                    },
                    'fieldGroupInstances' => function ($query) {
                        $query->with([
                            'styleInstances',
                            'fieldGroup',
                            'formInstanceFields' => function ($query) {
                                $query->orderBy('order')
                                    ->with([
                                        'formField' => function ($query) {
                                            $query->with([
                                                'dataType',
                                                'formFieldValue',
                                                'formFieldDateFormat',
                                            ]);
                                        },
                                        'selectOptionInstances',
                                        'validations',
                                        'conditionals',
                                        'styleInstances',
                                        'formInstanceFieldValue',
                                        'formInstanceFieldDateFormat',
                                    ]);
                            }
                        ]);
                    }
                ]);
            }
        ]);

        $data = array_merge($this->record->toArray(), $data);

        $components = array_merge(
            $this->fillFields($this->record->formInstanceFields),
            $this->fillGroups($this->record->fieldGroupInstances),
            $this->fillContainers($this->record->containers),
        );

        usort($components, function ($a, $b) {
            return $a['data']['order'] <=> $b['data']['order'];
        });

        foreach ($components as &$component) {
            unset($component['order']);
        }

        $data['components'] = $components;

        return $data;
    }

    // Helper functions
    private function fillStyles($styleInstances)
    {
        $styles = [
            'webStyles' => [],
            'pdfStyles' => [],
        ];
        foreach ($styleInstances as $styleInstance) {
            if ($styleInstance->type === 'web') {
                $styles['webStyles'][] = $styleInstance->style_id;
            } elseif ($styleInstance->type === 'pdf') {
                $styles['pdfStyles'][] = $styleInstance->style_id;
            }
        }
        return $styles;
    }

    private function fillValidations($validations)
    {
        $data = [];
        foreach ($validations as $validation) {
            $data[] = [
                'type' => $validation->type,
                'value' => $validation->value,
                'error_message' => $validation->error_message,
            ];
        }
        return $data;
    }

    private function fillConditionals($conditionals)
    {
        $data = [];
        foreach ($conditionals as $conditional) {
            $data[] = [
                'type' => $conditional->type,
                'value' => $conditional->value,
            ];
        }
        return $data;
    }

    private function fillSelectOptionInstances($selectOptionInstances)
    {
        $data = [];
        foreach ($selectOptionInstances as $instance) {
            $data[] = [
                'type' => 'select_option_instance',
                'data' => [
                    'select_option_id' => $instance->select_option_id,
                    'order' => $instance->order
                ],
            ];
        }
        return $data;
    }

    private function fillFields($formFields)
    {
        $components = [];
        foreach ($formFields as $field) {
            $styles = $this->fillStyles($field->styleInstances);
            $validations = $this->fillValidations($field->validations);
            $conditionals = $this->fillConditionals($field->conditionals);
            $selectOptionInstances = $this->fillSelectOptionInstances($field->selectOptionInstances);

            $formField = $field->formField;
            $components[] = [
                'type' => 'form_field',
                'data' => [
                    'form_field_id' => $field->form_field_id,
                    'label' => $formField?->label,
                    'custom_label' => $field->custom_label,
                    'customize_label' => $field->customize_label,
                    'data_binding_path' => $field->data_binding_path,
                    'custom_data_binding_path' => $field->custom_data_binding_path,
                    'customize_data_binding_path' => $field->custom_data_binding_path,
                    'data_binding' => $field->data_binding,
                    'custom_data_binding' => $field->custom_data_binding,
                    'customize_data_binding' => $field->custom_data_binding,
                    'custom_date_format' => $field->formInstanceFieldDateFormat?->custom_date_format ?? $formField->formFieldDateFormat?->date_format,
                    'customize_date_format' => $field->formInstanceFieldDateFormat?->custom_date_format ?? false,
                    'help_text' => $field->help_text,
                    'custom_help_text' => $field->custom_help_text,
                    'customize_help_text' => $field->custom_help_text,
                    'mask' => $field->mask,
                    'custom_mask' => $field->custom_mask,
                    'customize_mask' => $field->custom_mask,
                    'instance_id' => $field->instance_id,
                    'custom_instance_id' => $field->custom_instance_id,
                    'customize_instance_id' => $field->custom_instance_id,
                    'field_value' => $field->formInstanceFieldValue?->value,
                    'custom_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'customize_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'webStyles' => $styles['webStyles'],
                    'pdfStyles' => $styles['pdfStyles'],
                    'validations' => $validations,
                    'conditionals' => $conditionals,
                    'select_option_instances' => $selectOptionInstances,
                    'order' => $field->order,
                ],
            ];
        }
        return $components ?? [];
    }

    private function fillGroups($fieldGroups)
    {
        $components = [];
        foreach ($fieldGroups as $group) {
            $groupFields = $group->formInstanceFields;
            $formFieldsData = $this->fillFields($groupFields);

            $styles = $this->fillStyles($group->styleInstances);

            $fieldGroup = $group->fieldGroup;
            $components[] = [
                'type' => 'field_group',
                'data' => [
                    'field_group_id' => $group->field_group_id,
                    'custom_group_label' => $group->custom_group_label,
                    'customize_group_label' => $group->customize_group_label,
                    'repeater' => $group->repeater,
                    'custom_repeater_item_label' => $group->custom_repeater_item_label ?? $fieldGroup->repeater_item_label,
                    'customize_repeater_item_label' => $group->custom_repeater_item_label ?? null,
                    'custom_data_binding_path' => $group->custom_data_binding_path ?? $fieldGroup->data_binding_path,
                    'customize_data_binding_path' => $group->custom_data_binding_path ?? null,
                    'custom_data_binding' => $group->custom_data_binding ?? $fieldGroup->data_binding,
                    'customize_data_binding' => $group->custom_data_binding ?? null,
                    'form_fields' => $formFieldsData,
                    'order' => $group->order,
                    'instance_id' => $group->instance_id,
                    'custom_instance_id' => $group->custom_instance_id,
                    'customize_instance_id' => $group->custom_instance_id,
                    'visibility' => $group->visibility,
                    'webStyles' => $styles['webStyles'],
                    'pdfStyles' => $styles['pdfStyles'],
                ],
            ];
        }
        return $components ?? [];
    }

    private function fillContainers($containers)
    {
        $components = [];
        foreach ($containers as $container) {
            $styles = $this->fillStyles($container->styleInstances);

            $blocks = array_merge(
                $this->fillFields($container->formInstanceFields),
                $this->fillGroups($container->fieldGroupInstances),
            );

            usort($blocks, function ($a, $b) {
                return $a['data']['order'] <=> $b['data']['order'];
            });

            $components[] = [
                'type' => 'container',
                'data' => [
                    'instance_id' => $container->instance_id,
                    'custom_instance_id' => $container->custom_instance_id,
                    'customize_instance_id' => $container->custom_instance_id ?? null,
                    'components' => $blocks,
                    'webStyles' => $styles['webStyles'],
                    'pdfStyles' => $styles['pdfStyles'],
                    'visibility' => $container->visibility,
                    'order' => $container->order,
                ],
            ];
        }
        return $components ?? [];
    }
}
