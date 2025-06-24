<?php

namespace App\Filament\Forms\Helpers;

use App\Models\FormField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class SchemaFormatter
{
    /**
     * Get all mapping options for all fields (static, for reuse).
     * Memory optimized version that loads only essential data.
     *
     * @param bool $lightweight Whether to use lightweight format (text only) or rich HTML format
     * @return array Options array for field mapping
     */
    public static function getAllMappingOptions(bool $lightweight = true): array
    {
        // Use minimal eager loading to reduce memory usage
        $allFields = \App\Models\FormField::select(['id', 'name', 'label'])
            ->with(['dataType:id,name'])
            ->get();

        $options = [
            'new' => 'Create New Field',
        ];

        $sortedFields = $allFields->sortBy('label');
        foreach ($sortedFields as $field) {
            $id = (string) $field->id;
            $dataType = $field->dataType->name ?? 'unknown';

            $typeIcon = match ($dataType) {
                'text' => '✏️',
                'number' => '🔢',
                'email' => '📧',
                'password' => '🔒',
                'date' => '📅',
                'datetime' => '🕒',
                'checkbox' => '✅',
                'radio' => '🔘',
                'select' => '🔽',
                'multiselect' => '📋',
                'textarea' => '📝',
                'tel' => '📞',
                'file' => '📎',
                'image' => '🖼️',
                'url' => '🔗',
                'hidden' => '👁️',
                'container' => '📦',
                default => '📄'
            };

            if ($lightweight) {
                // Simple text-based format - much more memory efficient
                $options[$id] = "#$field->id: {$field->label} ({$field->name}) $typeIcon";
            } else {
                // Rich HTML format - looks nicer but uses more memory
                $optionLabel = "<span style='display:flex;align-items:center;'>" .
                    "<span style='color:#666;min-width:50px;'>#$field->id</span>" .
                    "<strong style='margin-right:8px;'>{$field->label}</strong> " .
                    "<span style='color:#777;margin-right:8px;'>({$field->name})</span>" .
                    "<span style='color:#444;background:#f3f4f6;padding:2px 6px;border-radius:4px;'>" .
                    "$typeIcon $dataType</span>" .
                    "</span>";
                $options[$id] = $optionLabel;
            }
        }

        $memoryFormat = $lightweight ? "lightweight" : "rich HTML";
        \Illuminate\Support\Facades\Log::debug("📋 Field mapping options created: " . count($options) . " options available ({$memoryFormat} format)");
        return $options;
    }

    /**
     * Get mapping options for a field, optionally using precomputed options.
     *
     * @param string $type
     * @param string $label
     * @param string $name
     * @param bool $repeating
     * @param array|null $precomputedOptions
     * @return array
     */
    public function getMappingOptionsWithDetails(string $type, string $label, string $name, bool $repeating = false, ?array $precomputedOptions = null): array
    {
        if ($precomputedOptions !== null) {
            return $precomputedOptions;
        }

        // Map the field type from import format to system format
        $mappedType = (new SchemaParser())->mapFieldType($type);

        // Get all form fields with all needed relationships eager loaded to avoid lazy loading errors
        $allFields = FormField::with([
            'dataType',
            'validations',
            'fieldGroups',
            'webStyles',
            'pdfStyles',
            'formFieldDateFormat',
            'formFieldValue',
            'selectOptionInstances'
        ])->get();

        // Initialize options with "Create New" option at the top with visual styling
        $options = [
            'new' => 'Create New Field',
        ];

        // Helper function to add a field to options and details with visual formatting
        $addFieldOption = function ($field) use (&$options) {
            // Store the field ID as a string key to prevent type conversion issues
            $id = (string) $field->id;

            // Get the field type for visual identification
            $dataType = $field->dataType->name ?? 'unknown';

            // Add icon based on field type for better visual identification
            $typeIcon = match ($dataType) {
                'text' => '✏️',
                'number' => '🔢',
                'email' => '📧',
                'password' => '🔒',
                'date' => '📅',
                'datetime' => '🕒',
                'checkbox' => '✅',
                'radio' => '🔘',
                'select' => '🔽',
                'multiselect' => '📋',
                'textarea' => '📝',
                'tel' => '📞',
                'file' => '📎',
                'image' => '🖼️',
                'url' => '🔗',
                'hidden' => '👁️',
                'container' => '📦',
                default => '📄'
            };

            // Create a visually formatted option label with color and spacing
            $optionLabel = "<span style='display:flex;align-items:center;'>" .
                "<span style='color:#666;min-width:50px;'>#$field->id</span>" .
                "<strong style='margin-right:8px;'>{$field->label}</strong> " .
                "<span style='color:#777;margin-right:8px;'>({$field->name})</span>" .
                "<span style='color:#444;background:#f3f4f6;padding:2px 6px;border-radius:4px;'>" .
                "$typeIcon $dataType</span>" .
                "</span>";

            // Store with the ID as both the key and the value to ensure consistent type handling
            $options[$id] = $optionLabel;
        };

        // Add all fields alphabetically by label for consistent ordering
        $sortedFields = $allFields->sortBy('label');
        foreach ($sortedFields as $field) {
            $addFieldOption($field);
        }

        Log::debug("📋 Field mapping options created: " . count($options) . " options available for selection");
        return $options;
    }

    /**
     * Generate a rich HTML overview of the imported field
     *
     * @param array $importField The field data to format
     * @return string HTML representation
     */
    public function generateImportFieldOverview(array $importField): string
    {
        // Extract essential field properties
        $name = $importField['name'] ?? $importField['uuid'] ?? '';
        $label = $importField['label'] ?? '';
        $type = $importField['type'] ?? $importField['dataType'] ?? $importField['data_type'] ?? '';

        // Handle repeating fields in either format
        $repeating = false;
        if (isset($importField['repeats'])) {
            $repeating = $importField['repeats'];
        } elseif (isset($importField['repeating'])) {
            $repeating = $importField['repeating'];
        } elseif (isset($importField['is_repeating'])) {
            $repeating = $importField['is_repeating'];
        }

        // Extract other common properties
        $helpText = $importField['help_text'] ?? $importField['helpText'] ?? '';
        $description = $importField['description'] ?? '';

        // Process validations
        $validations = [];
        if (isset($importField['validations'])) {
            $validations = $importField['validations'];
        } elseif (isset($importField['validation'])) {
            $validations = $importField['validation'];
        }
        $validationsText = is_array($validations)
            ? collect($validations)->map(function ($v, $k) {
                return is_array($v) ? "$k: " . json_encode($v) : "$k: $v";
            })->implode(', ')
            : $validations;

        // Extract options for select-type fields (legacy and Adze)
        $fieldOptions = '';
        if (isset($importField['options'])) {
            $fieldOptions = collect($importField['options'])->map(function ($opt) {
                return is_array($opt)
                    ? ($opt['label'] ?? $opt['text'] ?? $opt['value'] ?? '')
                    : $opt;
            })->implode(', ');
        } elseif (isset($importField['listItems']) && is_array($importField['listItems'])) {
            // Adze format: render as bullet list
            $fieldOptions = '<ul class="list-disc pl-5">';
            foreach ($importField['listItems'] as $item) {
                $label = $item['text'] ?? $item['label'] ?? $item['name'] ?? '';
                $value = $item['value'] ?? '';
                $fieldOptions .= '<li><span class="font-medium">' . htmlspecialchars($label) . '</span>';
                if ($value !== '') {
                    $fieldOptions .= ' <span class="text-gray-500">(' . htmlspecialchars($value) . ')</span>';
                }
                $fieldOptions .= '</li>';
            }
            $fieldOptions .= '</ul>';
        }

        // Build field details array with emojis for better visual scanning
        $fieldDetails = [
            '🔑 Basic Info' => [
                'Name' => $name,
                'Type' => $type,
                'Label' => $label,
                'Repeating' => $repeating ? 'Yes' : 'No',
            ],
            '📝 Content' => [
                'Help Text' => $helpText ?: 'None',
                'Description' => $description ?: 'None',
            ],
            '✓ Validation' => [
                'Rules' => $validationsText ?: 'None',
            ],
        ];

        // Add options section if applicable
        if ($fieldOptions) {
            $fieldDetails['🔽 Options'] = [
                'Options' => $fieldOptions,
                'Count' => isset($importField['options']) ? count($importField['options']) : (isset($importField['listItems']) ? count($importField['listItems']) : 0),
            ];
        }

        // Add additional properties section for anything else
        $additional = [];
        foreach ($importField as $key => $value) {
            // Skip keys we've already processed
            if (!in_array($key, [
                'name',
                'uuid',
                'label',
                'type',
                'dataType',
                'data_type',
                'repeats',
                'repeating',
                'is_repeating',
                'help_text',
                'helpText',
                'description',
                'validations',
                'validation',
                'options'
            ])) {
                if (is_array($value)) {
                    $additional[$key] = json_encode($value);
                } elseif (!empty($value) || $value === '0' || $value === 0) {
                    $additional[$key] = is_string($value) ? $value : (string) $value;
                }
            }
        }

        if (!empty($additional)) {
            $fieldDetails['⚙️ Additional Properties'] = $additional;
        }

        // Generate HTML with collapsible sections for better organization
        return $this->generateHTMLFromFieldDetails($fieldDetails);
    }

    /**
     * Generate HTML overview of an existing field
     *
     * @param FormField $field The field model to format
     * @return string HTML representation
     */
    public function generateExistingFieldOverview(FormField $field): string
    {
        // Basic info
        $fieldDetails = [
            '🔑 Basic Info' => [
                'Field ID' => $field->id,
                'Name' => $field->name,
                'Type' => $field->dataType->name ?? 'unknown',
                'Label' => $field->label,
                'Created' => $field->created_at ? $field->created_at->format('Y-m-d') : '',
                'Updated' => $field->updated_at ? $field->updated_at->format('Y-m-d') : '',
            ],
            '📝 Content & Display' => [
                'Help Text' => $field->help_text ?: 'None',
                'Description' => $field->description ?: 'None',
                'Data Binding' => $field->data_binding ?: 'None',
                'Mask' => $field->mask ?: 'None',
            ],
            '✓ Validation' => [
                'Rules' => ($field->validations && $field->validations->count() > 0)
                    ? $field->validations->map(function ($validation) {
                        return "$validation->type: $validation->value";
                    })->join(', ')
                    : 'None',
            ],
            '🗂️ Organization' => [
                'Field Groups' => ($field->fieldGroups && $field->fieldGroups->count() > 0)
                    ? $field->fieldGroups->pluck('name')->join(', ')
                    : 'Not assigned to any groups',
            ],
            '🎨 Styling' => [
                'Web Styles' => ($field->webStyles && $field->webStyles->count() > 0)
                    ? $field->webStyles->pluck('name')->join(', ')
                    : 'No web styles applied',
                'PDF Styles' => ($field->pdfStyles && $field->pdfStyles->count() > 0)
                    ? $field->pdfStyles->pluck('name')->join(', ')
                    : 'No PDF styles applied',
            ],
        ];

        // Options for select/radio/checkbox
        if (in_array($field->dataType->name ?? '', ['select', 'multiselect', 'radio', 'checkbox'])) {
            $optionsHtml = '';
            if ($field->selectOptionInstances && $field->selectOptionInstances->count() > 0) {
                $optionsHtml = '<ul class="list-disc pl-5">';
                foreach ($field->selectOptionInstances as $opt) {
                    $optionsHtml .= '<li><span class="font-medium">' . htmlspecialchars($opt->label) . '</span>';
                    if ($opt->value !== '') {
                        $optionsHtml .= ' <span class="text-gray-500">(' . htmlspecialchars($opt->value) . ')</span>';
                    }
                    $optionsHtml .= '</li>';
                }
                $optionsHtml .= '</ul>';
            } else {
                $optionsHtml = 'No options defined';
            }
            $fieldDetails['🔽 Options'] = [
                'Options' => $optionsHtml,
                'Count' => $field->selectOptionInstances ? $field->selectOptionInstances->count() : 0,
            ];
        }

        // Date format
        if (in_array($field->dataType->name ?? '', ['date', 'datetime'])) {
            $fieldDetails['📅 Date Settings'] = [
                'Date Format' => $field->formFieldDateFormat ? ($field->formFieldDateFormat->format ?? 'Default') : 'Default system format',
            ];
        }

        // Default value
        $fieldDetails['⚙️ Default Settings'] = [
            'Default Value' => $field->formFieldValue ? ($field->formFieldValue->value ?? 'None') : 'None',
        ];

        // Usage
        $formsCount = $field->formVersions ? $field->formVersions->count() : 0;
        $usage = [
            'Used In' => $formsCount . ' form version(s)',
        ];
        if ($formsCount > 0) {
            $formNames = $field->formVersions->pluck('form_title')->unique()->take(3)->join(', ');
            if ($field->formVersions->count() > 3) {
                $formNames .= ' and ' . ($field->formVersions->count() - 3) . ' more';
            }
            $usage['Form Names'] = $formNames;
        }
        $fieldDetails['📊 Usage Statistics'] = $usage;

        // Action
        $fieldDetails['🔄 Action'] = [
            'Action' => 'Will use this existing field for mapping',
        ];

        // Generate HTML with the same structure as import fields
        return $this->generateHTMLFromFieldDetails($fieldDetails);
    }

    /**
     * Generate HTML from a field details array
     *
     * @param array $fieldDetails Structured field details array
     * @return string HTML markup
     */
    protected function generateHTMLFromFieldDetails(array $fieldDetails): string
    {
        $html = '<div class="space-y-4 p-2 overflow-auto max-h-64">';

        foreach ($fieldDetails as $section => $items) {
            $html .= '<div class="border rounded-md bg-gray-50">';
            $html .= '<div class="font-medium text-lg p-2 bg-gray-100 border-b">' . $section . '</div>';
            $html .= '<table class="min-w-full">';

            foreach ($items as $key => $value) {
                $html .= '<tr class="border-t border-gray-200">';
                $html .= '<td class="py-2 px-4 font-medium text-gray-700 w-1/3">' . htmlspecialchars($key) . '</td>';
                if ($key === 'Options') {
                    $html .= '<td class="py-2 px-4 text-gray-800">' . $value . '</td>'; // allow HTML for options
                } elseif (empty($value) && $value !== '0' && $value !== 0) {
                    $html .= '<td class="py-2 px-4 text-gray-500 italic">empty</td>';
                } else {
                    $html .= '<td class="py-2 px-4 text-gray-800">' . htmlspecialchars((string)$value) . '</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Get detailed information about a specific form field
     *
     * @param mixed $fieldId ID of the field
     * @return array Array of field details
     */
    public function getFormFieldDetails($fieldId): array
    {
        try {
            Log::debug("🔍 getFormFieldDetails called with ID: {$fieldId} (type: " . gettype($fieldId) . ")");

            // Handle "Create New" option with explicit string comparison
            if ($fieldId === 'new') {
                Log::debug("📝 Creating details for NEW field");
                return [
                    '✅ New Field Will Be Created' => '',
                    'Action' => 'Will create new field from import data',
                ];
            }

            // Convert to integer if it's a numeric string to ensure consistent type handling
            if (is_string($fieldId) && is_numeric($fieldId)) {
                $fieldId = (int)$fieldId;
                Log::debug("🔄 Converting string fieldId to int: {$fieldId}");
            }

            // Now make sure we have a valid integer ID for database lookup
            if (!is_int($fieldId) || $fieldId <= 0) {
                throw new \Exception("Invalid field ID: {$fieldId}");
            }

            // Get the field with all its relationships
            Log::debug("🔎 Looking up field with ID: {$fieldId} (type: " . gettype($fieldId) . ")");

            try {
                // Get with relationships
                $field = FormField::with([
                    'dataType',
                    'validations',
                    'fieldGroups',
                    'webStyles',
                    'pdfStyles',
                    'formFieldDateFormat',
                    'formFieldValue',
                    'selectOptionInstances',
                    'formVersions'  // Eager load form versions to avoid N+1 queries
                ])->findOrFail($fieldId);

                Log::debug("✅ Found field: {$field->name} (ID: {$field->id}) with type: " . ($field->dataType->name ?? 'unknown'));
            } catch (\Exception $e) {
                Log::error("❌ Error finding field: " . $e->getMessage());
                return [
                    'Error ⚠️' => 'Field not found: ' . $e->getMessage(),
                    'Suggestion 💡' => 'Try selecting a different field or creating a new one',
                ];
            }

            // Build a comprehensive details array with all field attributes, grouped by category
            $details = [
                '✅ Existing Form Field Selected' => '',
                '🔍 Basic Information' => '',
                'Field ID' => $field->id,
                'Name' => $field->name,
                'Type' => $field->dataType->name ?? 'unknown',
                'Label' => $field->label,
                'Created' => $field->created_at->format('Y-m-d'),
                'Updated' => $field->updated_at->format('Y-m-d'),
                'Action' => 'Will use this existing field for mapping',
            ];

            // Add other properties when they exist under Content section
            $details['📝 Content & Display'] = '';
            if ($field->help_text) $details['Help Text'] = $field->help_text;
            if ($field->description) $details['Description'] = $field->description;
            if ($field->data_binding) $details['Data Binding'] = $field->data_binding;
            if ($field->mask) $details['Mask'] = $field->mask;

            Log::debug("📋 Added content details for field {$field->id}");

            // Add validations under Validation section
            $details['✓ Validation Rules'] = '';
            if ($field->validations && $field->validations->count() > 0) {
                $validations = $field->validations->map(function ($validation) {
                    return "$validation->type: $validation->value";
                })->join(', ');
                $details['Validation Rules'] = $validations;
            } else {
                $details['Validation Rules'] = 'None specified';
            }

            // Add field groups under Organization section
            $details['🗂️ Organization'] = '';
            if ($field->fieldGroups && $field->fieldGroups->count() > 0) {
                $details['Field Groups'] = $field->fieldGroups->pluck('name')->join(', ');
            } else {
                $details['Field Groups'] = 'Not assigned to any groups';
            }

            // Add styles under Styling section
            $details['🎨 Styling'] = '';
            if ($field->webStyles && $field->webStyles->count() > 0) {
                $details['Web Styles'] = $field->webStyles->pluck('name')->join(', ');
            } else {
                $details['Web Styles'] = 'No web styles applied';
            }

            if ($field->pdfStyles && $field->pdfStyles->count() > 0) {
                $details['PDF Styles'] = $field->pdfStyles->pluck('name')->join(', ');
            } else {
                $details['PDF Styles'] = 'No PDF styles applied';
            }

            // Add select options under Options section if applicable
            if (in_array($field->dataType->name ?? '', ['select', 'multiselect', 'radio', 'checkbox'])) {
                $details['🔽 Options'] = '';
                if ($field->selectOptionInstances && $field->selectOptionInstances->count() > 0) {
                    $details['Options Count'] = $field->selectOptionInstances->count();
                    $options = $field->selectOptionInstances->take(5)->map(function ($opt) {
                        return $opt->label . ($opt->value ? " ({$opt->value})" : '');
                    })->join(', ');
                    if ($field->selectOptionInstances->count() > 5) {
                        $options .= ' and ' . ($field->selectOptionInstances->count() - 5) . ' more';
                    }
                    $details['Options'] = $options;
                } else {
                    $details['Options'] = 'No options defined';
                }
            }

            // Add date format if applicable
            if (in_array($field->dataType->name ?? '', ['date', 'datetime'])) {
                $details['📅 Date Settings'] = '';
                if ($field->formFieldDateFormat) {
                    $details['Date Format'] = $field->formFieldDateFormat->format ?? 'Default';
                } else {
                    $details['Date Format'] = 'Default system format';
                }
            }

            // Add form field default value if available
            $details['⚙️ Default Settings'] = '';
            if ($field->formFieldValue) {
                $details['Default Value'] = $field->formFieldValue->value ?? 'None';
            } else {
                $details['Default Value'] = 'None';
            }

            // Info about usage
            $details['📊 Usage Statistics'] = '';
            try {
                $formsCount = $field->formVersions->count();
                $details['Used In'] = $formsCount . ' form version(s)';

                if ($formsCount > 0) {
                    $formNames = $field->formVersions->pluck('form_title')->unique()->take(3)->join(', ');
                    if ($field->formVersions->count() > 3) {
                        $formNames .= ' and ' . ($field->formVersions->count() - 3) . ' more';
                    }
                    $details['Form Names'] = $formNames;
                }
            } catch (\Exception $e) {
                $details['Used In'] = 'Error calculating usage';
            }

            // Action info
            $details['🔄 Action'] = 'Will use this existing field for mapping';

            Log::debug("✅ Returning " . count($details) . " details for field ID {$field->id}");
            return $details;
        } catch (\Exception $e) {
            Log::error("❌ Error in getFormFieldDetails: " . $e->getMessage());
            Log::debug("🔍 Stack trace: " . $e->getTraceAsString());

            return [
                'Error ⚠️' => $e->getMessage(),
                'Stack Trace 🔍' => substr($e->getTraceAsString(), 0, 200) . '...',
                'Suggestion 💡' => 'Please report this error to the development team',
                'Debug Info 🐛' => 'Field ID: ' . $fieldId . ' | Type: ' . gettype($fieldId)
            ];
        }
    }

    /**
     * Generate a simplified JSON preview of the imported form
     *
     * @param array $parsedSchema Parsed schema data
     * @param array $formData Form data including field mappings
     * @return string JSON string
     */
    public function getImportPreviewJson(array $parsedSchema, array $formData = []): string
    {
        // If no schema or invalid schema, show message
        if (empty($parsedSchema)) {
            return json_encode(['error' => 'No schema loaded'], JSON_PRETTY_PRINT);
        }

        // Ensure formData is an array
        $formData = $formData ?? [];

        $schemaParser = new SchemaParser();

        // Gather basic form info
        $formId = $formData['form_id'] ?? $parsedSchema['form_id'] ?? null;
        $formTitle = $formData['form_title'] ?? $parsedSchema['title'] ?? null;
        $ministryId = $formData['ministry_id'] ?? null;

        // Gather mapped fields
        $fields = [];
        $importFields = [];
        if (isset($parsedSchema['data']) && isset($parsedSchema['data']['elements']) && is_array($parsedSchema['data']['elements'])) {
            $importFields = $schemaParser->extractFieldsFromSchema($parsedSchema['data']['elements']);
        } elseif (isset($parsedSchema['fields']) && is_array($parsedSchema['fields'])) {
            $importFields = $schemaParser->extractFieldsFromSchema($parsedSchema['fields']);
        }

        // Ensure we have valid fields
        $importFields = is_array($importFields) ? $importFields : [];

        foreach ($importFields as $index => $importField) {
            $fieldId = $importField['token'] ?? $importField['id'] ?? md5($importField['name'] ?? "field_$index");
            $mappingKey = "field_mapping_{$fieldId}";
            $mapping = $formData[$mappingKey] ?? 'new';

            // If mapped to existing, show a summary of the mapping
            if ($mapping !== 'new') {
                // Try to get the existing field
                $existing = FormField::find($mapping);
                if ($existing) {
                    $fields[] = [
                        'id' => $fieldId,
                        'name' => $importField['name'] ?? '',
                        'label' => $importField['label'] ?? '',
                        'type' => $importField['type'] ?? $importField['elementType'] ?? '',
                        'mapping' => [
                            'type' => 'existing',
                            'field_id' => $existing->id,
                            'field_name' => $existing->name,
                            'field_label' => $existing->label,
                            'field_type' => $existing->dataType->name ?? 'unknown',
                        ],
                    ];
                }
            } else {
                // Show what will be created
                $fields[] = [
                    'id' => $fieldId,
                    'name' => $importField['name'] ?? '',
                    'label' => $importField['label'] ?? '',
                    'type' => $importField['type'] ?? $importField['elementType'] ?? '',
                    'mapping' => [
                        'type' => 'new',
                        'importDetails' => [
                            'name' => $importField['name'] ?? '',
                            'label' => $importField['label'] ?? '',
                            'type' => $schemaParser->mapFieldType($importField['type'] ?? $importField['elementType'] ?? ''),
                        ],
                    ],
                ];
            }
        }

        // Build preview JSON
        $preview = [
            'form_id' => $formId,
            'form_title' => $formTitle,
            'ministry_id' => $ministryId,
            'fields' => $fields,
        ];

        return json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
