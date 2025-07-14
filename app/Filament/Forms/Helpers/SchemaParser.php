<?php

namespace App\Filament\Forms\Helpers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;


class SchemaParser
{
    protected ?array $parsedSchema = null;

    /**
     * Parse schema and return summary information (for UI, synchronous)
     */
    public static function parseSchema($content): ?array
    {
        return self::parseSchemaContent($content);
    }

    /**
     * Parse schema and return summary information (for queue/job, or UI)
     * This is the original parseSchema logic, moved here for use by jobs and UI.
     */
    public static function parseSchemaContent($content): ?array
    {
        try {
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            // Count fields recursively
            $fieldCount = 0;
            $containerCount = 0;

            // Recursive function to count fields and containers
            $countElements = function ($elements) use (&$fieldCount, &$containerCount, &$countElements) {
                foreach ($elements as $element) {
                    // Handle new format with elementType
                    if (isset($element['elementType']) && $element['elementType'] === 'ContainerFormElements') {
                        $containerCount++;

                        if (isset($element['elements'])) {
                            $countElements($element['elements']);
                        }
                    }
                    // Handle old format with type=container
                    elseif (isset($element['type']) && $element['type'] === 'container') {
                        $containerCount++;

                        if (isset($element['children'])) {
                            $countElements($element['children']);
                        }
                    }
                    // Count any other element as a field
                    else {
                        $fieldCount++;
                    }
                }
            };

            // Format 1: formversion structure
            if (isset($data['formversion'])) {
                $formversion = $data['formversion'];
                if (isset($formversion['elements'])) {
                    $countElements($formversion['elements']);
                }
                return [
                    'form_id' => $formversion['id'] ?? null,
                    'title' => $formversion['name'] ?? null,
                    'field_count' => $fieldCount,
                    'container_count' => $containerCount,
                    'format' => 'formversion',
                    'version' => $formversion['version'] ?? null,
                    'status' => $formversion['status'] ?? null,
                    'data_sources_count' => isset($formversion['dataSources']) ? count($formversion['dataSources']) : 0,
                    'scripts_count' => isset($formversion['scripts']) ? count($formversion['scripts']) : 0,
                ];
            }
            // Format 2: data structure
            elseif (isset($data['data']) && isset($data['data']['elements'])) {
                $countElements($data['data']['elements']);
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => $fieldCount,
                    'container_count' => $containerCount,
                    'format' => 'adze-template',
                    'data_sources_count' => isset($data['data']['dataSources']) ? count($data['data']['dataSources']) : 0,
                    'javascript_sections_count' => isset($data['data']['javascript']) ? count($data['data']['javascript']) : 0,
                ];
            }
            // Format 3: direct elements structure
            elseif (isset($data['elements'])) {
                $countElements($data['elements']);
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => $fieldCount,
                    'container_count' => $containerCount,
                    'format' => 'direct-elements',
                    'data_sources_count' => isset($data['dataSources']) ? count($data['dataSources']) : 0,
                    'javascript_sections_count' => isset($data['javascript']) ? count($data['javascript']) : 0,
                ];
            }
            // Format 4: fields structure
            elseif (isset($data['fields'])) {
                $countElements($data['fields']);
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => $fieldCount,
                    'container_count' => $containerCount,
                    'format' => 'legacy',
                    'data_sources_count' => isset($data['dataSources']) ? count($data['dataSources']) : 0,
                    'javascript_sections_count' => isset($data['javascript']) ? count($data['javascript']) : 0,
                ];
            } else {
                // Unknown format
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => 0,
                    'container_count' => 0,
                    'format' => 'unknown',
                    'data_sources_count' => 0,
                    'javascript_sections_count' => 0,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Import parsing error: ' . $e->getMessage());
            return null;
        }
    }
}
