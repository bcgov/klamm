<?php

namespace App\Helpers;

use App\Filament\Forms\Resources\FormVersionResource;
use Illuminate\Support\Str;
use App\Models\FormVersion;
use App\Models\Form;

class FormTemplateHelper
{

    public static function generateJsonTemplate($formVersionId)
    {
        $formVersion = FormVersion::find($formVersionId);
        $form = Form::find($formVersion->form_id);

        $components = [];

        $formFields = $formVersion->formInstanceFields()
            ->whereNull('field_group_instance_id')
            ->whereNull('container_id')
            ->orderBy('order')
            ->with([
                'formField.dataType',
                'formField.formFieldValue',
                'formField.formFieldDateFormat',
                'styleInstances.style',
                'validations',
                'conditionals',
                'formInstanceFieldValue',
                'formInstanceFieldDateFormat',
            ])
            ->get();

        foreach ($formFields as $field) {
            $components[] = [
                'component_type' => 'form_field',
                'data' => $field,
                'order' => $field->order,
            ];
        }

        $fieldGroups = $formVersion->fieldGroupInstances()
            ->whereNull('container_id')
            ->orderBy('order')
            ->with(['styleInstances.style'])
            ->get();

        foreach ($fieldGroups as $group) {
            $components[] = [
                'component_type' => 'field_group',
                'data' => $group,
                'order' => $group->order,
            ];
        }

        $containers = $formVersion->containers()
            ->orderBy('order')
            ->with(['styleInstances.style'])
            ->get();

        foreach ($containers as $container) {
            $components[] = [
                'component_type' => 'container',
                'data' => $container,
                'order' => $container->order,
            ];
        }

        usort($components, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $items = [];
        $index = 1;

        foreach ($components as $component) {
            if ($component['component_type'] === 'form_field') {
                $field = $component['data'];
                $items[] = self::formatField($field, $index);
                $index++;
            } elseif ($component['component_type'] === 'field_group') {
                $group = $component['data'];
                $items[] = self::formatGroup($group, $index);
                $index++;
            } elseif ($component['component_type'] === 'container') {
                $container = $component['data'];
                $items[] = self::formatContainer($container, $index);
                $index++;
            }
        }

        return json_encode([
            "version" => $formVersion->version_number,
            "ministry_id" => $form->ministry_id,
            "id" => (string) Str::uuid(),
            "lastModified" => $formVersion->updated_at->toIso8601String(),
            "title" => $formVersion->form->form_title,
            "form_id" => $form->form_id,
            "deployed_to" => $formVersion->deployed_to,
            "dataSources" => $formVersion->formDataSources->map(function ($dataSource) {
                return [
                    'name' => $dataSource->name,
                    'type' => $dataSource->type,
                    'endpoint' => $dataSource->endpoint,
                    'params' => json_decode($dataSource->params, true),
                    'body' => json_decode($dataSource->body, true),
                    'headers' => json_decode($dataSource->headers, true),
                    'host' => $dataSource->host,
                ];
            })->toArray(),
            "data" => [
                "items" => $items,
            ],
        ], JSON_PRETTY_PRINT);
    }

    protected static function formatField($fieldInstance, $index)
    {
        $field = $fieldInstance->formField;

        $webStyle = [];
        foreach ($fieldInstance->styleInstances as $styleInstance) {
            if ($styleInstance->type === 'web' && $styleInstance->relationLoaded('style') && $styleInstance->style) {
                $webStyle[$styleInstance->style->property] = $styleInstance->style->value;
            }
        }

        $pdfStyle = [];
        foreach ($fieldInstance->styleInstances as $styleInstance) {
            if ($styleInstance->type === 'pdf' && $styleInstance->relationLoaded('style') && $styleInstance->style) {
                $pdfStyle[$styleInstance->style->property] = $styleInstance->style->value;
            }
        }

        $validation = $fieldInstance->validations->map(function ($validation) {
            return [
                'type' => $validation->type,
                'value' => $validation->value,
                'errorMessage' => $validation->error_message,
            ];
        })->toArray();

        $conditional = $fieldInstance->conditionals->map(function ($conditional) {
            return [
                'type' => $conditional->type,
                'value' => $conditional->value,
            ];
        })->toArray();

        $databindings = [
            "source" => $fieldInstance->custom_data_binding ?? $field->data_binding,
            "path" => $fieldInstance->custom_data_binding_path ?? $field->data_binding_path,
        ];

        // Construct $label for $base
        $label = null;
        if ($fieldInstance->customize_label == 'default') {
            $label = $field->label;
        } elseif ($fieldInstance->customize_label == 'customize') {
            $label = $fieldInstance->custom_label;
        } elseif ($fieldInstance->customize_label == 'hide') {
            $label = null;
        }

        $base = [
            "type" => $field->dataType->name,
            "id" => $fieldInstance->custom_instance_id ?? $fieldInstance->instance_id,
            "label" => $label,
            "helperText" => $fieldInstance->custom_help_text ?? $field->help_text,
            "mask" => $fieldInstance->custom_mask ?? $field->mask,
            "codeContext" => [
                "name" => $field->name,
            ],
        ];

        if (!empty($webStyle)) {
            $base["webStyles"] = $webStyle;
        }

        if (!empty($pdfStyle)) {
            $base["pdfStyles"] = $pdfStyle;
        }

        if (!empty($validation) > 0) {
            $base["validation"] = $validation;
        }

        if (!empty($conditional) > 0) {
            $base['conditions'] = $conditional;
        }

        if (!empty($databindings["source"]) && !empty($databindings["path"])) {
            $base['databindings'] = $databindings;
        }

        switch ($field->dataType->name) {
            case "text-input":
                return array_merge($base, [
                    "inputType" => "text",
                ]);
            case "dropdown":
                return array_merge($base, [
                    "placeholder" => "Select your {$label}",
                    "isMulti" => false,
                    "isInline" => false,
                    "selectionFeedback" => "top-after-reopen",
                    "direction" => "bottom",
                    "size" => "md",
                    "listItems" => $fieldInstance->selectOptionInstances()
                        ->with('selectOption')
                        ->get()
                        ->map(function ($selectOptionInstance) {
                            return [
                                "name" => $selectOptionInstance->selectOption->name,
                                "text" => $selectOptionInstance->selectOption->label,
                                "value" => $selectOptionInstance->selectOption->value
                            ];
                        })
                        ->toArray(),
                ]);
            case "text-info":
                return array_merge($base, [
                    "value" => $fieldInstance->formInstanceFieldValue?->custom_value ?? $field->formFieldValue?->value,
                ]);
            case "date":
                return array_merge($base, [
                    "inputFormat" => $fieldInstance->formInstanceFieldDateFormat?->custom_date_format ?? $field->formFieldDateFormat?->date_format,
                ]);
            case "radio":
                return array_merge($base, [
                    "listItems" => $fieldInstance->selectOptionInstances()
                        ->with('selectOption')
                        ->get()
                        ->map(function ($selectOptionInstance) {
                            return [
                                "name" => $selectOptionInstance->selectOption->name,
                                "text" => $selectOptionInstance->selectOption->label,
                                "value" => $selectOptionInstance->selectOption->value
                            ];
                        })
                        ->toArray(),
                ]);
            default:
                return $base;
        }
    }

    protected static function formatGroup($groupInstance, $index)
    {
        $group = $groupInstance->fieldGroup;

        $fieldsInGroup = $groupInstance->formInstanceFields()
            ->orderBy('order')
            ->with(['formField.dataType', 'styleInstances' => function ($query) {
                $query->with('style');
            }, 'validations', 'conditionals'])
            ->get();

        $webStyle = [];
        foreach ($groupInstance->styleInstances as $styleInstance) {
            if ($styleInstance->type === 'web' && $styleInstance->relationLoaded('style') && $styleInstance->style) {
                $webStyle[$styleInstance->style->property] = $styleInstance->style->value;
            }
        }

        $pdfStyle = [];
        foreach ($groupInstance->styleInstances as $styleInstance) {
            if ($styleInstance->type === 'pdf' && $styleInstance->relationLoaded('style') && $styleInstance->style) {
                $pdfStyle[$styleInstance->style->property] = $styleInstance->style->value;
            }
        }

        $visibility = [];
        if ($groupInstance->visibility) {
            $visibility = [
                [
                    'type' => 'visibility',
                    'value' => $groupInstance->visibility,
                ],
            ];
        }

        $fields = $fieldsInGroup->map(function ($fieldInstance, $fieldIndex) {
            return self::formatField($fieldInstance, $fieldIndex + 1);
        })->values()->all();

        $databindings = [
            "source" => $groupInstance->custom_data_binding ?? $group->data_binding,
            "path" => $groupInstance->custom_data_binding_path ?? $group->data_binding_path,
        ];

        // Construct $label for $base
        $label = null;
        if ($groupInstance->customize_group_label == 'default') {
            $label = $group->label;
        } elseif ($groupInstance->customize_group_label == 'customize') {
            $label = $groupInstance->custom_group_label;
        } elseif ($groupInstance->customize_group_label == 'hide') {
            $label = null;
        }

        $base = [
            "type" => "group",
            "label" => $label,
            "id" => $groupInstance->custom_instance_id ?? $groupInstance->instance_id,
            "groupId" => (string) $group->id,
            "repeater" => $groupInstance->repeater,
            "codeContext" => [
                "name" => $group->name,
            ],
        ];

        if (!empty($webStyle)) {
            $base["webStyles"] = $webStyle;
        }

        if (!empty($pdfStyle)) {
            $base["pdfStyles"] = $pdfStyle;
        }

        if ($groupInstance->repeater) {
            $label = $groupInstance->custom_repeater_item_label ?? $groupInstance->fieldGroup->repeater_item_label;
            $base = array_merge($base, ["repeaterItemLabel" => $label]);
        }

        if (!empty($visibility)) {
            $base["conditions"] = $visibility;
        }

        if (!empty($databindings["source"]) && !empty($databindings["path"])) {
            $base['databindings'] = $databindings;
        }

        return array_merge($base, [
            "groupItems" => [
                [
                    "fields" => $fields,
                ],
            ],
        ]);
    }

    protected static function formatContainer($container, $index)
    {
        $fieldsInContainer = $container->formInstanceFields()->orderBy('order')->get();
        $groupsInContainer = $container->fieldGroupInstances()
            ->orderBy('order')
            ->with(['fieldGroup', 'styleInstances'])
            ->get();

        $items = [];
        foreach ($fieldsInContainer as $fieldInstance) {
            $items[] = [
                'type' => 'field',
                'data' => $fieldInstance,
                'order' => $fieldInstance->order,
            ];
        }
        foreach ($groupsInContainer as $groupInstance) {
            $items[] = [
                'type' => 'group',
                'data' => $groupInstance,
                'order' => $groupInstance->order,
            ];
        }

        // Sort items by order before processing
        usort($items, fn($a, $b) => $a['order'] <=> $b['order']);

        // Process sorted items
        $containerItems = [];
        foreach ($items as $item) {
            if ($item['type'] === 'field') {
                $containerItems[] = self::formatField($item['data'], $index);
            } elseif ($item['type'] === 'group') {
                $containerItems[] = self::formatGroup($item['data'], $index);
            }
            $index++;
        }

        $webStyle = [];
        foreach ($container->styleInstances as $styleInstance) {
            if ($styleInstance->type === 'web' && $styleInstance->relationLoaded('style') && $styleInstance->style) {
                $webStyle[$styleInstance->style->property] = $styleInstance->style->value;
            }
        }

        $pdfStyle = [];
        foreach ($container->styleInstances as $styleInstance) {
            if ($styleInstance->type === 'pdf' && $styleInstance->relationLoaded('style') && $styleInstance->style) {
                $pdfStyle[$styleInstance->style->property] = $styleInstance->style->value;
            }
        }

        $visibility = [];
        if ($container->visibility) {
            $visibility = [
                [
                    'type' => 'visibility',
                    'value' => $container->visibility,
                ],
            ];
        }

        $base = [
            "type" => "container",
            "id" => $container->custom_instance_id ?? $container->instance_id,
            "containerId" => (string) $container->id,
            "codeContext" => [
                "name" => 'container',
            ],
        ];

        if (!empty($webStyle)) {
            $base["webStyles"] = $webStyle;
        }

        if (!empty($pdfStyle)) {
            $base["pdfStyles"] = $pdfStyle;
        }

        if (!empty($visibility)) {
            $base["conditions"] = $visibility;
        }

        return array_merge($base, [
            "containerItems" => $containerItems,
        ]);
    }

    public static function calculateElementID(): string
    {
        $counter = FormVersionResource::getElementCounter();
        FormVersionResource::incrementElementCounter();
        return 'element' . $counter;
    }
}
