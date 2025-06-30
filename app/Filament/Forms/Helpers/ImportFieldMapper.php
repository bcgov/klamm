<?php

namespace App\Filament\Forms\Helpers;

use App\Models\FormField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Import Field Mapper
 *
 * Handles field mapping operations including building mapping options,
 * extracting field details, and managing the field mapping interface.
 * This class centralizes all field mapping logic for better maintainability.
 */
class ImportFieldMapper
{
    private array $formFieldOptions = [];
    private array $fieldMappingOptions = [];
    private SchemaFormatter $schemaFormatter;

    public function __construct()
    {
        $this->schemaFormatter = new SchemaFormatter();
        $this->loadFormFieldOptions();
        $this->loadFieldMappingOptions();
    }

    /**
     * Load form field options grouped by data type
     *
     * @return void
     */
    private function loadFormFieldOptions(): void
    {
        $this->formFieldOptions = FormField::with('dataType')
            ->get()
            ->groupBy('data_type.name')
            ->map(function (Collection $fields) {
                return $fields->mapWithKeys(function ($field) {
                    return [$field->id => "{$field->label} ({$field->name})"];
                })->toArray();
            })
            ->toArray();
    }

    /**
     * Load cached field mapping options for all fields
     *
     * @return void
     */
    private function loadFieldMappingOptions(): void
    {
        $this->fieldMappingOptions = SchemaFormatter::getAllMappingOptions(true);
    }

    /**
     * Get form field options
     *
     * @return array Form field options grouped by type
     */
    public function getFormFieldOptions(): array
    {
        return $this->formFieldOptions;
    }

    /**
     * Get field mapping options
     *
     * @return array Field mapping options for select components
     */
    public function getFieldMappingOptions(): array
    {
        return $this->fieldMappingOptions;
    }

    /**
     * Build field mapping schema for a set of fields
     *
     * @param array $parsedSchema Parsed schema data
     * @param bool $showPreview Whether to show field previews
     * @return array Field mapping form components
     */
    public function buildFieldMappingSchema(array $parsedSchema, bool $showPreview = true): array
    {
        return $this->schemaFormatter->getFieldMappingSchemaWithPreview($parsedSchema, $showPreview);
    }

    /**
     * Get mapping options with field type filtering
     *
     * @param string $type Field type for filtering
     * @param string $label Field label for display
     * @param string $name Field name
     * @param bool $repeating Whether field is repeating
     * @return array Filtered mapping options
     */
    public function getMappingOptionsWithDetails(
        string $type,
        string $label,
        string $name,
        bool $repeating = false
    ): array {
        // Get base options from cached mapping options
        $options = $this->fieldMappingOptions;

        // Add type-specific filtering logic if needed
        $mappedType = $this->mapFieldType($type);

        // Filter options based on field type if we have type-specific fields
        if (isset($this->formFieldOptions[$mappedType])) {
            $typeSpecificOptions = collect($this->formFieldOptions[$mappedType])
                ->mapWithKeys(function ($label, $id) {
                    return [$id => $label];
                })
                ->toArray();

            // Merge with general options, prioritizing type-specific matches
            $options = array_merge($options, $typeSpecificOptions);
        }

        return $options;
    }

    /**
     * Map import field types to system data types
     *
     * @param string $type Import field type
     * @return string Mapped system data type
     */
    public function mapFieldType(string $type): string
    {
        $schemaParser = new SchemaParser();
        return $schemaParser->mapFieldType($type);
    }

    /**
     * Get detailed information about a form field
     *
     * @param int|string $fieldId Field ID to get details for
     * @return array|null Field details or null if not found
     */
    public function getFormFieldDetails($fieldId): ?array
    {
        if ($fieldId === 'new' || empty($fieldId)) {
            return null;
        }

        try {
            $field = FormField::with(['dataType', 'fieldOptions', 'fieldGroups', 'webStyles', 'pdfStyles'])
                ->find($fieldId);

            if (!$field) {
                return null;
            }

            return [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->label,
                'type' => $field->dataType->name ?? 'unknown',
                'help_text' => $field->help_text,
                'is_required' => $field->is_required,
                'is_repeating' => $field->is_repeating,
                'default_value' => $field->default_value,
                'validation_rules' => $field->validation_rules,
                'field_options' => $field->fieldOptions ? $field->fieldOptions->pluck('option_text', 'option_value')->toArray() : [],
                'overview_html' => $this->schemaFormatter->generateExistingFieldOverview($field),
            ];
        } catch (\Exception $e) {
            Log::error("Error fetching field details for field {$fieldId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate import field overview for display
     *
     * @param array $importField Import field data
     * @return string HTML overview of the field
     */
    public function generateImportFieldOverview(array $importField): string
    {
        return $this->schemaFormatter->generateImportFieldOverview($importField);
    }

    /**
     * Generate field overview for import field
     *
     * @param array $importField Import field data
     * @return string Formatted field overview HTML
     */
    public function generateFieldOverview(array $importField): string
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

        // Process validations - but limit size
        $validationsText = 'None';
        if (isset($importField['validations']) || isset($importField['validation'])) {
            $validations = isset($importField['validations']) ? $importField['validations'] : $importField['validation'];

            if (is_array($validations)) {
                // Memory optimization: limit validation text size
                $validationItems = [];
                $count = 0;
                foreach ($validations as $k => $v) {
                    if ($count < 5) { // Only show first 5 validations
                        $valueText = is_array($v) ? json_encode($v) : $v;
                        // Truncate long validation values
                        if (strlen($valueText) > 50) {
                            $valueText = substr($valueText, 0, 47) . '...';
                        }
                        $validationItems[] = "$k: $valueText";
                        $count++;
                    } else {
                        $validationItems[] = "... " . (count($validations) - 5) . " more";
                        break;
                    }
                }
                $validationsText = implode(', ', $validationItems);
            } else {
                $validationsText = (string) $validations;
                if (strlen($validationsText) > 100) {
                    $validationsText = substr($validationsText, 0, 97) . '...';
                }
            }
        }

        // Extract options for select-type fields - but limit size
        $fieldOptions = '';
        $optionsCount = 0;

        if (isset($importField['options'])) {
            $options = $importField['options'];
            $optionsCount = count($options);

            // Memory optimization: only show a limited number of options
            $showOptions = array_slice($options, 0, 5);
            $optionTexts = [];

            foreach ($showOptions as $opt) {
                if (is_array($opt)) {
                    $optionTexts[] = $opt['label'] ?? $opt['name'] ?? $opt['value'] ?? '(option)';
                } else {
                    $optionTexts[] = $opt;
                }
            }

            if ($optionsCount > 5) {
                $optionTexts[] = "... " . ($optionsCount - 5) . " more";
            }

            $fieldOptions = implode(', ', $optionTexts);
        } elseif (isset($importField['listItems']) && is_array($importField['listItems'])) {
            // Simplified list items presentation
            $listItems = $importField['listItems'];
            $optionsCount = count($listItems);

            $fieldOptions = '<ul class="list-disc pl-5">';
            foreach (array_slice($listItems, 0, 5) as $item) {
                $label = $item['text'] ?? $item['label'] ?? $item['name'] ?? '';
                $value = $item['value'] ?? '';
                $fieldOptions .= '<li><span class="font-medium">' . htmlspecialchars($label) . '</span>';
                if ($value !== '') {
                    $fieldOptions .= ' <span class="text-gray-500">(' . htmlspecialchars($value) . ')</span>';
                }
                $fieldOptions .= '</li>';
            }

            if ($optionsCount > 5) {
                $fieldOptions .= '<li><span class="text-gray-500">... ' . ($optionsCount - 5) . ' more options</span></li>';
            }

            $fieldOptions .= '</ul>';
        }

        // Build simplified field details array
        $fieldDetails = [
            '🔑 Basic Info' => [
                'Name' => $name,
                'Type' => $type,
                'Label' => $label,
                'Repeating' => $repeating ? 'Yes' : 'No',
            ],
            '📝 Content' => [
                'Help Text' => mb_strlen($helpText) > 100 ? mb_substr($helpText, 0, 97) . '...' : ($helpText ?: 'None'),
                'Description' => mb_strlen($description) > 100 ? mb_substr($description, 0, 97) . '...' : ($description ?: 'None'),
            ],
            '✓ Validation' => [
                'Rules' => $validationsText,
            ],
        ];

        // Add options section if applicable
        if ($fieldOptions) {
            $fieldDetails['🔽 Options'] = [
                'Options' => $fieldOptions,
                'Count' => $optionsCount,
            ];
        }

        // Add a limited number of additional properties
        $additional = [];
        $additionalCount = 0;
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
                'options',
                'listItems'
            ])) {
                // Only show a limited number of additional properties
                if ($additionalCount < 5) {
                    if (is_array($value)) {
                        $jsonValue = json_encode($value);
                        // Truncate long JSON strings
                        if (strlen($jsonValue) > 50) {
                            $additional[$key] = substr($jsonValue, 0, 47) . '...';
                        } else {
                            $additional[$key] = $jsonValue;
                        }
                    } elseif (!empty($value) || $value === '0' || $value === 0) {
                        $strValue = (string) $value;
                        if (strlen($strValue) > 50) {
                            $additional[$key] = substr($strValue, 0, 47) . '...';
                        } else {
                            $additional[$key] = $strValue;
                        }
                    }
                    $additionalCount++;
                }
            }
        }

        if (!empty($additional)) {
            $fieldDetails['⚙️ Additional Properties'] = $additional;
        }

        // Generate simplified HTML with fewer DOM elements
        $html = '<div class="space-y-4 p-2 overflow-auto max-h-64">';

        foreach ($fieldDetails as $section => $items) {
            $html .= '<div class="border rounded-md bg-gray-50">';
            $html .= '<div class="font-medium p-2 bg-gray-100 border-b">' . $section . '</div>';
            $html .= '<div class="divide-y divide-gray-200">';

            foreach ($items as $key => $value) {
                $html .= '<div class="flex p-2">';
                $html .= '<div class="w-1/3 font-medium text-gray-700">' . htmlspecialchars($key) . '</div>';

                if ($key === 'Options') {
                    $html .= '<div class="w-2/3">' . $value . '</div>'; // allow HTML for options
                } elseif (empty($value) && $value !== '0' && $value !== 0) {
                    $html .= '<div class="w-2/3 text-gray-500 italic">empty</div>';
                } else {
                    $html .= '<div class="w-2/3">' . htmlspecialchars($value) . '</div>';
                }
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Initialize field properties for form data
     *
     * @param array $fieldMappings Current field mappings
     * @param array $parsedSchema Parsed schema data
     * @return array Initialized field properties
     */
    public function initializeFieldProperties(array $fieldMappings, array $parsedSchema): array
    {
        $fieldProperties = [];

        if (empty($fieldMappings)) {
            Log::debug('No field mappings found for initialization');
            return $fieldProperties;
        }

        // Extract fields from schema for counting
        $schemaParser = new SchemaParser();
        $fields = [];

        if (isset($parsedSchema['data']) && isset($parsedSchema['data']['elements'])) {
            $fields = $schemaParser->extractFieldsFromSchema($parsedSchema['data']['elements']);
        } elseif (isset($parsedSchema['fields'])) {
            $fields = $schemaParser->extractFieldsFromSchema($parsedSchema['fields']);
        }

        // Initialize mapping properties for each field
        foreach ($fieldMappings as $fieldId => $mapping) {
            $fieldProperties["field_mapping_{$fieldId}"] = $mapping;
        }

        Log::debug('Field properties initialized', [
            'field_count' => count($fields),
            'mapping_count' => count($fieldMappings),
            'properties_count' => count($fieldProperties)
        ]);

        return $fieldProperties;
    }

    /**
     * Extract field mappings from component data
     *
     * @param array $data Component data array
     * @return array Extracted field mappings
     */
    public function extractFieldMappingsFromData(array $data): array
    {
        $fieldMappings = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'field_mapping_')) {
                $fieldId = str_replace('field_mapping_', '', $key);
                $fieldMappings[$fieldId] = $value;
            }
        }
        return $fieldMappings;
    }

    /**
     * Get mapping statistics for progress tracking
     *
     * @param array $fieldMappings Current field mappings
     * @return array Mapping statistics
     */
    public function getMappingStatistics(array $fieldMappings): array
    {
        $totalFields = count($fieldMappings);
        $mappedFields = 0;
        $newFields = 0;
        $skippedFields = 0;

        foreach ($fieldMappings as $mapping) {
            if ($mapping === 'skip') {
                $skippedFields++;
            } elseif ($mapping === 'new') {
                $newFields++;
                $mappedFields++;
            } elseif (!empty($mapping) && is_numeric($mapping)) {
                $mappedFields++;
            }
        }

        $completionPercentage = $totalFields > 0 ? round(($mappedFields / $totalFields) * 100) : 0;

        return [
            'total_fields' => $totalFields,
            'mapped_fields' => $mappedFields,
            'new_fields' => $newFields,
            'skipped_fields' => $skippedFields,
            'completion_percentage' => $completionPercentage,
        ];
    }

    /**
     * Validate field mappings for consistency
     *
     * @param array $fieldMappings Field mappings to validate
     * @param array $parsedSchema Parsed schema for reference
     * @return array Validation results
     */
    public function validateFieldMappings(array $fieldMappings, array $parsedSchema): array
    {
        $errors = [];
        $warnings = [];

        // Check for missing required mappings
        $schemaParser = new SchemaParser();
        $fields = [];

        if (isset($parsedSchema['data']) && isset($parsedSchema['data']['elements'])) {
            $fields = $schemaParser->extractFieldsFromSchema($parsedSchema['data']['elements']);
        } elseif (isset($parsedSchema['fields'])) {
            $fields = $schemaParser->extractFieldsFromSchema($parsedSchema['fields']);
        }

        foreach ($fields as $field) {
            $fieldId = $field['token'] ?? md5(json_encode($field));

            if (!isset($fieldMappings[$fieldId])) {
                $fieldName = $field['name'] ?? $field['label'] ?? 'Unknown';
                $warnings[] = "No mapping specified for field: {$fieldName}";
            }
        }

        // Check for invalid field ID references
        foreach ($fieldMappings as $fieldId => $mapping) {
            if (is_numeric($mapping)) {
                $field = FormField::find($mapping);
                if (!$field) {
                    $errors[] = "Referenced field ID {$mapping} does not exist";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Reload field options (useful after field changes)
     *
     * @return void
     */
    public function reloadFieldOptions(): void
    {
        $this->loadFormFieldOptions();
        $this->loadFieldMappingOptions();
    }
}
