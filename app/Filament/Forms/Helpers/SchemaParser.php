<?php

namespace App\Filament\Forms\Helpers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SchemaParser
{
    protected ?array $parsedSchema = null;

    /**
     * Parse a schema from JSON content
     *
     * @param string $content JSON content of the schema
     * @return array|null The parsed schema or null if parsing failed
     * @throws \Exception If the JSON is invalid
     */
    public function parseSchema(string $content): ?array
    {
        if (empty($content)) {
            return null;
        }

        try {
            $json = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('The schema is not valid JSON: ' . json_last_error_msg());
            }

            $this->parsedSchema = $json;

            // Determine schema format and log it
            $format = 'legacy';
            if (isset($json['data']) && isset($json['data']['elements'])) {
                $format = 'adze-template';
                Log::info("Detected Adze-template format schema");
            } elseif (isset($json['fields'])) {
                Log::info("Detected legacy format schema");
            } else {
                Log::warning("Unknown schema format detected");
            }

            return $this->parsedSchema;
        } catch (\Exception $e) {
            Log::error("Error parsing schema: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract fields from the schema for mapping
     *
     * @param array|null $parsedSchema The parsed schema array
     * @return array Associative array with field mappings and select options
     */
    public function extractFieldMappings(?array $parsedSchema): array
    {
        $fieldMappings = [];
        $selectOptions = [];

        // Check if we have a valid parsed schema
        if ($parsedSchema === null) {
            Log::warning('Attempted to extract field mappings from a null schema');
            return ['mappings' => $fieldMappings, 'selectOptions' => $selectOptions];
        }

        try {
            // Handle the new format that has a data.elements structure
            if (isset($parsedSchema['data']['elements']) && is_array($parsedSchema['data']['elements'])) {
                $this->extractFieldMappingsRecursively($parsedSchema['data']['elements'], $fieldMappings, $selectOptions);
            }
            // Handle older format with fields directly
            elseif (isset($parsedSchema['fields']) && is_array($parsedSchema['fields'])) {
                $this->extractFieldMappingsRecursively($parsedSchema['fields'], $fieldMappings, $selectOptions);
            } else {
                Log::warning('Schema format does not contain expected field structure', [
                    'has_data' => isset($parsedSchema['data']),
                    'has_fields' => isset($parsedSchema['fields'])
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error extracting field mappings: ' . $e->getMessage(), [
                'exception' => $e,
                'schema_keys' => array_keys($parsedSchema)
            ]);
        }

        return ['mappings' => $fieldMappings, 'selectOptions' => $selectOptions];
    }

    /**
     * Extract fields recursively from the schema
     *
     * @param array $elements The elements to process
     * @param array &$fieldMappings Reference to field mappings array to populate
     * @param array &$selectOptions Reference to select options array to populate
     */
    protected function extractFieldMappingsRecursively(array $elements, array &$fieldMappings, array &$selectOptions): void
    {
        foreach ($elements as $element) {
            // If this is a container with child elements, process recursively
            if (isset($element['elementType']) && $element['elementType'] === 'ContainerFormElements' && isset($element['elements'])) {
                $this->extractFieldMappingsRecursively($element['elements'], $fieldMappings, $selectOptions);
            }
            // If this is a field (not a container), add to mappings
            elseif (isset($element['elementType']) && $element['elementType'] !== 'ContainerFormElements') {
                $id = $element['token'] ?? md5(json_encode($element));
                $fieldMappings[$id] = 'new';

                // For select fields, extract options if available
                if (
                    isset($element['dataFormat']) && in_array($element['dataFormat'], ['dropdown', 'radio', 'checkbox', 'select'])
                    && isset($element['options'])
                ) {
                    $selectOptions[$id] = $element['options'];
                }
            }
        }
    }

    /**
     * Recursively extract all fields (not containers) from the parsed schema JSON.
     *
     * @param array $elements Elements to process
     * @param array|null $elements Elements to extract fields from
     * @param array &$result Reference to result array to populate
     * @return array Array of extracted fields
     */
    public function extractFieldsFromSchema(?array $elements, array &$result = []): array
    {
        // Return empty result if elements is null
        if ($elements === null) {
            Log::warning('Attempted to extract fields from a null schema elements array');
            return $result;
        }

        foreach ($elements as $element) {
            // Handle new format with elementType
            if (isset($element['elementType']) && $element['elementType'] === 'ContainerFormElements' && isset($element['elements'])) {
                $this->extractFieldsFromSchema($element['elements'], $result);
            }
            // Handle old format with type=container
            elseif (isset($element['type']) && $element['type'] === 'container' && isset($element['children'])) {
                $this->extractFieldsFromSchema($element['children'], $result);
            }
            // Handle new format actual field
            elseif (isset($element['elementType']) && $element['elementType'] !== 'ContainerFormElements') {
                $result[] = $element;
            }
            // Handle old format actual field
            elseif (isset($element['type']) && $element['type'] !== 'container') {
                $result[] = $element;
            }
        }
        return $result;
    }

    /**
     * Map field types to system data types
     *
     * @param string $type The field type to map
     * @return string The mapped data type
     */
    public function mapFieldType(string $type): string
    {
        // Extract the core type from combined types like "ContainerFormElements (text)"
        $baseType = $type;
        if (preg_match('/\((.*?)\)/', $type, $matches)) {
            $baseType = trim($matches[1]);
        }

        // First check if we're dealing with an elementType
        if (strpos($type, 'ContainerFormElements') !== false) {
            return 'container';
        }

        // Handle adze-specific types
        if (strpos($type, 'InputFormElement') !== false) {
            if (strpos($type, 'text') !== false) return 'text';
            if (strpos($type, 'number') !== false) return 'number';
            if (strpos($type, 'date') !== false) return 'date';
            if (strpos($type, 'email') !== false) return 'email';
            if (strpos($type, 'tel') !== false) return 'tel';
            return 'text';
        }

        if (strpos($type, 'SelectFormElement') !== false) {
            return 'select';
        }

        if (strpos($type, 'CheckboxFormElement') !== false) {
            return 'checkbox';
        }

        if (strpos($type, 'RadioFormElement') !== false) {
            return 'radio';
        }

        if (strpos($type, 'TextareaFormElement') !== false) {
            return 'textarea';
        }

        // Legacy type mapping
        $mapping = [
            'text-input' => 'text',
            'text' => 'text',
            'dropdown' => 'select',
            'select' => 'select',
            'radio' => 'radio',
            'checkbox' => 'checkbox',
            'textarea' => 'textarea',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'datetime',
            'number' => 'number',
            'email' => 'email',
            'tel' => 'tel',
            'phone' => 'tel',
            'url' => 'url',
            'file' => 'file',
            'image' => 'image',
        ];

        if (isset($mapping[$baseType])) {
            return $mapping[$baseType];
        }

        // Default to text if no matching type is found
        return 'text';
    }
}
