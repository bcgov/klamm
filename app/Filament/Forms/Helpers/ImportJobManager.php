<?php

namespace App\Filament\Forms\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Import Job Manager
 *
 * Handles background job management for schema imports, including
 * job status tracking, polling, and chunked schema processing.
 * This class centralizes all background job related operations.
 */
class ImportJobManager
{
    /**
     * Check the status of a schema import job
     *
     * @param string|null $jobId The job ID to check
     * @return array|null Job status information or null if job not found
     */
    public function checkJobStatus(?string $jobId): ?array
    {
        if (!$jobId) {
            return null;
        }

        try {
            // Check if job is still running
            $status = Cache::get("schema_import_job_{$jobId}");

            if (!$status) {
                Log::warning("No status found for job {$jobId}");
                return null;
            }

            // Return normalized status format
            return [
                'status' => $status['status'] ?? 'unknown',
                'message' => $status['message'] ?? '',
                'progress' => $status['progress'] ?? 0,
                'job_id' => $jobId,
                'completed_at' => $status['completed_at'] ?? null,
                'error' => $status['error'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error("Error checking job status for {$jobId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Start polling a background job
     *
     * @param string $jobId Job ID to start polling
     * @return array Initial job status
     */
    public function startPolling(string $jobId): array
    {
        $status = $this->checkJobStatus($jobId);

        if (!$status) {
            return [
                'status' => 'failed',
                'message' => 'Job not found',
                'job_id' => $jobId,
            ];
        }

        Log::debug("Started polling job {$jobId}", $status);
        return $status;
    }

    /**
     * Poll for job status updates
     *
     * @param string $jobId Job ID to poll
     * @return array Current job status
     */
    public function pollStatus(string $jobId): array
    {
        $status = $this->checkJobStatus($jobId);

        if (!$status) {
            return [
                'status' => 'lost',
                'message' => 'Job status lost or expired',
                'job_id' => $jobId,
            ];
        }

        // Log only significant status changes to avoid spam
        if (in_array($status['status'], ['completed', 'failed', 'success'])) {
            Log::info("Job {$jobId} status: {$status['status']}", [
                'message' => $status['message'],
                'progress' => $status['progress'] ?? 0
            ]);
        }

        return $status;
    }

    /**
     * Check if a job should continue polling
     *
     * @param array $status Current job status
     * @return bool True if polling should continue
     */
    public function shouldContinuePolling(array $status): bool
    {
        $finalStatuses = ['completed', 'failed', 'success', 'cancelled', 'lost'];
        return !in_array($status['status'] ?? '', $finalStatuses);
    }

    /**
     * Cancel a running job
     *
     * @param string $jobId Job ID to cancel
     * @return bool True if job was cancelled successfully
     */
    public function cancelJob(string $jobId): bool
    {
        try {
            // Update job status to cancelled
            $statusKey = "schema_import_job_{$jobId}";
            $currentStatus = Cache::get($statusKey);

            if ($currentStatus) {
                $currentStatus['status'] = 'cancelled';
                $currentStatus['message'] = 'Job cancelled by user';
                $currentStatus['completed_at'] = now()->toISOString();

                Cache::put($statusKey, $currentStatus, now()->addHours(24));

                Log::info("Job {$jobId} cancelled by user");
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Error cancelling job {$jobId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up job status cache
     *
     * @param string $jobId Job ID to clean up
     * @return void
     */
    public function cleanupJob(string $jobId): void
    {
        try {
            $statusKey = "schema_import_job_{$jobId}";
            Cache::forget($statusKey);

            // Also clean up any chunked data
            $this->cleanupChunkedData($jobId);

            Log::debug("Cleaned up job {$jobId}");
        } catch (\Exception $e) {
            Log::warning("Error cleaning up job {$jobId}: " . $e->getMessage());
        }
    }

    /**
     * Reassemble chunked schema data
     *
     * @param string $jobId Job ID for chunked data
     * @param array $baseSchema Base schema structure
     * @return array Complete reassembled schema
     */
    public function reassembleChunkedSchema(string $jobId, array $baseSchema): array
    {
        try {
            $chunkIndex = 0;
            $allElements = [];

            // Collect all chunks
            while (true) {
                $chunkKey = "schema_chunk_{$jobId}_{$chunkIndex}";
                $chunk = Cache::get($chunkKey);

                if (!$chunk) {
                    break; // No more chunks
                }

                if (isset($chunk['elements']) && is_array($chunk['elements'])) {
                    $allElements = array_merge($allElements, $chunk['elements']);
                }

                $chunkIndex++;
            }

            Log::debug("Reassembled schema from {$chunkIndex} chunks", [
                'job_id' => $jobId,
                'total_elements' => count($allElements)
            ]);

            // Rebuild the schema with all elements
            $reassembledSchema = $baseSchema;
            if (isset($baseSchema['data'])) {
                $reassembledSchema['data']['elements'] = $allElements;
            } else {
                $reassembledSchema['fields'] = $allElements;
            }

            return $reassembledSchema;
        } catch (\Exception $e) {
            Log::error("Error reassembling chunked schema for job {$jobId}: " . $e->getMessage());
            return $baseSchema; // Return base schema as fallback
        }
    }

    /**
     * Clean up chunked data for a job
     *
     * @param string $jobId Job ID to clean up chunks for
     * @return void
     */
    private function cleanupChunkedData(string $jobId): void
    {
        try {
            $chunkIndex = 0;

            // Remove all chunks for this job
            while ($chunkIndex < 1000) { // Safety limit
                $chunkKey = "schema_chunk_{$jobId}_{$chunkIndex}";

                if (!Cache::has($chunkKey)) {
                    break; // No more chunks
                }

                Cache::forget($chunkKey);
                $chunkIndex++;
            }

            Log::debug("Cleaned up {$chunkIndex} chunks for job {$jobId}");
        } catch (\Exception $e) {
            Log::warning("Error cleaning up chunks for job {$jobId}: " . $e->getMessage());
        }
    }

    /**
     * Get job status for UI display
     *
     * @param string|null $jobId Job ID to get status for
     * @return array UI-formatted job status
     */
    public function getJobStatusForUI(?string $jobId): array
    {
        if (!$jobId) {
            return [
                'status' => 'idle',
                'message' => 'No active job',
                'show_progress' => false,
                'progress' => 0,
            ];
        }

        $status = $this->checkJobStatus($jobId);

        if (!$status) {
            return [
                'status' => 'idle',
                'message' => 'Job status not found',
                'show_progress' => false,
                'progress' => 0,
            ];
        }

        return [
            'status' => $status['status'],
            'message' => $status['message'],
            'show_progress' => in_array($status['status'], ['processing', 'running', 'in_progress']),
            'progress' => $status['progress'] ?? 0,
            'is_complete' => in_array($status['status'], ['completed', 'success']),
            'is_failed' => $status['status'] === 'failed',
            'job_id' => $jobId,
        ];
    }

    /**
     * Create a new job tracking entry
     *
     * @param string $jobId Unique job identifier
     * @param array $initialData Initial job data
     * @return bool True if job was created successfully
     */
    public function createJob(string $jobId, array $initialData = []): bool
    {
        try {
            $jobData = array_merge([
                'status' => 'created',
                'message' => 'Job created',
                'progress' => 0,
                'created_at' => now()->toISOString(),
            ], $initialData);

            $statusKey = "schema_import_job_{$jobId}";
            Cache::put($statusKey, $jobData, now()->addHours(24));

            Log::info("Created job {$jobId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Error creating job {$jobId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update job status
     *
     * @param string $jobId Job ID to update
     * @param array $updateData Data to update
     * @return bool True if update was successful
     */
    public function updateJobStatus(string $jobId, array $updateData): bool
    {
        try {
            $statusKey = "schema_import_job_{$jobId}";
            $currentStatus = Cache::get($statusKey, []);

            $updatedStatus = array_merge($currentStatus, $updateData, [
                'updated_at' => now()->toISOString()
            ]);

            Cache::put($statusKey, $updatedStatus, now()->addHours(24));

            return true;
        } catch (\Exception $e) {
            Log::error("Error updating job {$jobId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check job status and process successful results
     *
     * @param string $jobId Job ID to check and process
     * @return array|null Job status with processed data if successful
     */
    public function checkAndProcessJobStatus(string $jobId): ?array
    {
        $status = Cache::get("schema_import_status_{$jobId}");

        // Debug cache retrieval
        Log::debug('Schema import status check', [
            'job_id' => $jobId,
            'cache_key' => "schema_import_status_{$jobId}",
            'status_found' => $status !== null,
            'status_value' => $status['status'] ?? 'not set'
        ]);

        if (!$status) {
            return ['status' => 'pending', 'message' => 'Job is still in queue'];
        }

        if (isset($status['status']) && $status['status'] === 'processing') {
            // Return processing status with progress
            return [
                'status' => 'processing',
                'message' => $status['message'] ?? 'Processing schema...',
                'progress' => $status['progress'] ?? 0
            ];
        }

        if (isset($status['status']) && $status['status'] === 'success') {
            Log::debug('Processing successful schema import', [
                'has_schema' => isset($status['schema']),
                'schema_type' => isset($status['schema']) ? gettype($status['schema']) : 'not set',
                'has_content' => isset($status['raw_content']) && !empty($status['raw_content']),
                'is_chunked' => $status['chunked'] ?? false
            ]);

            // Process the successful result
            try {
                $processedData = $this->processSuccessfulJobResult($jobId, $status);

                if ($processedData) {
                    return [
                        'status' => 'success',
                        'message' => 'Schema import completed successfully',
                        'summary' => $status['summary'] ?? [],
                        'processed_data' => $processedData
                    ];
                } else {
                    return [
                        'status' => 'error',
                        'message' => 'Error processing successful job result'
                    ];
                }
            } catch (\Exception $e) {
                Log::error("Error processing successful schema import: " . $e->getMessage(), [
                    'exception' => $e,
                    'job_id' => $jobId
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Error processing schema: ' . $e->getMessage()
                ];
            }
        }

        if (isset($status['status']) && $status['status'] === 'error') {
            return [
                'status' => 'error',
                'message' => $status['message'] ?? 'An error occurred during schema import'
            ];
        }

        return $status;
    }

    /**
     * Process successful job result and extract necessary data
     *
     * @param string $jobId Job ID
     * @param array $status Job status data
     * @return array|null Processed data for component state
     */
    private function processSuccessfulJobResult(string $jobId, array $status): ?array
    {
        // Get the structure information
        $structure = Cache::get("schema_structure_{$jobId}");
        if (!$structure) {
            Log::warning("Schema structure not found in cache", ['job_id' => $jobId]);
            return null;
        }

        // Start building a complete schema
        $parsedSchema = $status['schema'] ?? [];

        // Check if data is stored in chunks
        if (isset($status['chunked']) && $status['chunked']) {
            Log::info("Loading chunked schema data", ['job_id' => $jobId]);

            // Get the reassembled schema
            $parsedSchema = $this->reassembleChunkedSchemaFromCache($jobId, $parsedSchema);
        } else {
            // Get elements directly if not chunked
            $elements = Cache::get("schema_elements_{$jobId}");
            if ($elements) {
                if ($structure['type'] === 'adze-template') {
                    if (!isset($parsedSchema['data'])) {
                        $parsedSchema['data'] = [];
                    }
                    $parsedSchema['data']['elements'] = $elements;
                } else {
                    $parsedSchema['fields'] = $elements;
                }
            } else {
                Log::error("Schema elements not found in cache", ['job_id' => $jobId]);
                return null;
            }
        }

        // Check if we have a valid schema
        if ($parsedSchema === null || empty($parsedSchema)) {
            Log::warning("Invalid or empty parsed schema after reassembly", [
                'job_id' => $jobId,
                'schema_type' => gettype($parsedSchema)
            ]);
            return null;
        }

        // Extract field mappings
        $schemaParser = new \App\Filament\Forms\Helpers\SchemaParser();
        $extractedData = $schemaParser->extractFieldMappings($parsedSchema);

        return [
            'parsed_schema' => $parsedSchema,
            'raw_content' => $status['raw_content'] ?? '',
            'field_mappings' => $extractedData['mappings'] ?? [],
            'select_options' => $extractedData['selectOptions'] ?? [],
        ];
    }

    /**
     * Reassemble chunked schema from cache (alternative method name for clarity)
     *
     * @param string $jobId Job ID for chunked data
     * @param array $baseSchema Base schema structure
     * @return array Complete reassembled schema
     */
    private function reassembleChunkedSchemaFromCache(string $jobId, array $baseSchema): array
    {
        try {
            // Get structure data
            $structure = Cache::get("schema_structure_{$jobId}");
            if (!$structure) {
                Log::warning("Schema structure not found in cache", ['job_id' => $jobId]);
                return $baseSchema;
            }

            // Check if elements are chunked
            $chunksCount = Cache::get("schema_elements_chunks_{$jobId}");
            if ($chunksCount) {
                // Reassemble from chunks
                Log::debug("Reassembling schema from {$chunksCount} chunks", ['job_id' => $jobId]);
                $elements = [];

                for ($i = 0; $i < $chunksCount; $i++) {
                    $chunk = Cache::get("schema_elements_chunk_{$jobId}_{$i}");
                    if ($chunk) {
                        $elements = array_merge($elements, $chunk);
                    } else {
                        Log::warning("Schema chunk {$i} missing", ['job_id' => $jobId]);
                    }
                    // Free memory after processing each chunk
                    gc_collect_cycles();
                }
            } else {
                // Get elements directly
                $elements = Cache::get("schema_elements_{$jobId}");
            }

            // Rebuild the schema with correct structure
            if ($structure['type'] === 'adze-template') {
                $baseSchema['data']['elements'] = $elements;
            } else {
                $baseSchema['fields'] = $elements;
            }

            return $baseSchema;
        } catch (\Exception $e) {
            Log::error("Error reassembling chunked schema: " . $e->getMessage(), [
                'exception' => $e,
                'job_id' => $jobId
            ]);
            return $baseSchema;
        }
    }
}
