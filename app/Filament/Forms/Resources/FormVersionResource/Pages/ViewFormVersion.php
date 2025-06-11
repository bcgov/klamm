<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Filament\Forms\Resources\FormVersionResource\Actions\FormApprovalActions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewFormVersion extends ViewRecord
{
    protected static string $resource = FormVersionResource::class;

    public array $additionalApprovers = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make('view')
                ->url(fn($record) => route('filament.forms.resources.forms.view', ['record' => $record->form_id]))
                ->label('Form Metadata')
                ->button()
                ->link()
                ->extraAttributes(['class' => 'underline']),
            Actions\EditAction::make()
                ->outlined()
                ->visible(fn() => $this->record->status === 'draft'),
            FormApprovalActions::makeReadyForReviewAction($this->record, $this->additionalApprovers),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Eager load required records
        $this->record->load([
            'styleSheets',
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
                    'formInstanceFieldValue',
                    'formInstanceFieldDateFormat',
                ]);
            },
            'fieldGroupInstances' => function ($query) {
                $query
                    ->whereNull('container_id')
                    ->with([
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
                                    'formInstanceFieldValue',
                                    'formInstanceFieldDateFormat',
                                ]);
                        }
                    ]);
            },
            'containers' => function ($query) {
                $query->with([
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
                                'formInstanceFieldValue',
                                'formInstanceFieldDateFormat',
                            ]);
                    },
                    'fieldGroupInstances' => function ($query) {
                        $query->with([
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
        $data['style_sheets'] = $this->record?->styleSheets
            ->map(function ($styleSheet) {
                return [
                    'id' => $styleSheet->id,
                    'type' => $styleSheet->pivot->type ?? null,
                ];
            })
            ->toArray();

        return $data;
    }

    // Helper functions
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
                    'custom_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'customize_field_value' => $field->formInstanceFieldValue?->custom_value,
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

            $fieldGroup = $group->fieldGroup;
            $components[] = [
                'type' => 'field_group',
                'data' => [
                    'field_group_id' => $group->field_group_id,
                    'custom_group_label' => $group->custom_group_label,
                    'customize_group_label' => $group->customize_group_label,
                    'repeater' => $group->repeater ?? false,
                    'clear_button' => $group->clear_button ?? false,
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
                ],
            ];
        }
        return $components ?? [];
    }

    private function fillContainers($containers)
    {
        $components = [];
        foreach ($containers as $container) {

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
                    'clear_button' => $container->clear_button ?? false,
                    'components' => $blocks,
                    'visibility' => $container->visibility,
                    'order' => $container->order,
                ],
            ];
        }
        return $components ?? [];
    }
}
