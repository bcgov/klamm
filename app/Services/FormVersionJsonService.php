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
            'webStyleSheet',
            'pdfStyleSheet',
            'webFormScript',
            'pdfFormScript'
        ]);

        return [
            'formversion' => [
                'uuid' => $formVersion->uuid ?? $formVersion->id,
                'name' => $formVersion->form->form_title ?? 'Unknown Form',
                'id' => $formVersion->form->form_id ?? '',
                'version' => $formVersion->version_number,
                'status' => $formVersion->status,
                'data' => $this->getFormVersionData($formVersion),
                'styles' => $this->getStyles($formVersion),
                'scripts' => $this->getScripts($formVersion),
                'elements' => $this->getElements($formVersion)
            ]
        ];
    }

    public function generatePreMigrationJson(FormVersion $formVersion): array
    {
        // Load the form version with necessary relationships
        $formVersion->load([
            'form',
            'formElements.elementable',
            'webStyleSheet',
            'pdfStyleSheet',
            'webFormScript',
            'pdfFormScript'
        ]);

        return [
            'version' => $formVersion->version_number,
            'ministry_id' => 2, // Default ministry ID - you may want to make this configurable
            'id' => $formVersion->uuid ?? $formVersion->id,
            'lastModified' => $formVersion->updated_at?->format('c') ?? now()->format('c'),
            'title' => $formVersion->form->form_title ?? 'Unknown Form',
            'form_id' => $formVersion->form->form_id ?? '',
            'deployed_to' => null,
            'footer' => $formVersion->footer,
            'dataSources' => $this->getDataSources(), // Placeholder for now
            'data' => [
                'items' => $this->transformElementsToPreMigrationFormat($formVersion)
            ]
        ];
    }

    protected function getFormVersionData(FormVersion $formVersion): array
    {
        return [
            'footer' => $formVersion->footer,
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
        $elementData = [
            'uuid' => $element->uuid ?? $element->id,
            'type' => $this->getElementType($element),
            'name' => $element->name,
            'description' => $element->description,
            'help_text' => $element->help_text,
            'is_visible' => $element->is_visible,
            'visible_web' => $element->visible_web,
            'visible_pdf' => $element->visible_pdf,
            'is_read_only' => $element->is_read_only,
            'save_on_submit' => $element->save_on_submit,
            'order' => $element->order,
            'parent_id' => $element->parent_id == -1 ? null : $element->parent_id,
            'attributes' => $this->getElementAttributes($element)
        ];

        // Load and add children if this element has any
        $children = FormElement::where('parent_id', $element->id)
            ->orderBy('order')
            ->with('elementable')
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

    protected function getDataSources(): array
    {
        // Return default data sources - you may want to make this configurable
        return [
            [
                'name' => 'Contact',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Contact/DT Form Instance/@@attachmentId',
                'params' => [
                    'ViewMode' => 'Organization',
                    'getChildren' => 'all'
                ],
                'body' => null,
                'headers' => [
                    'Authorization' => 'Bearer @@token@@'
                ],
                'host' => 'https://sieblab-data.api.gov.bc.ca'
            ],
            [
                'name' => 'Service Request',
                'type' => 'GET',
                'endpoint' => '/fwd/v1.0/data/Forms Service Request/DT Form Instance/@@attachmentId',
                'params' => [
                    'ViewMode' => 'Organization',
                    'getChildren' => 'all'
                ],
                'body' => null,
                'headers' => [
                    'Authorization' => 'Bearer @@token@@'
                ],
                'host' => 'https://sieblab-data.api.gov.bc.ca'
            ],
            [
                'name' => 'Case',
                'type' => 'GET',
                'endpoint' => '/ICM REST Forms Case/DT Form Instance/@@attachmentId',
                'params' => [
                    'ViewMode' => 'Catalog',
                    'getChildren' => 'all'
                ],
                'body' => null,
                'headers' => [
                    'Authorization' => 'Bearer @@token@@'
                ],
                'host' => 'SIEBEL_ICM_API_HOST'
            ]
        ];
    }

    protected function transformElementsToPreMigrationFormat(FormVersion $formVersion): array
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
            return $this->transformElementToPreMigrationFormat($element);
        })->toArray();
    }

    protected function transformElementToPreMigrationFormat(FormElement $element): array
    {
        $elementType = $this->getElementType($element);

        $elementData = [
            'type' => $this->mapElementTypeToPreMigration($elementType),
            'id' => $element->uuid,
        ];

        // Handle different element types
        if ($elementType === 'container') {
            return $this->transformContainerElement($element, $elementData);
        } elseif ($this->isGroupElement($elementType)) {
            return $this->transformGroupElement($element, $elementData);
        } else {
            return $this->transformStandardElement($element, $elementData, $elementType);
        }
    }

    protected function transformContainerElement(FormElement $element, array $elementData): array
    {
        $elementData['containerId'] = (string)($element->id ?? '');
        $elementData['clear_button'] = false;
        $elementData['codeContext'] = [
            'name' => $this->generateCodeContextName($element->name ?? 'container')
        ];

        // Add children if this is a container
        $children = FormElement::where('parent_id', $element->id)
            ->orderBy('order')
            ->with('elementable')
            ->get();

        if ($children->count() > 0) {
            $elementData['containerItems'] = $children->map(function (FormElement $child) {
                return $this->transformElementToPreMigrationFormat($child);
            })->toArray();
        }

        $this->addElementStyles($elementData, $element);
        return $elementData;
    }

    protected function transformGroupElement(FormElement $element, array $elementData): array
    {
        $elementData['label'] = $element->name;
        $elementData['groupId'] = (string)($element->id ?? '1');
        $elementData['repeater'] = false;
        $elementData['clear_button'] = false;
        $elementData['codeContext'] = [
            'name' => $this->generateCodeContextName($element->name ?? 'group')
        ];

        // Get children and group them into fields
        $children = FormElement::where('parent_id', $element->id)
            ->orderBy('order')
            ->with('elementable')
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

        $this->addElementStyles($elementData, $element);
        return $elementData;
    }

    protected function transformStandardElement(FormElement $element, array $elementData, string $originalType): array
    {
        // Basic properties for all standard elements
        $elementData['label'] = $element->name;
        $elementData['helperText'] = $element->help_text;
        $elementData['mask'] = null;
        $elementData['codeContext'] = [
            'name' => $this->generateCodeContextName($element->name ?? 'field')
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
            $databindings = $this->getDataBindings($element);
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
            'textarea-input' => 'textarea',
            'dropdown' => 'select',
            'select-input' => 'select',
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

            case 'radio':
            case 'dropdown':
                // Add list items if they exist in attributes
                $attributes = $this->getElementAttributes($element);
                if (isset($attributes['options']) && is_array($attributes['options'])) {
                    $elementData['listItems'] = array_map(function ($option) {
                        return [
                            'name' => $option['label'] ?? $option['value'] ?? '',
                            'text' => $option['label'] ?? $option['value'] ?? '',
                            'value' => $option['value'] ?? ''
                        ];
                    }, $attributes['options']);
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
        if (isset($attributes['required']) && $attributes['required']) {
            $validation[] = [
                'type' => 'required',
                'value' => 'true',
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

        // Fallback: if element is marked as read-only, add required validation
        if (empty($validation) && $element->is_read_only) {
            $validation[] = [
                'type' => 'required',
                'value' => 'true',
                'errorMessage' => 'This field is required!'
            ];
        }

        return $validation;
    }

    protected function transformConditions(FormElement $element): array
    {
        $conditions = [];

        if ($element->save_on_submit) {
            $conditions[] = [
                'type' => 'saveOnSubmit',
                'value' => 'true'
            ];
        }

        if ($element->is_read_only) {
            $conditions[] = [
                'type' => 'readOnly',
                'value' => 'true'
            ];
        }

        if (!$element->is_visible) {
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
        $attributes = $this->getElementAttributes($element);

        // Check if data bindings are explicitly defined in attributes
        if (isset($attributes['data_source']) || isset($attributes['data_path'])) {
            return [
                'source' => $attributes['data_source'] ?? 'Contact',
                'path' => $attributes['data_path'] ?? '/default/path/' . $this->generateCodeContextName($element->name ?? 'field')
            ];
        }

        // Check for legacy binding format
        if (isset($attributes['binding_source']) && isset($attributes['binding_path'])) {
            return [
                'source' => $attributes['binding_source'],
                'path' => $attributes['binding_path']
            ];
        }

        // Default data binding
        return [
            'source' => 'Contact', // Default source - could be made configurable
            'path' => '/default/path/' . $this->generateCodeContextName($element->name ?? 'field')
        ];
    }
}
