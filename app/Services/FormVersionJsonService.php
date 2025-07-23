<?php

namespace App\Services;

use App\Models\FormBuilding\FormVersion;
use App\Models\FormBuilding\FormElement;

class FormVersionJsonService
{
    public function generateJson(FormVersion $formVersion): array
    {
        // Load the form version with necessary relationships
        $formVersion->load([
            'form',
            'formElements.elementable',
            'formElements.dataBindings.formDataSource',
            'formDataSources' => function ($query) {
                $query->orderBy('form_versions_form_data_sources.order');
            },
            'webStyleSheet',
            'pdfStyleSheet',
            'webFormScript',
            'pdfFormScript'
        ]);

        $formVersionData = [
            'uuid' => $formVersion->uuid,
            'name' => $formVersion->form->form_title ?? 'Unknown Form',
            'id' => $formVersion->form->form_id ?? '',
            'version' => $formVersion->version_number,
            'status' => $formVersion->status,
            'data' => $this->getFormVersionData($formVersion),
            'dataSources' => $this->getDataSources($formVersion),
            'styles' => $this->getStyles($formVersion),
            'scripts' => $this->getScripts($formVersion),
            'elements' => $this->getElements($formVersion)
        ];

        // Only add pdfTemplate if uses_pets_template is true
        if ($formVersion->uses_pets_template) {
            $formVersionData['pdfTemplate'] = $this->getPdfTemplate($formVersion);
        }

        return [
            'formversion' => $formVersionData
        ];
    }

    public function generatePreMigrationJson(FormVersion $formVersion): array
    {
        // Load the form version with necessary relationships
        $formVersion->load([
            'form',
            'formElements.elementable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\FormBuilding\SelectInputFormElement::class => ['options'],
                    \App\Models\FormBuilding\RadioInputFormElement::class => ['options'],
                ]);
            },
            'formElements.dataBindings.formDataSource',
            'formDataSources' => function ($query) {
                $query->orderBy('form_versions_form_data_sources.order');
            },
            'webStyleSheet',
            'pdfStyleSheet',
            'webFormScript',
            'pdfFormScript'
        ]);

        $preMigrationData = [
            'version' => $formVersion->version_number,
            'id' => $formVersion->uuid,
            'lastModified' => $formVersion->updated_at?->format('c') ?? now()->format('c'),
            'title' => $formVersion->form->form_title ?? 'Unknown Form',
            'form_id' => $formVersion->form->form_id ?? '',
            'deployed_to' => '',
            'ministry_id' => $formVersion->form->ministry_id ?? null,
            'dataSources' => $this->getDataSources($formVersion),
            'data' => [
                'styles' => $this->getStyles($formVersion),
                'scripts' => $this->getScripts($formVersion),
                'items' => $this->transformElementsToPreMigrationFormat($formVersion)
            ]
        ];

        // Only add pdfTemplate if uses_pets_template is true
        if ($formVersion->uses_pets_template) {
            $preMigrationData['pdfTemplate'] = $this->getPdfTemplate($formVersion);
        }

        return $preMigrationData;
    }

    protected function getFormVersionData(FormVersion $formVersion): array
    {
        return [
            'comments' => $formVersion->comments,
            'created_at' => $formVersion->created_at?->toISOString(),
            'updated_at' => $formVersion->updated_at?->toISOString(),
        ];
    }

    protected function getStyles(FormVersion $formVersion): array
    {
        $styles = [];

        if ($formVersion->webStyleSheet) {
            $styles[] = [
                'type' => 'web',
                'content' => $formVersion->webStyleSheet->getCssContent()
            ];
        }

        if ($formVersion->pdfStyleSheet) {
            $styles[] = [
                'type' => 'pdf',
                'content' => $formVersion->pdfStyleSheet->getCssContent()
            ];
        }

        return $styles;
    }

    protected function getScripts(FormVersion $formVersion): array
    {
        $scripts = [];

        if ($formVersion->webFormScript) {
            $scripts[] = [
                'type' => 'web',
                'content' => $formVersion->webFormScript->getJsContent()
            ];
        }

        if ($formVersion->pdfFormScript) {
            $scripts[] = [
                'type' => 'pdf',
                'content' => $formVersion->pdfFormScript->getJsContent()
            ];
        }

        return $scripts;
    }

    protected function getPdfTemplate(FormVersion $formVersion): array
    {
        return [
            'name' => $formVersion->pdf_template_name,
            'version' => $formVersion->pdf_template_version,
            'parameters' => $formVersion->pdf_template_parameters,
        ];
    }

    protected function getElements(FormVersion $formVersion): array
    {
        // Get root elements (elements without a parent - parent_id is -1 for root elements)
        $rootElements = $formVersion->formElements()
            ->where(function ($query) {
                $query->whereNull('parent_id')
                    ->orWhere('parent_id', -1);
            })
            ->orderBy('order')
            ->get();

        return $rootElements->map(function (FormElement $element) {
            return $this->transformElement($element);
        })->toArray();
    }

    protected function transformElement(FormElement $element): array
    {
        // Create the full reference ID (reference_id + uuid)
        $fullReferenceId = $element->getFullReferenceId();

        $elementData = [
            'uuid' => $fullReferenceId,
            'type' => $this->getElementType($element),
            'name' => $element->name,
            'description' => $element->description,
            'help_text' => $element->help_text,
            'is_required' => $element->is_required,
            'visible_web' => $element->visible_web,
            'visible_pdf' => $element->visible_pdf,
            'is_read_only' => $element->is_read_only,
            'save_on_submit' => $element->save_on_submit,
            'order' => $element->order,
            'options' => $element->elementable?->options ?? [],
            'parent_id' => $element->parent_id == -1 ? null : $element->parent_id,
            'attributes' => $this->getElementAttributes($element)
        ];

        // Add data bindings if they exist
        $dataBindings = $this->getDataBindings($element);
        if (!empty($dataBindings)) {
            $elementData['dataBindings'] = $dataBindings;
        }

        // Load and add children if this element has any
        $children = FormElement::where('parent_id', $element->id)
            ->orderBy('order')
            ->with(['elementable', 'dataBindings.formDataSource'])
            ->get();

        if ($children->count() > 0) {
            $elementData['children'] = $children->map(function (FormElement $child) {
                return $this->transformElement($child);
            })->toArray();
        }

        return $elementData;
    }

    protected function getElementType(FormElement $element): string
    {
        if (!$element->elementable_type) {
            return 'unknown';
        }

        // Convert class name to kebab-case type
        $className = class_basename($element->elementable_type);

        // Remove "FormElement" suffix if present
        $typeName = str_replace('FormElement', '', $className);

        // Convert to kebab-case
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $typeName));
    }

    protected function getElementAttributes(FormElement $element): array
    {
        if (!$element->elementable) {
            return [];
        }

        $attributes = $element->elementable->toArray();

        // Remove Laravel model metadata
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);

        return $attributes;
    }

    protected function transformElementsToPreMigrationFormat(FormVersion $formVersion): array
    {
        // Get root elements (elements without a parent - parent_id is -1 for root elements)
        $rootElements = $formVersion->formElements()
            ->where(function ($query) {
                $query->whereNull('parent_id')
                    ->orWhere('parent_id', -1);
            })
            ->with(['elementable', 'dataBindings.formDataSource'])
            ->orderBy('order')
            ->get();

        return $rootElements->map(function (FormElement $element) {
            return $this->transformElementToPreMigrationFormat($element);
        })->toArray();
    }

    protected function transformElementToPreMigrationFormat(FormElement $element): array
    {
        $elementType = $this->getElementType($element);

        // Create the full reference ID (reference_id + uuid)
        $fullReferenceId = $element->getFullReferenceId();

        $elementData = [
            'type' => $this->mapElementTypeToPreMigration($elementType),
            'id' => $fullReferenceId,
        ];

        // Handle repeatable containers as groups FIRST, before other container logic
        if ($elementType === 'container' && $element->elementable?->is_repeatable) {
            return $this->transformRepeatableContainerAsGroup($element, $elementData);
        }
        // Handle non-repeatable containers
        elseif ($elementType === 'container' && !$element->elementable?->is_repeatable) {
            return $this->transformContainerElement($element, $elementData);
        }
        // Handle explicit group elements
        elseif ($this->isGroupElement($elementType)) {
            return $this->transformGroupElement($element, $elementData);
        }
        // Handle all other standard elements
        else {
            return $this->transformStandardElement($element, $elementData, $elementType);
        }
    }

    protected function transformContainerElement(FormElement $element, array $elementData): array
    {
        // Check if this container is repeatable - if so, transform it as a group
        if ($element->elementable?->is_repeatable) {
            return $this->transformRepeatableContainerAsGroup($element, $elementData);
        }

        $elementData['containerId'] = (string)($element->id ?? '');
        $elementData['clear_button'] = false;
        $elementData['codeContext'] = [
            'name' => $this->generateCodeContextName($element->name ?? 'container')
        ];
        $elementData['attributes'] = $this->remapAttributes($this->getElementAttributes($element));
        $elementData['label'] = $elementData['attributes']['legend'] ?? null;

        $elementData['repeater'] = false;
        $elementData['repeaterLabel'] = null;

        $elementData['pdfStyles'] = [
            'display' => $element->visible_pdf ? null : 'none',
        ];
        $elementData['webStyles'] = [
            'display' => $element->visible_web ? null : 'none',
        ];

        // Add validation rules
        $validation = $this->transformValidationRules($element);
        if (!empty($validation)) {
            $elementData['validation'] = $validation;
        }

        // Add conditions
        $conditions = $this->transformConditions($element);
        if (!empty($conditions)) {
            $elementData['conditions'] = $conditions;
        }

        // Add children if this is a container
        $children = FormElement::where('parent_id', $element->id)
            ->orderBy('order')
            ->with(['elementable', 'dataBindings.formDataSource'])
            ->get();

        if ($children->count() > 0) {
            $elementData['containerItems'] = $children->map(function (FormElement $child) {
                return $this->transformElementToPreMigrationFormat($child);
            })->toArray();
        }

        $this->addElementStyles($elementData, $element);
        return $elementData;
    }

    protected function transformRepeatableContainerAsGroup(FormElement $element, array $elementData): array
    {
        // Transform repeatable container to group format for renderer compatibility
        $elementData['type'] = 'group'; // Override type to group
        $elementData['label'] = $element->elementable?->legend ?? null;
        $elementData['groupId'] = (string)($element->id ?? '1');
        $elementData['repeater'] = true; // Always true for repeatable containers
        $elementData['repeaterLabel'] = $element->elementable?->legend ?? null;
        $elementData['repeaterItemLabel'] = $element->elementable?->repeater_item_label;
        $elementData['clear_button'] = $element->elementable?->clear_button ?? false;
        $elementData['codeContext'] = [
            'name' => $this->generateCodeContextName($element->name ?? 'group')
        ];

        // Add styles
        $elementData['pdfStyles'] = [
            'display' => $element->visible_pdf ? null : 'none',
        ];
        $elementData['webStyles'] = [
            'display' => $element->visible_web ? null : 'none',
        ];

        // Add validation rules
        $validation = $this->transformValidationRules($element);
        if (!empty($validation)) {
            $elementData['validation'] = $validation;
        }

        // Add conditions
        $conditions = $this->transformConditions($element);
        if (!empty($conditions)) {
            $elementData['conditions'] = $conditions;
        }

        // Get children and transform them as group fields
        $children = FormElement::where('parent_id', $element->id)
            ->orderBy('order')
            ->with(['elementable', 'dataBindings.formDataSource'])
            ->get();

        $fields = [];
        if ($children->count() > 0) {
            $fields = $children->map(function (FormElement $child) {
                return $this->transformElementToPreMigrationFormat($child);
            })->toArray();
        }

        // Create groupItems structure expected by the renderer
        $elementData['groupItems'] = [
            ['fields' => $fields]
        ];

        // Add container-specific attributes
        $attributes = $this->getElementAttributes($element);
        if (!empty($attributes)) {
            $elementData['attributes'] = $this->remapAttributes($attributes);
            $elementData['attributes']['id'] = $elementData['id'];
        }

        $this->addElementStyles($elementData, $element);
        return $elementData;
    }

    protected function transformGroupElement(FormElement $element, array $elementData): array
    {
        $elementData['label'] = $element->elementable?->legend ?? null;
        $elementData['groupId'] = (string)($element->id ?? '1');
        $elementData['repeater'] = $element->elementable?->is_repeatable ?? false;
        $elementData['repeaterLabel'] = $element->elementable?->legend ?? null;
        $elementData['repeaterItemLabel'] = $element->elementable?->repeater_item_label;
        $elementData['clear_button'] = $element->elementable?->clear_button ?? false;
        $elementData['codeContext'] = [
            'name' => $this->generateCodeContextName($element->name ?? 'group')
        ];

        // Add styles
        $elementData['pdfStyles'] = [
            'display' => $element->visible_pdf ? null : 'none',
        ];
        $elementData['webStyles'] = [
            'display' => $element->visible_web ? null : 'none',
        ];

        // Add validation rules
        $validation = $this->transformValidationRules($element);
        if (!empty($validation)) {
            $elementData['validation'] = $validation;
        }

        // Add conditions
        $conditions = $this->transformConditions($element);
        if (!empty($conditions)) {
            $elementData['conditions'] = $conditions;
        }

        // Get children and group them into fields
        $children = FormElement::where('parent_id', $element->id)
            ->orderBy('order')
            ->with(['elementable', 'dataBindings.formDataSource'])
            ->get();

        $fields = [];
        if ($children->count() > 0) {
            $fields = $children->map(function (FormElement $child) {
                return $this->transformElementToPreMigrationFormat($child);
            })->toArray();
        }

        $elementData['groupItems'] = [
            ['fields' => $fields]
        ];

        // Add group-specific attributes
        $attributes = $this->getElementAttributes($element);
        if (!empty($attributes)) {
            $elementData['attributes'] = $this->remapAttributes($attributes);
        }

        $this->addElementStyles($elementData, $element);
        return $elementData;
    }

    protected function transformStandardElement(FormElement $element, array $elementData, string $originalType): array
    {
        $elementData['attributes'] = $this->remapAttributes($this->getElementAttributes($element));
        // Basic properties for all standard elements
        $elementData['label'] = $elementData['attributes']['label'] ?? $element->name;
        $elementData['helperText'] = $element->help_text;
        $elementData['mask'] = null;
        $elementData['codeContext'] = [
            'name' => $this->generateCodeContextName($element->name ?? 'field')
        ];

        $elementData['pdfStyles'] = [
            'display' => $element->visible_pdf ? null : 'none',
        ];
        $elementData['webStyles'] = [
            'display' => $element->visible_web ? null : 'none',
        ];

        // Add input type for specific elements
        if (in_array($originalType, ['text-input', 'text-area', 'textarea-input', 'number-input', 'date-input', 'date-select-input', 'file-input'])) {
            $elementData['inputType'] = $this->getInputType($originalType);
        }

        // Add validation rules
        $validation = $this->transformValidationRules($element);
        if (!empty($validation)) {
            $elementData['validation'] = $validation;
        }

        // Add conditions
        $conditions = $this->transformConditions($element);
        if (!empty($conditions)) {
            $elementData['conditions'] = $conditions;
        }

        // Add data bindings if element saves on submit
        if ($element->save_on_submit) {
            $databindings = $this->getDataBindingsForPreMigration($element);
            if (!empty($databindings)) {
                $elementData['databindings'] = $databindings;
            }
        }

        // Add special properties for specific element types
        $this->addElementSpecificProperties($elementData, $element, $originalType);

        $this->addElementStyles($elementData, $element);
        return $elementData;
    }

    protected function mapElementTypeToPreMigration(string $elementType): string
    {
        $typeMap = [
            'text-input' => 'text-input',
            'number-input' => 'number-input',
            'text-area' => 'textarea',
            'textarea-input' => 'text-area',
            'dropdown' => 'dropdown',
            'select-input' => 'dropdown',
            'select' => 'dropdown',
            'checkbox-input' => 'checkbox',
            'checkbox' => 'checkbox',
            'toggle-input' => 'toggle',
            'toggle' => 'toggle',
            'date-input' => 'date',
            'date-select-input' => 'date',
            'date' => 'date',
            'button-input' => 'button',
            'button' => 'button',
            'radio-input' => 'radio',
            'radio' => 'radio',
            'text-info' => 'text-info',
            'link' => 'link',
            'file-input' => 'file',
            'file' => 'file',
            'table' => 'table',
            'number-display' => 'number-display',
            'container' => 'container',
            'group' => 'group',
            'fieldset' => 'group',
            'html' => 'html'
        ];

        return $typeMap[$elementType] ?? $elementType;
    }

    protected function isGroupElement(string $elementType): bool
    {
        return in_array($elementType, ['group', 'fieldset']);
    }

    protected function getInputType(string $elementType): string
    {
        $inputTypeMap = [
            'text-input' => 'text',
            'text-area' => 'textarea',
            'textarea-input' => 'textarea',
            'number-input' => 'number',
            'date-input' => 'date',
            'date-select-input' => 'date',
            'file-input' => 'file'
        ];

        return $inputTypeMap[$elementType] ?? 'text';
    }

    protected function addElementSpecificProperties(array &$elementData, FormElement $element, string $elementType): void
    {
        switch ($elementType) {
            case 'checkbox':
            case 'toggle':
                // Add default value for boolean elements
                $attributes = $this->getElementAttributes($element);
                if (isset($attributes['default_value'])) {
                    $elementData['defaultValue'] = (bool)$attributes['default_value'];
                }
                break;
            case 'radio-input':
            case 'radio':
                $radioOptions = [];
                if ($element->elementable && method_exists($element->elementable, 'options')) {
                    $optionsCollection = $element->elementable->options()->ordered()->get();
                    $radioOptions = $optionsCollection->map(function ($option) {
                        return [
                            'value' => $option->value ?? '',
                            'text' => $option->label ?? '',
                        ];
                    })->toArray();
                }
                if (!empty($radioOptions)) {
                    $elementData['listItems'] = $radioOptions;
                }
                break;
            case 'dropdown':
            case 'select-input':
            case 'select':
                $options = [];
                if ($element->elementable && method_exists($element->elementable, 'options')) {
                    $optionsCollection = $element->elementable->options()->ordered()->get();
                    $options = $optionsCollection->map(function ($option) {
                        return [
                            'name' => $option->label ?? '',
                            'text' => $option->label ?? '',
                            'value' => $option->value ?? '',
                        ];
                    })->toArray();
                }
                if (!empty($options)) {
                    $elementData['listItems'] = $options;
                }
                break;
            case 'file':
                // Add file-specific properties
                $attributes = $this->getElementAttributes($element);
                if (isset($attributes['accept'])) {
                    $elementData['accept'] = $attributes['accept'];
                }
                if (isset($attributes['multiple'])) {
                    $elementData['multiple'] = $attributes['multiple'];
                }
                break;

            case 'table':
                // Add table-specific properties
                $attributes = $this->getElementAttributes($element);
                if (isset($attributes['columns'])) {
                    $elementData['columns'] = $attributes['columns'];
                }
                break;
            case 'text-info':
                // Add text info specific properties
                $attributes = $this->getElementAttributes($element);
                if (isset($attributes['content'])) {
                    $elementData['value'] = $attributes['content'];
                }
                break;
        }
    }

    protected function generateCodeContextName(string $name): string
    {
        // Convert name to snake_case for code context
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        $name = trim($name, '_');
        return $name ?: 'field';
    }

    protected function transformValidationRules(FormElement $element): array
    {
        $validation = [];
        $attributes = $this->getElementAttributes($element);

        // Handle different validation types based on element attributes
        // Only include the required validation if the element is required
        if (isset($attributes['required']) && $attributes['required'] || $element->is_required) {
            $validation[] = [
                'type' => 'required',
                'value' => (bool)($attributes['required'] ?? $element->is_required),
                'errorMessage' => $attributes['required_message'] ?? 'This field is required!'
            ];
        }

        // Handle min/max length for text fields
        if (isset($attributes['min_length'])) {
            $validation[] = [
                'type' => 'minLength',
                'value' => (string)$attributes['min_length'],
                'errorMessage' => $attributes['min_length_message'] ?? "Minimum length is {$attributes['min_length']}"
            ];
        }

        if (isset($attributes['max_length'])) {
            $validation[] = [
                'type' => 'maxLength',
                'value' => (string)$attributes['max_length'],
                'errorMessage' => $attributes['max_length_message'] ?? "Maximum length is {$attributes['max_length']}"
            ];
        }

        // Handle min/max value for number fields
        if (isset($attributes['min_value'])) {
            $validation[] = [
                'type' => 'minValue',
                'value' => (string)$attributes['min_value'],
                'errorMessage' => $attributes['min_value_message'] ?? "Minimum value is {$attributes['min_value']}"
            ];
        }

        if (isset($attributes['max_value'])) {
            $validation[] = [
                'type' => 'maxValue',
                'value' => (string)$attributes['max_value'],
                'errorMessage' => $attributes['max_value_message'] ?? "Maximum value is {$attributes['max_value']}"
            ];
        }

        // Handle regex pattern validation
        if (isset($attributes['pattern'])) {
            $validation[] = [
                'type' => 'pattern',
                'value' => $attributes['pattern'],
                'errorMessage' => $attributes['pattern_message'] ?? 'Invalid format'
            ];
        }

        // Handle email validation
        if (isset($attributes['email']) && $attributes['email']) {
            $validation[] = [
                'type' => 'email',
                'value' => 'true',
                'errorMessage' => $attributes['email_message'] ?? 'Invalid email format'
            ];
        }

        // Handle custom JavaScript validation
        if (isset($attributes['custom_validation'])) {
            $validation[] = [
                'type' => 'javascript',
                'value' => $attributes['custom_validation'],
                'errorMessage' => $attributes['custom_validation_message'] ?? 'Custom validation failed'
            ];
        }

        return $validation;
    }

    protected function transformConditions(FormElement $element): array
    {
        $conditions = [];

        // Always include saveOnSubmit condition with the actual boolean value
        $conditions[] = [
            'type' => 'saveOnSubmit',
            'value' => $element->save_on_submit ? '{return true}' : '{return false}'
        ];

        // Always include readOnly condition with the actual boolean value
        $conditions[] = [
            'type' => 'readOnly',
            'value' => $element->is_read_only ? '{return true}' : '{return false}'
        ];

        if (!$element->visible_web && !$element->visible_pdf) {
            $conditions[] = [
                'type' => 'visibility',
                'value' => 'NOT visible'
            ];
        }

        return $conditions;
    }

    protected function addElementStyles(array &$elementData, FormElement $element): void
    {
        $attributes = $this->getElementAttributes($element);

        // Extract web styles
        $webStyles = [];
        if (isset($attributes['web_styles']) && is_array($attributes['web_styles'])) {
            $webStyles = $attributes['web_styles'];
        } elseif (isset($attributes['css_classes'])) {
            // Handle CSS classes as styles
            $webStyles['class'] = $attributes['css_classes'];
        }

        // Extract PDF styles
        $pdfStyles = [];
        if (isset($attributes['pdf_styles']) && is_array($attributes['pdf_styles'])) {
            $pdfStyles = $attributes['pdf_styles'];
        }

        // Look for individual style properties in attributes
        $styleProperties = [
            'background-color',
            'color',
            'font-size',
            'font-weight',
            'font-family',
            'border',
            'margin',
            'padding',
            'width',
            'height',
            'display',
            'text-align',
            'vertical-align',
            'line-height'
        ];

        foreach ($styleProperties as $property) {
            if (isset($attributes["web_{$property}"])) {
                $webStyles[$property] = $attributes["web_{$property}"];
            }
            if (isset($attributes["pdf_{$property}"])) {
                $pdfStyles[$property] = $attributes["pdf_{$property}"];
            }
        }

        // Add styles to element data if they exist
        if (!empty($webStyles)) {
            $elementData['webStyles'] = $webStyles;
        }

        if (!empty($pdfStyles)) {
            $elementData['pdfStyles'] = $pdfStyles;
        }
    }

    protected function getDataBindings(FormElement $element): array
    {
        $dataBindings = [];

        foreach ($element->dataBindings as $dataBinding) {
            $dataBindings[] = [
                'data_source_name' => $dataBinding->formDataSource->name ?? 'Unknown',
                'path' => $dataBinding->path,
                'order' => $dataBinding->order,
            ];
        }

        return $dataBindings;
    }

    protected function getDataBindingsForPreMigration(FormElement $element): array
    {
        // For the pre-migration format, we just return 1 binding
        // If there are multiple bindings, lets just grab the first one by order
        $firstBinding = $element->dataBindings->sortBy('order')->first();

        if (!$firstBinding) {
            return [];
        }

        return [
            'source' => $firstBinding->formDataSource->name ?? 'Unknown',
            'path' => $firstBinding->path
        ];
    }

    protected function getDataSources(FormVersion $formVersion): array
    {
        $dataSources = [];

        foreach ($formVersion->formDataSources as $formDataSource) {
            $dataSources[] = [
                'name' => $formDataSource->name,
                'type' => $formDataSource->type,
                'endpoint' => $formDataSource->endpoint,
                'description' => $formDataSource->description,
                'params' => $this->decodeJsonField($formDataSource->params),
                'body' => $formDataSource->body,
                'headers' => $this->decodeJsonField($formDataSource->headers),
                'host' => $formDataSource->host,
                'order' => $formDataSource->pivot->order ?? 0,
            ];
        }

        return $dataSources;
    }

    /**
     * Utility to convert snake_case to camelCase.
     */
    protected function toCamelCase(string $str): string
    {
        return preg_replace_callback('/_([a-z])/', function ($matches) {
            return strtoupper($matches[1]);
        }, $str);
    }

    /**
     * Decode JSON field to object/array, return empty object if invalid or empty.
     */
    protected function decodeJsonField(?string $jsonString): array|object
    {
        if (empty($jsonString)) {
            return (object)[];
        }

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return (object)[];
        }

        return is_array($decoded) ? (object)$decoded : (object)[];
    }

    /**
     * Custom mapping for special attribute cases.
     * This method allows for specific keys to be transformed into
     * different keys or values in the final JSON output.
     * This is required for the pre-migration format
     * where some attributes need to be renamed or transformed.
     * @param string $key
     * @param mixed $value
     * @return array|null
     */
    protected function customAttributeMapping(string $key, $value): ?array
    {
        switch ($key) {
            case 'default_value':
                return ['value', $value];
            case 'button_type':
                return ['kind', $value];
            case 'default_date':
                return ['value', $value];
            case 'placeholder_text':
                return ['placeholder', $value];
            case 'visible_label':
                return ['hideLabel', !$value];
            default:
                return null;
        }
    }

    /**
     * Normalize and camelCase attributes, with custom mapping.
     * @param array $attributes
     * @return array
     */
    protected function normalizeAttributes($attributes): array
    {
        if (!$attributes || !is_array($attributes)) {
            return [];
        }
        $result = [];
        foreach ($attributes as $k => $v) {
            $custom = $this->customAttributeMapping($k, $v);
            if ($custom) {
                [$newKey, $newValue] = $custom;
                $result[$newKey] = $newValue;
            } else {
                $result[$this->toCamelCase($k)] = $v;
            }
        }
        return $result;
    }

    /**
     * Remap/clean attributes array for export using custom rules and camelCase normalization.
     * @param array $attributes
     * @return array
     */
    protected function remapAttributes(array $attributes): array
    {
        return $this->normalizeAttributes($attributes);
    }
}
