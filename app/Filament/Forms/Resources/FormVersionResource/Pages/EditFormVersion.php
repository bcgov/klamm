<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Helpers\UniqueIDsHelper;
use App\Models\Container;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Session;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;
use App\Models\FormInstanceFieldValidation;
use App\Models\FormInstanceFieldConditionals;
use App\Models\FormInstanceFieldDateFormat;
use App\Models\FormInstanceFieldValue;
use App\Models\SelectOptionInstance;
use Illuminate\Support\Facades\Cache;
use App\Jobs\GenerateFormTemplateJob;
use App\Helpers\FormTemplateHelper;
use Illuminate\Support\Facades\Log;
use App\Models\StyleSheet;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Put all instance IDs into the session so that each block can check them against its duplicate ID rule
        Session::put('all_instance_ids', UniqueIDsHelper::extractInstanceIds($data['components']));

        unset($data['components']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Regenerate Form Version Cache after cancelling the edit
        FormTemplateHelper::clearFormTemplateCache($this->record->id);
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('Preview Draft Template')
                ->label('Preview Draft')
                ->icon('heroicon-o-rocket-launch')
                ->extraAttributes([
                    'style' => 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;'
                ])
                ->action(function ($livewire) {
                    $formVersionId = $this->record->id;
                    $previewBaseUrl = env('FORM_PREVIEW_URL', '');
                    $previewUrl = rtrim($previewBaseUrl, '/') . '/preview/' . $formVersionId . '?draft=true';
                    $livewire->js("window.open('$previewUrl', '_blank')");
                }),
            Actions\Action::make('refresh_template')
                ->label('Refresh Preview')
                ->requiresConfirmation(false)
                ->action(function () {
                    Cache::tags(['draft'])->flush();
                    $formVersion = $this->record;
                    $components = $this->form->getState()['components'] ?? [];
                    $requestedAt = now()->unix();
                    $uniqueJobId = 'generate-form-template-' . $formVersion->id . '-draft';
                    Cache::forget('laravel_unique_job:' . $uniqueJobId);
                    $cacheKey = "formtemplate:{$formVersion->id}:draft_requested_at";
                    Cache::tags(['form-template'])->put($cacheKey, $requestedAt, now()->addDay());
                    try {
                        $job = new GenerateFormTemplateJob($formVersion->id, $requestedAt, $components, true);
                        $job->handle();
                    } catch (\Exception $e) {
                        Log::error("Job execution failed: " . $e->getMessage(), [
                            'exception' => $e,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                    Log::info("Draft cache refresh process completed for form version {$this->record->id}");
                })
        ];
    }

    protected function afterSave(): void
    {
        $formVersion = $this->record;
        $components = $this->form->getState()['components'] ?? [];

        if (method_exists($this, 'getRecord')) {
            $formVersion->formInstanceFields()->delete();
            $formVersion->fieldGroupInstances()->delete();
            $formVersion->containers()->delete();
        }

        // Build
        foreach ($components as $order => $block) {
            if ($block['type'] === 'form_field') {
                $this->createField($formVersion, $order, $block['data'], fieldGroupInstanceID: null, containerID: null);
            } elseif ($block['type'] === 'field_group') {
                $this->createGroup($formVersion, $order, $block['data'], containerID: null);
            } elseif ($block['type'] === 'container') {
                $this->createContainer($formVersion, $order, $block['data']);
            }
        }

        // Style
        $css_content_web = $this->form->getState()['css_content_web'] ?? '';
        $css_content_pdf = $this->form->getState()['css_content_pdf'] ?? '';
        StyleSheet::createStyleSheet($formVersion, $css_content_web, 'web');
        StyleSheet::createStyleSheet($formVersion, $css_content_pdf, 'pdf');

        // Invalidate all caches explicitly
        FormTemplateHelper::clearFormTemplateCache($formVersion->id);
        $formId = $this->record->id;
        // FormDataHelper::invalidateFormCache($formId);

        $formVersion->touch();
        $requestedAt = now()->unix();
        Cache::tags(['form-template'])->put("formtemplate:{$formVersion->id}:requested_at", $requestedAt, now()->addHours(1));
        $job = new GenerateFormTemplateJob($formVersion->id, $requestedAt);
        $job->handle();

        Log::info("Form version {$formVersion->id} saved and template generation triggered");
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Eager load required records
        $this->record->load([
            'webStyleSheet',
            'pdfStyleSheet',
            'formInstanceFields' => function ($query) {
                $query->whereNull('field_group_instance_id')->whereNull('container_id');
                $query->with([
                    'formField' => function ($query) {
                        $query->with([
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
            unset($component['data']['order']);
        }

        $data['components'] = $components;

        // Load CSS content from file
        $cssContentWeb = $this->record->webStyleSheet?->getCssContent();
        $cssContentPdf = $this->record->pdfStyleSheet?->getCssContent();
        $data['css_content_web'] = $cssContentWeb ?? '';
        $data['css_content_pdf'] = $cssContentPdf ?? '';

        return $data;
    }

    // Helper functions to create records
    private function createFieldValidations($component, $formInstanceField)
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

    private function createFieldConditionals($component, $formInstanceField)
    {
        foreach ($component['conditionals'] ?? [] as $conditionalData) {
            FormInstanceFieldConditionals::create([
                'form_instance_field_id' => $formInstanceField->id,
                'type' => $conditionalData['type'],
                'value' => $conditionalData['value'] ?? null,
            ]);
        }
    }

    private function createFieldValue($component, $formInstanceField)
    {
        if (!empty($component['customize_field_value'])) {
            FormInstanceFieldValue::create([
                'form_instance_field_id' => $formInstanceField->id,
                'custom_value' => $component['custom_field_value'] ?? null,
            ]);
        }
    }

    private function createFieldDateFormat($component, $formInstanceField)
    {
        if (!empty($component['customize_date_format'])) {
            FormInstanceFieldDateFormat::create([
                'form_instance_field_id' => $formInstanceField->id,
                'custom_date_format' => $component['custom_date_format'] ?? null,
            ]);
        }
    }

    private function createSelectOptionInstance($component, $formInstanceField)
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

    private function createField($formVersion, $order, $component, $fieldGroupInstanceID, $containerID)
    {
        $formInstanceField = FormInstanceField::create([
            'form_version_id' => $formVersion->id,
            'form_field_id' => $component['form_field_id'],
            'field_group_instance_id' => $fieldGroupInstanceID,
            'container_id' => $containerID,
            'order' => $order,
            'custom_label' => $component['customize_label'] === 'customize' ? $component['custom_label'] : null,
            'customize_label' => $component['customize_label'] ?? null,
            'custom_data_binding_path' => $component['customize_data_binding_path'] ? $component['custom_data_binding_path'] : null,
            'custom_data_binding' => $component['customize_data_binding'] ? $component['custom_data_binding'] : null,
            'custom_help_text' => $component['customize_help_text'] ? $component['custom_help_text'] : null,
            'custom_mask' => $component['customize_mask'] ? $component['custom_mask'] : null,
            'instance_id' => $component['instance_id'] ?? null,
            'custom_instance_id' => $component['customize_instance_id'] ? $component['custom_instance_id'] : null,
        ]);

        $this->createFieldValidations($component, $formInstanceField);
        $this->createFieldConditionals($component, $formInstanceField);
        $this->createFieldValue($component, $formInstanceField);
        $this->createFieldDateFormat($component, $formInstanceField);
        $this->createSelectOptionInstance($component, $formInstanceField);
    }

    private function createGroup($formVersion, $order, $component, $containerID)
    {
        $fieldGroupInstance = FieldGroupInstance::create([
            'form_version_id' => $formVersion->id,
            'field_group_id' => $component['field_group_id'],
            'container_id' => $containerID,
            'order' => $order,
            'repeater' => $component['repeater'] ?? false,
            'clear_button' => $component['clear_button'] ?? false,
            'custom_group_label' => $component['customize_group_label'] === 'customize' ? $component['custom_group_label'] : null,
            'customize_group_label' => $component['customize_group_label'] ?? null,
            'custom_repeater_item_label' => $component['customize_repeater_item_label'] ? $component['custom_repeater_item_label'] : null,
            'custom_data_binding_path' => $component['customize_data_binding_path'] ? $component['customize_data_binding_path'] : null,
            'custom_data_binding' => $component['customize_data_binding'] ? $component['custom_data_binding'] : null,
            'visibility' => $component['visibility'] ? $component['visibility'] : null,
            'instance_id' => $component['instance_id'] ?? null,
            'custom_instance_id' => $component['customize_instance_id'] ? $component['custom_instance_id'] : null,
        ]);

        $formFields = $component['form_fields'] ?? [];
        foreach ($formFields as $field) {
            $this->createField($formVersion, $order, $field['data'], fieldGroupInstanceID: $fieldGroupInstance->id, containerID: null);
        }
    }

    private function createContainer($formVersion, $order, $component)
    {
        $container = Container::create([
            'form_version_id' => $formVersion->id,
            'order' => $order,
            'instance_id' => $component['instance_id'] ?? null,
            'clear_button' => $component['clear_button'] ?? false,
            'custom_instance_id' => $component['customize_instance_id'] ? $component['custom_instance_id'] : null,
            'visibility' => $component['visibility'] ? $component['visibility'] : null,
        ]);

        $blocks = $component['components'] ?? [];
        foreach ($blocks as $order => $block) {
            if ($block['type'] === 'form_field') {
                $this->createField($formVersion, $order, $block['data'], fieldGroupInstanceID: null, containerID: $container->id);
            } elseif ($block['type'] === 'field_group') {
                $this->createGroup($formVersion, $order, $block['data'], $container->id);
            }
        }
    }

    // Helper functions to fill data
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
                    'label' => $field->label,
                    'custom_label' => $field->custom_label ?? null,
                    'customize_label' => $field->customize_label ?? null,
                    'custom_data_binding_path' => $field->custom_data_binding_path ?? $formField->data_binding_path,
                    'customize_data_binding_path' => $field->custom_data_binding_path ?? null,
                    'custom_data_binding' => $field->custom_data_binding ?? $formField->data_binding,
                    'customize_data_binding' => $field->custom_data_binding ?? null,
                    'custom_date_format' => $field->formInstanceFieldDateFormat?->custom_date_format ?? $formField->formFieldDateFormat?->date_format,
                    'customize_date_format' => $field->formInstanceFieldDateFormat?->custom_date_format ?? false,
                    'custom_help_text' => $field->custom_help_text ?? $formField->help_text,
                    'customize_help_text' => $field->custom_help_text ?? null,
                    'custom_mask' => $field->custom_mask ?? $formField->mask,
                    'customize_mask' => $field->custom_mask ?? null,
                    'instance_id' => $field->instance_id,
                    'custom_instance_id' => $field->custom_instance_id,
                    'customize_instance_id' => $field->custom_instance_id ?? null,
                    'custom_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'customize_field_value' => $field->formInstanceFieldValue?->custom_value ?? null,
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
                    'repeater' => $group->repeater ?? $fieldGroup->repeater,
                    'clear_button' => $group->clear_button ?? $fieldGroup->clear_button,
                    'custom_group_label' => $group->custom_group_label ?? null,
                    'customize_group_label' => $group->customize_group_label ?? null,
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
