<?php

namespace App\Helpers;

use App\Filament\Forms\Resources\FormVersionResource;
use Illuminate\Support\Str;
use App\Models\FormVersion;
use App\Models\Form;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Helpers\DraftCacheHelper;

class FormTemplateHelper
{
    public static function clearFormTemplateCache(int $formVersionId): void
    {
        $formVersion = FormVersion::find($formVersionId);
        if ($formVersion) {
            // Clear both published and draft caches
            Cache::forget("formtemplate:{$formVersionId}:cached_json");
            Cache::forget("formtemplate:{$formVersionId}:draft_cached_json");
            Log::info("Cleared form template cache (both published and draft) for form version {$formVersionId}");
        }
    }

    public static function generateJsonTemplate(int $formVersionId, ?array $updatedComponents = null, bool $isDraft = false): string
    {
        $formVersion = FormVersion::findOrFail($formVersionId);

        $cacheKey = $isDraft
            ? "formtemplate:{$formVersionId}:draft_cached_json"
            : "formtemplate:{$formVersionId}:cached_json";

        $form = $formVersion->form;
        $components = [];

        // If we have updated components from a live edit, process those instead of fetching from database
        if ($updatedComponents !== null) {
            return self::generateTemplateFromComponents($formVersion, $updatedComponents, $cacheKey);
        }

        $formFields = $formVersion->formInstanceFields()
            ->whereNull('field_group_instance_id')
            ->whereNull('container_id')
            ->orderBy('order')
            ->with([
                'formField.dataType',
                'formField.formFieldValue',
                'formField.formFieldDateFormat',
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
            ->with(['fieldGroup'])
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
            ->with([
                'formInstanceFields.formField.dataType',
                'formInstanceFields.formField.formFieldValue',
                'formInstanceFields.validations',
                'formInstanceFields.conditionals',
                'formInstanceFields.formInstanceFieldValue',
                'formInstanceFields.selectOptionInstances.selectOption',
                'fieldGroupInstances' => function ($query) {
                    $query->orderBy('order')->with([
                        'fieldGroup',
                        'formInstanceFields' => function ($query) {
                            $query->orderBy('order')->with([
                                'formField.dataType',
                                'formField.formFieldValue',
                                'formInstanceFieldValue',
                                'validations',
                                'conditionals'
                            ]);
                        }
                    ]);
                }
            ])
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

        $result = json_encode([
            "version" => $formVersion->version_number,
            "ministry_id" => $form->ministry_id,
            "id" => (string) Str::uuid(),
            "lastModified" => $formVersion->updated_at->toIso8601String(),
            "title" => $formVersion->form->form_title,
            "form_id" => $form->form_id,
            "deployed_to" => $formVersion->deployed_to,
            "footer" => $formVersion->footer,
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
            "webStyleSheet" => $formVersion->webStyleSheet?->filename ?? null,
            "pdfStyleSheet" => $formVersion->pdfStyleSheet?->filename ?? null,
            "data" => [
                "items" => $items,
            ],
        ], JSON_PRETTY_PRINT);

        Cache::tags(['form-template'])->put($cacheKey, $result, now()->addDay());

        return $result;
    }

    /**
     * Generate a JSON template based on updated components from a live edit
     *
     * @param FormVersion $formVersion The form version being edited
     * @param array $updatedComponents The components array from the live edit
     * @return string The generated JSON template
     */
    protected static function generateTemplateFromComponents(FormVersion $formVersion, array $updatedComponents, string $cacheKey): string
    {
        $form = $formVersion->form;
        $items = [];
        $index = 1;
        // For live editing, we need to work with the component data directly since
        // the database records may not exist yet or may be outdated
        foreach ($updatedComponents as $component) {
            if (!isset($component['data'])) {
                Log::warning("Invalid component structure, missing data key", ['component' => $component]);
                continue;
            }

            $componentData = $component['data'];
            $componentType = $component['type'] ?? null;

            if ($componentType === 'form_field') {
                // For live editing, create a mock field object from the component data
                $items[] = self::formatLiveField($componentData, $index, $formVersion);
                $index++;
            } elseif ($componentType === 'field_group') {
                // For live editing, create a mock group object from the component data
                $items[] = self::formatLiveGroup($componentData, $index, $formVersion);
                $index++;
            } elseif ($componentType === 'container') {
                // For live editing, create a mock container object from the component data
                $items[] = self::formatLiveContainer($componentData, $index, $formVersion);
                $index++;
            } else {
                Log::warning("Unknown component type: {$componentType}", ['data' => $componentData]);
            }
        }

        $result = json_encode([
            "version" => $formVersion->version_number,
            "ministry_id" => $form->ministry_id,
            "id" => (string) \Illuminate\Support\Str::uuid(),
            "lastModified" => now()->toIso8601String(),
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

        // Update cache with the new result
        Cache::tags(['form-template'])->put($cacheKey, $result, now()->addDay());
        return $result;
    }

    /**
     * Format a field from live component data using database fallback
     */
    protected static function formatLiveField(array $componentData, int $index, FormVersion $formVersion): array
    {
        // Try to get the actual field from database first
        $fieldId = $componentData['form_field_id'] ?? null;
        $instanceId = $componentData['instance_id'] ?? $componentData['custom_instance_id'] ?? null;

        $field = null;
        if ($fieldId) {
            // Try multiple approaches to find the field
            $query = \App\Models\FormInstanceField::where('form_version_id', $formVersion->id)
                ->where('form_field_id', $fieldId);

            if ($instanceId) {
                $field = $query->where(function ($q) use ($instanceId) {
                    $q->where('instance_id', $instanceId)
                        ->orWhere('custom_instance_id', $instanceId);
                })->with([
                    'formField.dataType',
                    'formField.formFieldValue',
                    'styleInstances.style',
                    'validations',
                    'conditionals',
                    'formInstanceFieldValue',
                    'selectOptionInstances.selectOption'
                ])->first();
            }

            // If we still can't find it, try without instance_id (might be a new field)
            if (!$field) {
                $field = $query->with([
                    'formField.dataType',
                    'formField.formFieldValue',
                    'styleInstances.style',
                    'validations',
                    'conditionals',
                    'formInstanceFieldValue',
                    'selectOptionInstances.selectOption'
                ])->first();
            }
        }

        // If we found the database record, use the existing formatter
        if ($field) {
            return self::formatField($field, $index);
        }

        // Get the base form field for reference
        $formField = null;
        if ($fieldId) {
            $formField = \App\Models\FormField::find($fieldId);
        }

        $label = null;
        if (($componentData['customize_label'] ?? 'default') === 'default' && $formField) {
            $label = $formField->label;
        } elseif (($componentData['customize_label'] ?? 'default') === 'customize') {
            $label = $componentData['custom_label'] ?? null;
        }

        $base = [
            "type" => $formField?->dataType?->name ?? 'text-input',
            "id" => $instanceId ?? 'field-' . $index,
            "label" => $label,
            "helperText" => $componentData['custom_help_text'] ?? $formField?->help_text,
            "mask" => $componentData['custom_mask'] ?? $formField?->mask,
            "codeContext" => [
                "name" => $formField?->name ?? 'field-' . $index,
            ],
        ];

        // Add validations if present
        if (!empty($componentData['validations'])) {
            $base["validation"] = array_values(array_map(function ($validation) {
                return [
                    'type' => $validation['type'] ?? 'required',
                    'value' => $validation['value'] ?? null,
                    'errorMessage' => $validation['error_message'] ?? null,
                ];
            }, $componentData['validations']));
        }

        // Add conditionals if present
        if (!empty($componentData['conditionals'])) {
            $base["conditions"] = array_values(array_map(function ($conditional) {
                return [
                    'type' => $conditional['type'] ?? 'visibility',
                    'value' => $conditional['value'] ?? null,
                ];
            }, $componentData['conditionals']));
        }

        // Add data bindings if present
        $dataBinding = $componentData['custom_data_binding'] ?? $formField?->data_binding;
        $dataBindingPath = $componentData['custom_data_binding_path'] ?? $formField?->data_binding_path;
        if ($dataBinding && $dataBindingPath) {
            $base['databindings'] = [
                "source" => $dataBinding,
                "path" => $dataBindingPath,
            ];
        }

        // Add field-specific properties
        $fieldType = $formField?->dataType?->name ?? 'text-input';
        switch ($fieldType) {
            case "text-input":
                $base["inputType"] = "text";
                break;
            case "dropdown":
                $base = array_merge($base, [
                    "placeholder" => "Select your " . ($label ?? 'option'),
                    "isMulti" => false,
                    "isInline" => false,
                    "selectionFeedback" => "top-after-reopen",
                    "direction" => "bottom",
                    "size" => "md",
                    "listItems" => []
                ]);
                break;
            case "text-info":
                $base["value"] = $componentData['custom_field_value'] ?? $formField?->formFieldValue?->value;
                break;
            case "radio":
                $base["listItems"] = [];
                break;
        }

        return $base;
    }

    /**
     * Format a group from live component data using database fallback
     */
    protected static function formatLiveGroup(array $componentData, int $index, FormVersion $formVersion): array
    {
        // Similar approach for groups - try database first, fall back to component data
        $groupId = $componentData['field_group_id'] ?? null;
        $instanceId = $componentData['instance_id'] ?? $componentData['custom_instance_id'] ?? null;

        $group = null;
        if ($groupId && $instanceId) {
            $group = \App\Models\FieldGroupInstance::where('form_version_id', $formVersion->id)
                ->where('field_group_id', $groupId)
                ->where(function ($q) use ($instanceId) {
                    $q->where('instance_id', $instanceId)
                        ->orWhere('custom_instance_id', $instanceId);
                })
                ->with([
                    'fieldGroup',
                    'styleInstances.style',
                    'formInstanceFields' => function ($query) {
                        $query->orderBy('order')->with([
                            'formField.dataType',
                            'formField.formFieldValue',
                            'formInstanceFieldValue',
                            'styleInstances.style',
                            'validations',
                            'conditionals',
                            'selectOptionInstances.selectOption'
                        ]);
                    }
                ])
                ->first();
        }

        if ($group) {
            return self::formatGroup($group, $index);
        }

        // Format from component data
        $fieldGroup = null;
        if ($groupId) {
            $fieldGroup = \App\Models\FieldGroup::find($groupId);
        }

        $label = null;
        if (($componentData['customize_group_label'] ?? 'default') === 'default' && $fieldGroup) {
            $label = $fieldGroup->label;
        } elseif (($componentData['customize_group_label'] ?? 'default') === 'customize') {
            $label = $componentData['custom_group_label'] ?? null;
        }

        $base = [
            "type" => "group",
            "label" => $label,
            "id" => $instanceId ?? 'group-' . $index,
            "groupId" => (string) ($groupId ?? $index),
            "repeater" => $componentData['repeater'] ?? false,
            "codeContext" => [
                "name" => $fieldGroup?->name ?? 'group-' . $index,
            ],
        ];

        // Process nested fields
        $fields = [];
        if (!empty($componentData['form_fields'])) {
            $fieldIndex = 1;
            foreach ($componentData['form_fields'] as $field) {
                $fields[] = self::formatLiveField($field['data'] ?? $field, $fieldIndex, $formVersion);
                $fieldIndex++;
            }
        }

        $base["groupItems"] = [
            [
                "fields" => $fields,
            ],
        ];

        return $base;
    }

    /**
     * Format a container from live component data using database fallback
     */
    protected static function formatLiveContainer(array $componentData, int $index, FormVersion $formVersion): array
    {
        $instanceId = $componentData['instance_id'] ?? $componentData['custom_instance_id'] ?? null;

        $container = null;
        if ($instanceId) {
            $container = \App\Models\Container::where('form_version_id', $formVersion->id)
                ->where(function ($q) use ($instanceId) {
                    $q->where('instance_id', $instanceId)
                        ->orWhere('custom_instance_id', $instanceId);
                })
                ->with([
                    'styleInstances.style',
                    'formInstanceFields.formField.dataType',
                    'formInstanceFields.formField.formFieldValue',
                    'formInstanceFields.styleInstances.style',
                    'formInstanceFields.validations',
                    'formInstanceFields.conditionals',
                    'formInstanceFields.formInstanceFieldValue',
                    'formInstanceFields.selectOptionInstances.selectOption',
                    'fieldGroupInstances' => function ($query) {
                        $query->orderBy('order')->with([
                            'fieldGroup',
                            'styleInstances.style',
                            'formInstanceFields' => function ($query) {
                                $query->orderBy('order')->with([
                                    'formField.dataType',
                                    'formField.formFieldValue',
                                    'formInstanceFieldValue',
                                    'styleInstances.style',
                                    'validations',
                                    'conditionals',
                                    'selectOptionInstances.selectOption'
                                ]);
                            }
                        ]);
                    }
                ])
                ->first();
        }

        if ($container) {
            return self::formatContainer($container, $index);
        }

        // Format from component data
        $base = [
            "type" => "container",
            "id" => $instanceId ?? 'container-' . $index,
            "containerId" => (string) $index,
            "codeContext" => [
                "name" => 'container',
            ],
        ];

        // Process nested components
        $containerItems = [];
        if (!empty($componentData['components'])) {
            $componentIndex = 1;
            foreach ($componentData['components'] as $component) {
                $componentType = $component['type'] ?? null;
                if ($componentType === 'form_field') {
                    $containerItems[] = self::formatLiveField($component['data'] ?? $component, $componentIndex, $formVersion);
                } elseif ($componentType === 'field_group') {
                    $containerItems[] = self::formatLiveGroup($component['data'] ?? $component, $componentIndex, $formVersion);
                }
                $componentIndex++;
            }
        }

        $base["containerItems"] = $containerItems;

        return $base;
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
            ->with([
                'formField.dataType',
                'formField.formFieldValue',
                'formField.formFieldDateFormat',
                'formInstanceFieldValue',
                'formInstanceFieldDateFormat',
                'validations',
                'conditionals'
            ])
            ->get();

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
            "clear_button" => $groupInstance->clear_button,
            "codeContext" => [
                "name" => $group->name,
            ],
        ];

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
        $fieldsInContainer = $container->formInstanceFields()
            ->orderBy('order')
            ->with([
                'formField',
                'validations',
                'conditionals',
            ])
            ->get();
        $groupsInContainer = $container->fieldGroupInstances()
            ->orderBy('order')
            ->with([
                'fieldGroup',
                'formInstanceFields' => function ($query) {
                    $query->orderBy('order')->with([
                        'formField.dataType',
                        'formField.formFieldValue',
                        'formInstanceFieldValue',
                        'validations',
                        'conditionals'
                    ]);
                }
            ])
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
            "clear_button" => $container->clear_button,
            "codeContext" => [
                "name" => 'container',
            ],
        ];

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

    /**
     * Clear all form template caches
     */
    public static function clearAllFormTemplateCaches(): void
    {
        Log::info("Clearing all form template caches");
        Cache::tags(['form-template'])->flush();
        Cache::tags(['draft'])->flush();
        Log::info("All form template caches cleared successfully");
    }
}
