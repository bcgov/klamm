<?php

namespace App\Jobs;

use App\Filament\Forms\Resources\FormSchemaImporterResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FormSchemaImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $jobId;

    // Memory management settings
    protected $chunkSize = 50; // Process this many elements at a time
    protected $memoryLimit = 100; // MB

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $jobId)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;

        // Log job creation for debugging
        Log::info("FormSchemaImportJob created", [
            'job_id' => $this->jobId,
            'file_path' => $this->filePath,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Log the start of job execution
        Log::info("🚀 FormSchemaImportJob started execution", [
            'job_id' => $this->jobId,
            'file_path' => $this->filePath,
            'memory_limit' => $this->memoryLimit . 'MB',
            'chunk_size' => $this->chunkSize,
            'queue' => $this->queue ?? 'default',
            'job_exists' => file_exists($this->filePath) ? 'yes' : 'no'
        ]);

        // Mark the job as started in cache
        Cache::put("schema_import_status_{$this->jobId}", [
            'status' => 'processing',
            'message' => 'Starting schema import...',
            'progress' => 0,
            'started_at' => now()->toIso8601String()
        ], now()->addHours(1));

        try {
            // Check if file exists
            if (!file_exists($this->filePath)) {
                Log::error("FormSchemaImportJob failed: Temporary file not found", ['path' => $this->filePath]);
                Cache::put("schema_import_status_{$this->jobId}", [
                    'status' => 'error',
                    'message' => 'Temporary schema file not found. Please try uploading again.',
                ], now()->addMinutes(30));
                return;
            }

            // Get content from the file
            $content = file_get_contents($this->filePath);
            if ($content === false) {
                throw new \Exception("Could not read file contents from {$this->filePath}");
            }

            // Set higher memory limit for initial parsing
            ini_set('memory_limit', ($this->memoryLimit * 2) . 'M');

            // Initial parsing only - we'll chunk the processing later
            $schemaParser = new \App\Filament\Forms\Helpers\SchemaParser();
            try {
                // Start with basic parsing
                Log::info("Starting initial schema parsing", ['job_id' => $this->jobId]);

                // Store processing status
                Cache::put("schema_import_status_{$this->jobId}", [
                    'status' => 'processing',
                    'message' => 'Schema parsing in progress...',
                    'progress' => 0,
                ], now()->addHours(1));

                // Start with basic structure parsing, but don't process elements yet
                $parsedSchema = $schemaParser->parseSchema($content);

                // Check if parsedSchema is valid
                if (!$parsedSchema || !is_array($parsedSchema)) {
                    throw new \Exception("Schema parsing failed - invalid schema structure");
                }

                // Log the basic parsed schema structure
                Log::info("Basic schema structure parsed", [
                    'job_id' => $this->jobId,
                    'has_data' => isset($parsedSchema['data']),
                    'has_elements' => isset($parsedSchema['data']) && isset($parsedSchema['data']['elements']),
                    'has_fields' => isset($parsedSchema['fields']),
                    'form_id' => $parsedSchema['form_id'] ?? 'not set'
                ]);

                // Create a temporary representation of the schema structure
                $schemaStructure = [
                    'type' => isset($parsedSchema['data']) && isset($parsedSchema['data']['elements']) ? 'adze-template' : 'legacy',
                    'form_id' => $parsedSchema['form_id'] ?? null,
                    'title' => $parsedSchema['title'] ?? null,
                    'metadata' => $parsedSchema['metadata'] ?? []
                ];

                // Store just the structure initially, without the heavy elements
                Cache::put("schema_structure_{$this->jobId}", $schemaStructure, now()->addHours(1));

                // Now process the elements in chunks to prevent memory issues
                $elements = [];
                $fields = [];
                $containers = 0;

                if (isset($parsedSchema['data']) && isset($parsedSchema['data']['elements'])) {
                    // Process elements in chunks
                    $elements = $this->processElementsInChunks($parsedSchema['data']['elements'], $schemaParser);
                    // Count containers
                    $containers = $this->countContainers($elements);
                    // Extract fields
                    $fields = $schemaParser->extractFieldsFromSchema($elements);
                } elseif (isset($parsedSchema['fields'])) {
                    // Process fields in chunks
                    $elements = $this->processElementsInChunks($parsedSchema['fields'], $schemaParser);
                    // Count containers
                    $containers = $this->countContainers($elements);
                    // Extract fields
                    $fields = $schemaParser->extractFieldsFromSchema($elements);
                }

                $summary = [
                    'field_count' => count($fields),
                    'container_count' => $containers,
                    'format' => $schemaStructure['type']
                ];

                // Reconstruct the final schema with chunked processing results
                if ($schemaStructure['type'] === 'adze-template') {
                    // Use the chunked processed elements
                    $parsedSchema['data']['elements'] = $elements;
                } else {
                    // Legacy format
                    $parsedSchema['fields'] = $elements;
                }

                // Store processed results in multiple cache entries to prevent memory issues

                // 1. Store the basic schema structure and metadata
                Cache::put("schema_structure_{$this->jobId}", $schemaStructure, now()->addHours(1));

                // 2. Store field data separately
                Cache::put("schema_fields_{$this->jobId}", $fields, now()->addHours(1));

                // 3. Store elements in chunks if they're too large
                $elementsSize = strlen(serialize($elements)) / 1024 / 1024; // Size in MB
                Log::info("Schema elements size: {$elementsSize}MB", ['job_id' => $this->jobId]);

                if ($elementsSize > 5) { // If larger than 5MB, store in chunks
                    $elementChunks = array_chunk($elements, ceil(count($elements) / ceil($elementsSize / 5)));
                    foreach ($elementChunks as $index => $chunk) {
                        Log::debug("Storing schema chunk {$index}", [
                            'job_id' => $this->jobId,
                            'chunk_size' => count($chunk),
                            'memory_used' => memory_get_usage(true) / 1024 / 1024 . ' MB'
                        ]);
                        Cache::put("schema_elements_chunk_{$this->jobId}_{$index}", $chunk, now()->addHours(1));
                    }
                    Cache::put("schema_elements_chunks_{$this->jobId}", count($elementChunks), now()->addHours(1));
                    Log::info("Stored schema elements in " . count($elementChunks) . " chunks", ['job_id' => $this->jobId]);
                } else {
                    // Small enough to store directly
                    Cache::put("schema_elements_{$this->jobId}", $elements, now()->addHours(1));
                    Log::info("Stored schema elements directly (size: {$elementsSize}MB)", ['job_id' => $this->jobId]);
                }

                // Create a simplified version of the schema for the final status
                $minimalSchema = [
                    'form_id' => $parsedSchema['form_id'] ?? null,
                    'title' => $parsedSchema['title'] ?? null,
                    'metadata' => $parsedSchema['metadata'] ?? []
                ];

                if ($schemaStructure['type'] === 'adze-template') {
                    $minimalSchema['data'] = ['elements' => []]; // Just the structure, elements are stored separately
                } else {
                    $minimalSchema['fields'] = []; // Just the structure, fields are stored separately
                }

                // 4. Finally, store success status with summary and minimal schema data
                $finalStatus = [
                    'status' => 'success',
                    'message' => 'Schema parsed successfully.',
                    'summary' => $summary,
                    'schema' => $minimalSchema, // Include just the minimal schema structure
                    'raw_content' => $content, // Store the raw content for reference
                    'chunked' => $elementsSize > 5, // Flag if the data is stored in chunks
                    'completed_at' => now()->toIso8601String()
                ];

                // Store the status in cache
                $cacheResult = Cache::put("schema_import_status_{$this->jobId}", $finalStatus, now()->addHours(1));

                // Double-check that the cache was written successfully
                Log::info("📦 Schema import cache storage result", [
                    'job_id' => $this->jobId,
                    'cache_key' => "schema_import_status_{$this->jobId}",
                    'cache_write_success' => $cacheResult,
                    'schema_size' => strlen(json_encode($minimalSchema)) / 1024 / 1024 . ' MB',
                    'content_size' => strlen($content) / 1024 / 1024 . ' MB',
                    'status' => 'success'
                ]);

                // Verify the cache was stored by reading it back immediately
                $cachedStatus = Cache::get("schema_import_status_{$this->jobId}");
                Log::info("🔍 Verifying schema import cache", [
                    'job_id' => $this->jobId,
                    'cache_read_success' => $cachedStatus ? 'true' : 'false',
                    'cached_status' => $cachedStatus ? $cachedStatus['status'] : 'not found',
                    'has_schema' => $cachedStatus && isset($cachedStatus['schema']),
                    'has_content' => $cachedStatus && isset($cachedStatus['raw_content']) && !empty($cachedStatus['raw_content'])
                ]);

                // Log success
                Log::info("Schema import job completed successfully", [
                    'job_id' => $this->jobId,
                    'field_count' => $summary['field_count'],
                    'container_count' => $summary['container_count'],
                    'format' => $summary['format']
                ]);
            } catch (\Exception $e) {
                throw new \Exception("Error parsing schema: " . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('FormSchemaImportJob failed: ' . $e->getMessage(), [
                'job_id' => $this->jobId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            Cache::put("schema_import_status_{$this->jobId}", [
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage(),
            ], now()->addMinutes(30));
        } finally {
            // Clean up the temp file
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        }
    }

    /**
     * Process elements in chunks to prevent memory exhaustion
     *
     * @param array $elements The elements to process
     * @param \App\Filament\Forms\Helpers\SchemaParser $schemaParser The schema parser
     * @return array The processed elements
     */
    protected function processElementsInChunks(array $elements, \App\Filament\Forms\Helpers\SchemaParser $schemaParser): array
    {
        $result = [];
        $totalElements = count($elements);
        $chunks = array_chunk($elements, $this->chunkSize);
        $chunkCount = count($chunks);

        Log::info("Processing schema in {$chunkCount} chunks", [
            'total_elements' => $totalElements,
            'chunk_size' => $this->chunkSize,
            'job_id' => $this->jobId
        ]);

        foreach ($chunks as $index => $chunk) {
            // Report progress
            $progress = (int)(($index + 1) / $chunkCount * 100);
            Cache::put("schema_import_status_{$this->jobId}", [
                'status' => 'processing',
                'message' => "Processing schema ({$progress}% complete)...",
                'progress' => $progress,
            ], now()->addHours(1));

            // Process this chunk
            foreach ($chunk as $element) {
                if (isset($element['elements'])) {
                    // Process nested elements recursively, but in controlled chunks
                    $element['elements'] = $this->processElementsInChunks($element['elements'], $schemaParser);
                } elseif (isset($element['children'])) {
                    // Process nested children recursively, but in controlled chunks
                    $element['children'] = $this->processElementsInChunks($element['children'], $schemaParser);
                }

                $result[] = $element;
            }

            // Free memory after each chunk
            gc_collect_cycles();

            // Check memory usage and adjust chunk size if needed
            $memoryUsage = memory_get_usage(true) / 1024 / 1024; // Convert to MB
            if ($memoryUsage > $this->memoryLimit * 0.8) {
                // We're approaching the limit, reduce chunk size for next iterations
                $this->chunkSize = max(5, (int)($this->chunkSize * 0.8));
                Log::warning("High memory usage detected, reducing chunk size to {$this->chunkSize}", [
                    'memory_usage_mb' => $memoryUsage,
                    'job_id' => $this->jobId
                ]);
            }
        }

        return $result;
    }

    /**
     * Count container elements in a schema
     *
     * @param array $elements The elements to count containers in
     * @return int The number of containers found
     */
    protected function countContainers(array $elements): int
    {
        $count = 0;

        foreach ($elements as $element) {
            // Check for container type in new format
            if (isset($element['elementType']) && $element['elementType'] === 'ContainerFormElements') {
                $count++;
                // Recursively count containers in child elements
                if (isset($element['elements'])) {
                    $count += $this->countContainers($element['elements']);
                }
            }
            // Check for container type in legacy format
            elseif (isset($element['type']) && $element['type'] === 'container') {
                $count++;
                // Recursively count containers in child elements
                if (isset($element['children'])) {
                    $count += $this->countContainers($element['children']);
                }
            }
        }

        return $count;
    }
}
