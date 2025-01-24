<?php

namespace App\Helpers;

use Illuminate\Support\Str;
use App\Models\SelectOptions;
use App\Models\FormVersion;
use App\Models\Form;
use App\Models\FieldGroupInstance;
use App\Models\FormInstanceField;
use App\Models\FormDataSource;

class FormTemplateHelper
{

    public static function generateJsonTemplate($formVersionId)
    {
        $formVersion = FormVersion::find($formVersionId);
        $form = Form::find($formVersion->form_id);

        $components = [];

        $formFields = $formVersion->formInstanceFields()
            ->whereNull('field_group_instance_id')
            ->orderBy('order')
            ->get();

        foreach ($formFields as $field) {
            $components[] = [
                'component_type' => 'form_field',
                'data' => $field,
                'order' => $field->order,
            ];
        }

        $fieldGroups = $formVersion->fieldGroupInstances()->orderBy('order')->get();

        foreach ($fieldGroups as $group) {
            $components[] = [
                'component_type' => 'field_group',
                'data' => $group,
                'order' => $group->order,
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
            "source" => $fieldInstance->custom_data_binding_path ?? $field->data_binding_path,
            "path" => $fieldInstance->custom_data_binding ?? $field->data_binding,
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
            "helpText" => $fieldInstance->custom_help_text ?? $field->help_text,
            "styles" => $fieldInstance->custom_styles ?? $field->styles,
            "mask" => $fieldInstance->custom_mask ?? $field->mask,
            "codeContext" => [
                "name" => $field->name,
            ],
        ];

        if (sizeof($validation) > 0) {
            $base = array_merge($base, ["validation" => $validation]);
        }

        if (sizeof($conditional) > 0) {
            $base = array_merge($base, ["conditions" => $conditional]);
        }

        if (!is_null($databindings["source"]) && !is_null($databindings["path"])) {
            $base = array_merge($base, ["databindings" => $databindings]);
        }

        switch ($field->dataType->name) {
            case "text-input":
                return array_merge($base, [
                    "placeholder" => "Enter your {$fieldInstance->label}",
                    "helperText" => "{$fieldInstance->label} as it appears on official documents",
                    "inputType" => "text",
                ]);
            case "dropdown":
                return array_merge($base, [
                    "placeholder" => "Select your {$fieldInstance->label}",
                    "isMulti" => false,
                    "isInline" => false,
                    "selectionFeedback" => "top-after-reopen",
                    "direction" => "bottom",
                    "size" => "md",
                    "helperText" => "Choose one from the list",
                    "listItems" => $field->selectOptions()
                        ->get()
                        ->map(function ($selectOption) {
                            return ["text" => $selectOption->label];
                        })
                        ->toArray(),
                ]);
            case "text-info":
                return array_merge($base, [
                    "value" => $fieldInstance->formInstanceFieldValue?->custom_value ?? $field->formFieldValue?->value,
                    "helperText" => "{$fieldInstance->label} as it appears on official documents",
                ]);
            case "radio":
                return array_merge($base, [
                    "helperText" => "Choose one option",
                    "listItems" => $field->selectOptions()
                        ->get()
                        ->map(function ($selectOption) {
                            return ["text" => $selectOption->label];
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

        $fieldsInGroup = $groupInstance->formInstanceFields()->orderBy('order')->get();

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
            "source" => $groupInstance->custom_data_binding_path ?? $group->data_binding_path,
            "path" => $groupInstance->custom_data_binding ?? $group->data_binding,
        ];

        $base = [
            "type" => "group",
            "label" => $groupInstance->label ?? $group->label,
            "id" => $groupInstance->instance_id,
            "groupId" => (string) $group->id,
            "repeater" => $groupInstance->repeater,
            "codeContext" => [
                "name" => $group->name,
            ],
        ];

        if (sizeof($visibility) > 0) {
            $base = array_merge($base, ["conditions" => $visibility]);
        }

        if (!is_null($databindings["source"]) && !is_null($databindings["path"])) {
            $base = array_merge($base, ["databindings" => $databindings]);
        }

        return array_merge($base, [
            "groupItems" => [
                [
                    "fields" => $fields,
                ],
            ],
        ]);
    }

    public static function calculateFieldID($state)
    {
        $numOfComponents = count($state['components']);
        return 'field' . $numOfComponents;
    }

    public static function calculateFieldInGroupID($state)
    {

        $numOfFormFields = count($state['form_fields']);
        return 'nestedField' . $numOfFormFields;
    }
}
