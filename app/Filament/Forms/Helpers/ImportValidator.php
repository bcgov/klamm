<?php

namespace App\Filament\Forms\Helpers;

use App\Filament\Forms\Imports\FormSchemaImporter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Import Validator
 *
 * Handles validation logic for schema imports, including content validation,
 * field mapping validation, and import readiness checks.
 * This class centralizes all validation operations for consistency.
 */
class ImportValidator
{
    private SchemaParser $schemaParser;
    private ImportFieldMapper $fieldMapper;

    public function __construct()
    {
        $this->schemaParser = new SchemaParser();
        $this->fieldMapper = new ImportFieldMapper();
    }

    /**
     * Validate schema content before parsing
     *
     * @param string|null $content Schema content to validate
     * @return array Validation result with success status and messages
     */
    public function validateSchemaContent(?string $content): array
    {
        if (empty($content)) {
            return [
                'valid' => false,
                'error' => 'No schema content provided',
                'message' => 'Please upload or paste a schema first',
            ];
        }

        try {
            $json = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'valid' => false,
                    'error' => 'Invalid JSON format',
                    'message' => 'The schema is not valid JSON: ' . json_last_error_msg(),
                ];
            }

            // Check for expected schema structure
            $hasValidStructure = $this->validateSchemaStructure($json);
            if (!$hasValidStructure['valid']) {
                return $hasValidStructure;
            }

            return [
                'valid' => true,
                'message' => 'Schema content is valid',
                'parsed_data' => $json,
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Parsing error',
                'message' => 'Error parsing schema: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate schema structure for expected format
     *
     * @param array $schema Parsed schema array
     * @return array Validation result
     */
    public function validateSchemaStructure(array $schema): array
    {
        // Check for known formats
        $hasAdzeFormat = isset($schema['data']) && isset($schema['data']['elements']);
        $hasLegacyFormat = isset($schema['fields']);

        if (!$hasAdzeFormat && !$hasLegacyFormat) {
            return [
                'valid' => false,
                'error' => 'Unknown schema format',
                'message' => 'Schema must contain either "data.elements" (Adze format) or "fields" (legacy format)',
            ];
        }

        // Validate elements/fields are arrays
        if ($hasAdzeFormat && !is_array($schema['data']['elements'])) {
            return [
                'valid' => false,
                'error' => 'Invalid elements structure',
                'message' => 'Schema data.elements must be an array',
            ];
        }

        if ($hasLegacyFormat && !is_array($schema['fields'])) {
            return [
                'valid' => false,
                'error' => 'Invalid fields structure',
                'message' => 'Schema fields must be an array',
            ];
        }

        // Check for empty schemas
        $elementCount = 0;
        if ($hasAdzeFormat) {
            $elementCount = count($schema['data']['elements']);
        } elseif ($hasLegacyFormat) {
            $elementCount = count($schema['fields']);
        }

        if ($elementCount === 0) {
            return [
                'valid' => false,
                'error' => 'Empty schema',
                'message' => 'Schema contains no fields or elements to import',
            ];
        }

        return [
            'valid' => true,
            'format' => $hasAdzeFormat ? 'adze-template' : 'legacy',
            'element_count' => $elementCount,
        ];
    }

    /**
     * Validate import readiness
     *
     * @param array $data Current form data
     * @param array|null $parsedSchema Parsed schema
     * @param array $fieldMappings Current field mappings
     * @return array Validation result
     */
    public function validateImportReadiness(array $data, ?array $parsedSchema, array $fieldMappings): array
    {
        $errors = [];
        $warnings = [];

        // Check required fields
        if (empty($data['form'])) {
            $errors[] = 'No target form selected';
        }

        if (!$parsedSchema) {
            $errors[] = 'No schema has been parsed';
        }

        if (empty($fieldMappings)) {
            $errors[] = 'No field mappings configured';
        }

        // Validate field mappings
        if ($parsedSchema && !empty($fieldMappings)) {
            $mappingValidation = $this->fieldMapper->validateFieldMappings($fieldMappings, $parsedSchema);
            $errors = array_merge($errors, $mappingValidation['errors']);
            $warnings = array_merge($warnings, $mappingValidation['warnings']);
        }

        // Check mapping completeness
        if (!empty($fieldMappings)) {
            $stats = $this->fieldMapper->getMappingStatistics($fieldMappings);
            if ($stats['mapped_fields'] === 0) {
                $errors[] = 'No fields are mapped for import';
            } elseif ($stats['completion_percentage'] < 50) {
                $warnings[] = "Only {$stats['completion_percentage']}% of fields are mapped";
            }
        }

        return [
            'ready' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'can_proceed' => empty($errors), // Same as ready for now
        ];
    }

    /**
     * Validate before running import
     *
     * @param array $data Form data
     * @return array Validation result
     */
    public function validateBeforeImport(array $data): array
    {
        $errors = [];

        // Check for schema content
        if (empty($data['schema_content'])) {
            $errors[] = 'No schema content available for import';
        }

        // Check for confirmation
        if (empty($data['confirm_import'])) {
            $errors[] = 'Import confirmation is required';
        }

        // Extract and validate field mappings
        $fieldMappings = $this->fieldMapper->extractFieldMappingsFromData($data);
        if (empty($fieldMappings)) {
            $errors[] = 'No field mappings found';
        } else {
            // Check for at least one non-skip mapping
            $hasValidMappings = false;
            foreach ($fieldMappings as $mapping) {
                if ($mapping !== 'skip' && !empty($mapping)) {
                    $hasValidMappings = true;
                    break;
                }
            }
            if (!$hasValidMappings) {
                $errors[] = 'No valid field mappings found (all fields are skipped)';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'field_mappings' => $fieldMappings ?? [],
        ];
    }

    /**
     * Send validation error notification
     *
     * @param array $validationResult Validation result from any validation method
     * @return void
     */
    public function sendValidationErrorNotification(array $validationResult): void
    {
        if (!empty($validationResult['errors'])) {
            $errorMessage = implode(', ', $validationResult['errors']);

            Notification::make()
                ->title('Validation Error')
                ->body($errorMessage)
                ->danger()
                ->send();
        }
    }

    /**
     * Send validation warning notification
     *
     * @param array $validationResult Validation result with warnings
     * @return void
     */
    public function sendValidationWarningNotification(array $validationResult): void
    {
        if (!empty($validationResult['warnings'])) {
            $warningMessage = implode(', ', $validationResult['warnings']);

            Notification::make()
                ->title('Validation Warnings')
                ->body($warningMessage)
                ->warning()
                ->send();
        }
    }

    /**
     * Validate file upload
     *
     * @param \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null $file Uploaded file
     * @return array Validation result
     */
    public function validateFileUpload($file): array
    {
        if (!$file) {
            return [
                'valid' => false,
                'error' => 'No file uploaded',
                'message' => 'Please select a file to upload',
            ];
        }

        // Check file size (5MB limit)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return [
                'valid' => false,
                'error' => 'File too large',
                'message' => 'File size must be less than 5MB',
            ];
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'json') {
            return [
                'valid' => false,
                'error' => 'Invalid file type',
                'message' => 'Only JSON files are allowed',
            ];
        }

        // Try to read file content
        try {
            $content = file_get_contents($file->getRealPath());
            if ($content === false) {
                return [
                    'valid' => false,
                    'error' => 'Cannot read file',
                    'message' => 'Unable to read the uploaded file',
                ];
            }

            // Validate the content
            return $this->validateSchemaContent($content);
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'File read error',
                'message' => 'Error reading file: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get validation summary for UI display
     *
     * @param array $data Current form data
     * @param array|null $parsedSchema Parsed schema
     * @param array $fieldMappings Field mappings
     * @return array Summary for display
     */
    public function getValidationSummary(array $data, ?array $parsedSchema, array $fieldMappings): array
    {
        $readiness = $this->validateImportReadiness($data, $parsedSchema, $fieldMappings);
        $stats = !empty($fieldMappings) ? $this->fieldMapper->getMappingStatistics($fieldMappings) : null;

        return [
            'is_ready' => $readiness['ready'],
            'error_count' => count($readiness['errors']),
            'warning_count' => count($readiness['warnings']),
            'completion_percentage' => $stats['completion_percentage'] ?? 0,
            'mapped_fields' => $stats['mapped_fields'] ?? 0,
            'total_fields' => $stats['total_fields'] ?? 0,
            'errors' => $readiness['errors'],
            'warnings' => $readiness['warnings'],
        ];
    }
}
