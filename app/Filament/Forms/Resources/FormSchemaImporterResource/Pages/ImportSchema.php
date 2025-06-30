<?php

namespace App\Filament\Forms\Resources\FormSchemaImporterResource\Pages;

use App\Filament\Forms\Helpers\SchemaFormatter;
use App\Filament\Forms\Helpers\SchemaParser;
use App\Filament\Forms\Helpers\ImportSessionManager;
use App\Filament\Forms\Helpers\ImportPaginator;
use App\Filament\Forms\Helpers\ImportFormBuilder;
use App\Filament\Forms\Helpers\ImportFieldMapper;
use App\Filament\Forms\Helpers\ImportJobManager;
use App\Filament\Forms\Helpers\ImportValidator;
use App\Filament\Forms\Imports\FormSchemaImporter;
use App\Filament\Forms\Resources\FormSchemaImporterResource;
use App\Http\Middleware\CheckRole;
use App\Models\Form as FormModel;
use App\Models\FormField;
use App\Models\FormSchemaImportSession;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form as FilamentForm;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Form Schema Importer Page
 *
 * This class manages the import of form schemas with field mapping capabilities.
 * It has been refactored to use helper classes for better maintainability:
 * - ImportSessionManager: Handles session management and persistence
 * - ImportPaginator: Manages pagination for large schemas
 * - ImportFormBuilder: Builds the wizard form components
 * - ImportFieldMapper: Handles field mapping logic and options
 * - ImportJobManager: Manages background job processing
 * - ImportValidator: Validates content and import readiness
 */
class ImportSchema extends Page implements HasForms
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static string $resource = FormSchemaImporterResource::class;
    protected static string $view = 'filament.forms.resources.form-schema-importer-resource.pages.import-schema';
    protected static ?string $title = 'Import Form Schema';

    // Core component state
    public ?array $data = [];
    public ?array $parsedSchema = null;
    public ?array $fieldMappings = [];
    public ?array $selectOptions = [];
    public array $fieldDetails = [];
    public bool $loadingFieldDetails = false;
    public array $jobStatus = ['status' => 'idle', 'message' => ''];
    public int $schemaVersion = 1; // Cache buster for form schema
    public ?FormSchemaImportSession $importSession = null; // Current import session

    // Helper class instances
    private ImportSessionManager $sessionManager;
    private ImportPaginator $paginator;
    private ImportFormBuilder $formBuilder;
    private ImportFieldMapper $fieldMapper;
    private ImportJobManager $jobManager;
    private ImportValidator $validator;

    public function mount(?string $session = null): void
    {
        // Initialize helper classes
        $this->initializeHelperClasses();

        // Try to load an existing session first
        if ($session) {
            $this->importSession = $this->sessionManager->loadSession($session);

            if ($this->importSession) {
                $this->restoreSessionState();
                $this->sessionManager->sendRestorationNotification($this->importSession);
            } else {
                $this->sessionManager->sendSessionNotFoundNotification();
            }
        }

        // Handle form_id parameter from CreateFormVersion (query string fallback)
        if (!$this->importSession) {
            $formId = request()->query('form_id');
            $formData = $this->sessionManager->handleFormIdParameter($formId);
            if ($formData) {
                $this->form->fill($formData);
            }
        }

        // Check if there's an ongoing import job
        if (isset($this->data['schema_import_job_id'])) {
            $status = $this->jobManager->checkJobStatus($this->data['schema_import_job_id']);
            if ($status) {
                $this->jobStatus = $status;
            }
        }

        // Initialize pagination and schema version
        $this->schemaVersion = 1;
        $this->data['pagination_per_page'] = $this->paginator->getPerPage();
    }

    /**
     * Initialize all helper class instances
     *
     * @return void
     */
    private function initializeHelperClasses(): void
    {
        $this->sessionManager = new ImportSessionManager();
        $this->paginator = new ImportPaginator();
        $this->formBuilder = new ImportFormBuilder($this->importSession, $this->parsedSchema, $this->schemaVersion);
        $this->fieldMapper = new ImportFieldMapper();
        $this->jobManager = new ImportJobManager();
        $this->validator = new ImportValidator();
    }
    /**
     * Restore component state from session
     *
     * @return void
     */
    private function restoreSessionState(): void
    {
        // Ensure helper classes are initialized
        $this->ensureHelperClassesInitialized();

        // Restore session data
        $sessionData = $this->sessionManager->restoreSessionData($this->importSession);

        // Restore component state
        $this->parsedSchema = $this->importSession->getParsedSchemaAttribute();
        $this->fieldMappings = $this->importSession->field_mappings ?? [];

        // Restore pagination state from session
        if (isset($sessionData['current_page'], $sessionData['per_page'])) {
            $this->paginator->restoreState([
                'current_page' => $sessionData['current_page'],
                'per_page' => $sessionData['per_page'],
                'total_fields' => $sessionData['total_fields'] ?? 0,
            ]);
        }

        // Fill the form with the session data
        $this->form->fill($sessionData);

        // Ensure the data property is properly set
        if (!isset($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, $sessionData);

        // Mark session as in progress
        $this->importSession->markInProgress();

        // Update form builder with current state
        $this->formBuilder->setImportSession($this->importSession);
        $this->formBuilder->setParsedSchema($this->parsedSchema);
    }

    public function form(FilamentForm $form): FilamentForm
    {
        // Ensure helper classes are initialized
        $this->ensureHelperClassesInitialized();

        // Restore pagination state from form data if available
        if (isset($this->data['current_page']) || isset($this->data['per_page'])) {
            Log::debug('Restoring pagination state in form()', [
                'current_page' => $this->data['current_page'] ?? 'not set',
                'per_page' => $this->data['per_page'] ?? 'not set',
                'total_fields' => $this->data['total_fields'] ?? 'not set'
            ]);

            $this->paginator->restoreState([
                'current_page' => $this->data['current_page'] ?? 1,
                'per_page' => $this->data['per_page'] ?? 10,
                'total_fields' => $this->data['total_fields'] ?? 0,
            ]);
        }

        // Update form builder with current state
        $this->formBuilder->setImportSession($this->importSession);
        $this->formBuilder->setParsedSchema($this->parsedSchema);
        $this->formBuilder->setSchemaVersion($this->schemaVersion);

        // Provide field mapping schema if we have a parsed schema
        if ($this->parsedSchema !== null) {
            $fieldMappingSchema = $this->getFieldMappingSchema();
            $this->formBuilder->setFieldMappingSchema($fieldMappingSchema);

            // Extract current field mappings from form data for preview generation
            $currentFieldMappings = [];
            foreach ($this->data as $key => $value) {
                if (str_starts_with($key, 'field_mapping_')) {
                    $currentFieldMappings[$key] = $value;
                }
            }

            // Pass current field mappings to form builder for preview
            $this->formBuilder->setCurrentFieldMappings($currentFieldMappings);
        }

        return $form->schema($this->formBuilder->buildFormSchema())->statePath('data');
    }

    public function parseSchema(): void
    {
        $this->ensureHelperClassesInitialized();

        $content = $this->data['schema_content'] ?? null;

        // Validate schema content first
        $validation = $this->validator->validateSchemaContent($content);
        if (!$validation['valid']) {
            $this->validator->sendValidationErrorNotification($validation);
            return;
        }

        try {
            $schemaParser = new SchemaParser();

            // Parse the schema
            $this->parsedSchema = $schemaParser->parseSchema($content);

            // Check if parsing was successful
            if ($this->parsedSchema === null) {
                Notification::make()
                    ->danger()
                    ->title('Schema Parsing Error')
                    ->body('Could not parse the uploaded schema file. Please check the file format and try again.')
                    ->send();
                return;
            }

            // Extract fields and build mappings using the field mapper
            $extractedData = $schemaParser->extractFieldMappings($this->parsedSchema);
            $this->fieldMappings = $extractedData['mappings'] ?? [];
            $this->selectOptions = $extractedData['selectOptions'] ?? [];

            // Reset pagination to first page
            $this->paginator->resetPagination();

            // Initialize field properties in data array
            $fieldProperties = $this->fieldMapper->initializeFieldProperties($this->fieldMappings, $this->parsedSchema);
            $this->data = array_merge($this->data, $fieldProperties);

            $fieldCount = count($this->fieldMappings);

            // Determine schema format for notification
            $format = 'legacy';
            if (isset($this->parsedSchema['data']) && isset($this->parsedSchema['data']['elements'])) {
                $format = 'adze-template';
            }

            // Update form builder with new schema
            $this->formBuilder->setParsedSchema($this->parsedSchema);
            $this->schemaVersion++; // Increment to force form refresh
            $this->formBuilder->setSchemaVersion($this->schemaVersion);

            // Update field mapping schema in form builder
            $fieldMappingSchema = $this->getFieldMappingSchema();
            $this->formBuilder->setFieldMappingSchema($fieldMappingSchema);

            // Auto-save progress if we have a session
            $this->sessionManager->autoSaveProgress($this->importSession, $this->data, $this->parsedSchema);

            Notification::make()
                ->success()
                ->title('Schema Parsed Successfully')
                ->body("Found {$fieldCount} fields in {$format} format schema. You can now map fields on the next step.")
                ->send();
        } catch (\Exception $e) {
            Log::error('Error parsing schema: ' . $e->getMessage(), [
                'exception' => $e,
                'content_length' => strlen($content ?? '')
            ]);

            Notification::make()
                ->danger()
                ->title('Schema Parsing Error')
                ->body('An error occurred while parsing the schema: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * Extract fields from a schema
     *
     * @param array $parsedSchema The parsed schema to extract fields from
     * @return array The extracted fields
     */
    protected function extractFieldsFromSchema(array $parsedSchema): array
    {
        $schemaParser = new SchemaParser();
        $fields = [];

        if (isset($parsedSchema['data']) && isset($parsedSchema['data']['elements'])) {
            $fields = $schemaParser->extractFieldsFromSchema($parsedSchema['data']['elements']);
        } elseif (isset($parsedSchema['fields'])) {
            $fields = $schemaParser->extractFieldsFromSchema($parsedSchema['fields']);
        }

        return $fields;
    }

    public function getFieldMappingSchema(): array
    {
        // Ensure helper classes are initialized
        $this->ensureHelperClassesInitialized();

        // Only continue if we have a parsed schema
        if ($this->parsedSchema === null) {
            return [
                \Filament\Forms\Components\Placeholder::make('no_schema')
                    ->label('No schema loaded')
                    ->content('No schema has been parsed yet. Please upload and parse a schema first.')
            ];
        }

        // Extract all fields first for pagination
        $allFields = $this->extractFieldsFromSchema($this->parsedSchema);

        // Update paginator with total field count
        $this->paginator->setTotalFields(count($allFields));

        // Apply pagination using the paginator
        $paginatedFields = $this->paginator->paginateFields($allFields);

        Log::debug('Field mapping schema generation', [
            'total_fields' => count($allFields),
            'paginated_fields_count' => count($paginatedFields),
            'current_page' => $this->paginator->getCurrentPage(),
            'per_page' => $this->paginator->getPerPage(),
            'max_page' => $this->paginator->getMaxPage()
        ]);

        // Handle empty fields case
        if (empty($paginatedFields)) {
            return [
                \Filament\Forms\Components\Placeholder::make('no_fields')
                    ->label('No fields found')
                    ->content('No fields were found in the schema or schema has not been parsed yet.')
            ];
        }

        // Get pagination info for UI
        $paginationInfo = $this->paginator->getPaginationInfo();

        Log::debug('Pagination info for UI', $paginationInfo);

        // Initialize schema with pagination controls at the top
        $schema = [
            \Filament\Forms\Components\Grid::make(3)
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('pagination_info')
                        ->label('')
                        ->content("Showing {$paginationInfo['start']}-{$paginationInfo['end']} of {$paginationInfo['total']} fields")
                        ->extraAttributes(['wire:key' => 'pagination-info-' . $this->schemaVersion]),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('prev_page')
                            ->label('Previous')
                            ->icon('heroicon-o-chevron-left')
                            ->color('gray')
                            ->visible(fn() => $paginationInfo['has_previous'])
                            ->action(function () {
                                $this->prevPage();
                            }),

                        \Filament\Forms\Components\Actions\Action::make('current_page')
                            ->label("Page {$paginationInfo['current_page']} of {$paginationInfo['total_pages']}")
                            ->color('gray')
                            ->disabled()
                            ->extraAttributes(['wire:key' => 'current-page-' . $this->schemaVersion]),

                        \Filament\Forms\Components\Actions\Action::make('next_page')
                            ->label('Next')
                            ->icon('heroicon-o-chevron-right')
                            ->iconPosition('after')
                            ->color('gray')
                            ->visible(fn() => $paginationInfo['has_next'])
                            ->action(function () {
                                $this->nextPage();
                            }),
                    ]),

                    \Filament\Forms\Components\Select::make('pagination_per_page')
                        ->label('Fields per page')
                        ->options([
                            5 => '5 fields',
                            10 => '10 fields',
                            15 => '15 fields',
                            25 => '25 fields',
                            50 => '50 fields',
                        ])
                        ->default($this->paginator->getPerPage())
                        ->afterStateUpdated(function ($state) {
                            $this->changePerPage((int) $state);
                        })
                        ->live()
                        ->reactive()
                        ->selectablePlaceholder(false)
                        ->extraAttributes(['wire:key' => 'per-page-select-' . $this->schemaVersion])
                        ->columnSpan(1),
                ])
        ];

        // Create paginated schema
        $paginatedSchema = $this->paginator->createPaginatedSchema($this->parsedSchema, $allFields);

        // Use the field mapper to build the mapping schema
        $fieldSchemaComponents = $this->fieldMapper->buildFieldMappingSchema($paginatedSchema, true);

        // Merge the pagination controls with the field schema
        $schema = array_merge($schema, $fieldSchemaComponents);

        // Add pagination controls at the bottom for multi-page schemas
        if ($this->paginator->isPaginationNeeded()) {
            $schema[] = \Filament\Forms\Components\Grid::make(3)
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('pagination_info_bottom')
                        ->label('')
                        ->content(''),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('prev_page_bottom')
                            ->label('Previous')
                            ->icon('heroicon-o-chevron-left')
                            ->color('gray')
                            ->visible(fn() => $paginationInfo['has_previous'])
                            ->action(function () {
                                $this->prevPage();
                            }),

                        \Filament\Forms\Components\Actions\Action::make('current_page_bottom')
                            ->label("Page {$paginationInfo['current_page']} of {$paginationInfo['total_pages']}")
                            ->color('gray')
                            ->disabled()
                            ->extraAttributes(['wire:key' => 'current-page-bottom-' . $this->schemaVersion]),

                        \Filament\Forms\Components\Actions\Action::make('next_page_bottom')
                            ->label('Next')
                            ->icon('heroicon-o-chevron-right')
                            ->iconPosition('after')
                            ->color('gray')
                            ->visible(fn() => $paginationInfo['has_next'])
                            ->action(function () {
                                $this->nextPage();
                            }),
                    ]),

                    \Filament\Forms\Components\Placeholder::make('bottom_spacer')
                        ->label('')
                        ->content(''),
                ]);
        }

        return $schema;
    }


    /**
     * Get mapping options for a field: existing fields (by label/type) or 'new', with details for preview.
     *
     * @param string $type Field type
     * @param string $label Field label
     * @param string $name Field name
     * @param bool $repeating Whether field is repeating
     * @return array Options array for field mapping
     */
    protected function getMappingOptionsWithDetails($type, $label, $name, $repeating = false): array
    {
        // Use cached options if available
        return $this->fieldMappingOptions;
    }

    /**
     * Map field types to system data types
     *
     * @param string $type The field type to map
     * @return string The mapped data type
     */
    protected function mapFieldType(string $type): string
    {
        return (new SchemaParser())->mapFieldType($type);
    }

    protected function getActions(): array
    {
        return [
            Action::make('parse_schema')
                ->label(fn() => $this->parsedSchema !== null ? 'Parse Schema (Already Parsed)' : 'Parse Schema')
                ->requiresConfirmation(false)
                ->disabled(fn() => $this->parsedSchema !== null)
                ->color(fn() => $this->parsedSchema !== null ? 'gray' : 'primary')
                ->action(function () {
                    $content = $this->data['schema_content'] ?? null;
                    if (!$content) {
                        Notification::make()
                            ->title('No schema content')
                            ->body('Please upload or paste a schema before parsing.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Ensure the tmp directory exists
                    $tmpDir = storage_path('app/tmp');
                    if (!is_dir($tmpDir)) {
                        if (!mkdir($tmpDir, 0755, true)) {
                            Notification::make()
                                ->title('Error creating temporary directory')
                                ->body('Could not create temporary directory for schema processing.')
                                ->danger()
                                ->send();
                            return;
                        }
                    }

                    // Save content to a temp file
                    $tempPath = $tmpDir . '/schema_import_' . uniqid() . '.json';
                    if (file_put_contents($tempPath, $content) === false) {
                        Notification::make()
                            ->title('Error saving schema')
                            ->body('Could not save schema to temporary file.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Generate a unique job ID
                    $jobId = 'schema_import_' . uniqid();

                    // Remove any previous cache for this job
                    Cache::forget("schema_import_status_{$jobId}");

                    // Dispatch the job to the queue
                    try {
                        \App\Jobs\FormSchemaImportJob::dispatch($tempPath, $jobId);
                        // Store the job ID in the component state for polling
                        $this->data['schema_import_job_id'] = $jobId;
                        $this->jobStatus = ['status' => 'pending', 'message' => 'Job submitted to queue'];

                        Notification::make()
                            ->title('Schema parsing started')
                            ->body('The schema is being parsed in the background. You can continue working and check back for results.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error("FormSchemaImportJob dispatch failed: " . $e->getMessage(), [
                            'exception' => $e,
                            'trace' => $e->getTraceAsString()
                        ]);
                        Notification::make()
                            ->title('Error')
                            ->body('Failed to dispatch schema import job: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('save_session')
                ->label('Save Progress')
                ->icon('heroicon-o-bookmark')
                ->color('gray')
                ->visible(fn() => $this->parsedSchema !== null || !empty($this->data['schema_content']))
                ->form([
                    \Filament\Forms\Components\TextInput::make('session_name')
                        ->label('Session Name')
                        ->required()
                        ->default(fn() => $this->importSession?->session_name ?? $this->generateDefaultSessionName())
                        ->maxLength(255),
                    \Filament\Forms\Components\Textarea::make('description')
                        ->label('Description (Optional)')
                        ->default(fn() => $this->importSession?->description)
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->saveImportSession($data['session_name'], $data['description']);
                }),

            // Action::make('reset_import')
            //     ->label('Reset Import Process')
            //     ->icon('heroicon-o-arrow-path')
            //     ->color('warning')
            //     ->requiresConfirmation()
            //     ->modalHeading('Reset Import Process')
            //     ->modalDescription('Are you sure you want to reset the entire import process? This will clear all uploaded data, field mappings, and start fresh. This action cannot be undone.')
            //     ->modalSubmitActionLabel('Yes, Reset Everything')
            //     ->modalCancelActionLabel('Cancel')
            //     ->visible(fn() => $this->parsedSchema !== null || !empty($this->data['schema_content']))
            //     ->action('resetImportProcess'),
        ];
    }

    /**
     * Initialize data array with field properties after schema parsing
     * Memory-optimized version that only stores essential field state
     * Updated to use helper classes
     */
    protected function initializeFieldProperties(): void
    {
        $this->ensureHelperClassesInitialized();

        $fields = [];

        if ($this->parsedSchema !== null) {
            $fields = $this->extractFieldsFromSchema($this->parsedSchema);
        }

        // Store the total number of fields in paginator
        $this->paginator->setTotalFields(count($fields));

        // Memory optimization: only initialize mappings, not full previews
        foreach ($fields as $index => $field) {
            // Generate stable field ID based on available field identifier
            $fieldId = $field['token'] ?? $field['id'] ?? md5($field['name'] ?? "field_$index");
            $selectFieldName = "field_mapping_{$fieldId}";

            // Initialize select field with 'new' default in fieldMappings array
            $this->fieldMappings[$fieldId] = 'new';

            // Initialize just the select field in data to prevent Livewire errors
            $this->data[$selectFieldName] = 'new';

            // Don't initialize preview fields until they're requested
            // This saves substantial memory when there are many fields
        }

        // Log how many fields were initialized
        logger()->debug("📝 Memory-optimized initialization complete for " . count($fields) . " fields");
    }

    /**
     * Get detailed information about a specific form field
     * This is used by both direct calls and Livewire reactive components
     */
    public function getFormFieldDetails($fieldId)
    {
        $this->ensureHelperClassesInitialized();
        return $this->fieldMapper->getFormFieldDetails($fieldId);
    }

    /**
     * Livewire method for getting field details
     * This will be called when a field is selected
     */
    public function fetchFieldDetails($fieldId, $previewName)
    {
        try {
            // Debug log to diagnose the issue
            logger()->debug("🔎 Fetching field details for ID: {$fieldId} to update {$previewName}");

            // Convert to integer if it's a numeric string
            if (is_string($fieldId) && is_numeric($fieldId)) {
                $fieldId = (int)$fieldId;
                logger()->debug("🔄 Converting field ID string to integer: {$fieldId}");
            }

            // Store field details in component state
            $details = $this->getFormFieldDetails($fieldId);

            // Debug log to see what details were returned
            logger()->debug("📊 Field details retrieved: " . json_encode(array_keys($details)));

            // Format the details for display with better formatting
            $formattedDetails = collect($details)->map(function ($value, $key) {
                // Skip empty section headers
                if ($value === '') {
                    return "**{$key}**";
                }
                // Bold the key and format the value
                return "**{$key}:** {$value}";
            })->implode("\n");

            // Update the form state
            $this->form->fill([$previewName => $formattedDetails]);
            logger()->debug("✅ Updated form field {$previewName} with formatted details");

            // Also store in fieldDetails array for potential future use
            $this->fieldDetails[$fieldId] = $details;
        } catch (\Exception $e) {
            logger()->error("❌ Error in fetchFieldDetails: " . $e->getMessage());

            // Update preview with error message
            $errorDetails = "**❌ Error:** " . $e->getMessage() . "\n\n" .
                "**Suggestion:** Please try selecting another field or report this issue.";

            $this->form->fill([$previewName => $errorDetails]);
        }

        // Set a temporary loading state - no longer needed as we fetched the data
        $this->loadingFieldDetails = false;
    }

    /**
     * Check the status of a schema import job
     * Delegated to ImportJobManager for better organization
     *
     * @return array|null The job status or null if no job ID is set
     */
    public function checkSchemaImportStatus()
    {
        $this->ensureHelperClassesInitialized();

        $jobId = $this->data['schema_import_job_id'] ?? null;

        if (!$jobId) {
            return null;
        }

        // Delegate to job manager for status checking and processing
        $result = $this->jobManager->checkAndProcessJobStatus($jobId);

        // If job was successful, apply the results to our component state
        if ($result && $result['status'] === 'success' && isset($result['processed_data'])) {
            $processedData = $result['processed_data'];

            // Apply schema and content to component
            $this->parsedSchema = $processedData['parsed_schema'];
            $this->data['schema_content'] = $processedData['raw_content'];
            $this->fieldMappings = $processedData['field_mappings'];
            $this->selectOptions = $processedData['select_options'];

            // Initialize field properties using helper
            $fieldProperties = $this->fieldMapper->initializeFieldProperties($this->fieldMappings, $this->parsedSchema);
            $this->data = array_merge($this->data, $fieldProperties);

            // Update form builder with new schema
            $this->formBuilder->setParsedSchema($this->parsedSchema);
            $this->schemaVersion++; // Force form refresh
            $this->formBuilder->setSchemaVersion($this->schemaVersion);

            // Update field mapping schema in form builder
            $fieldMappingSchema = $this->getFieldMappingSchema();
            $this->formBuilder->setFieldMappingSchema($fieldMappingSchema);
        }

        return $result;
    }

    /**
     * Poll schema import status - can be called from frontend to check status
     * Delegated to ImportJobManager for better organization
     *
     * @return array Status information
     */
    public function pollSchemaImportStatus(): array
    {
        $this->ensureHelperClassesInitialized();

        $jobId = $this->data['schema_import_job_id'] ?? null;

        if (!$jobId) {
            $this->jobStatus = ['status' => 'idle', 'message' => ''];
            return $this->jobStatus;
        }

        $status = $this->jobManager->getJobStatusForUI($jobId);
        $this->jobStatus = $status;

        return $this->jobStatus;
    }

    /**
     * Generate field overview for import field
     * Delegated to ImportFieldMapper for consistency
     *
     * @param array $importField Import field data
     * @return string Formatted field overview
     */
    public function generateImportFieldOverview(array $importField): string
    {
        $this->ensureHelperClassesInitialized();
        return $this->fieldMapper->generateFieldOverview($importField);
    }

    // Pagination methods - delegated to paginator helper

    public function nextPage(): void
    {
        $this->ensureHelperClassesInitialized();

        Log::debug('NextPage called', [
            'current_page_before' => $this->paginator->getCurrentPage(),
            'total_fields' => $this->paginator->getTotalFields(),
            'per_page' => $this->paginator->getPerPage(),
            'max_page' => $this->paginator->getMaxPage()
        ]);

        if ($this->paginator->nextPage()) {
            Log::debug('NextPage successful', [
                'current_page_after' => $this->paginator->getCurrentPage(),
                'schema_version_before' => $this->schemaVersion
            ]);

            $this->schemaVersion++; // Force refresh

            // Regenerate field mapping schema with new pagination state
            $fieldMappingSchema = $this->getFieldMappingSchema();
            $this->formBuilder->setFieldMappingSchema($fieldMappingSchema);
            $this->formBuilder->setSchemaVersion($this->schemaVersion);

            // Force form refresh by clearing any cached schema
            $this->refreshFormSchema();

            // Update form data with new pagination state
            $this->data['current_page'] = $this->paginator->getCurrentPage();
            $this->data['per_page'] = $this->paginator->getPerPage();
            $this->data['total_fields'] = $this->paginator->getTotalFields();

            $this->sessionManager->autoSaveProgress($this->importSession, $this->data, $this->parsedSchema);

            Log::debug('NextPage completed', [
                'schema_version_after' => $this->schemaVersion
            ]);
        } else {
            Log::debug('NextPage failed - already at last page');
        }
    }

    public function prevPage(): void
    {
        $this->ensureHelperClassesInitialized();

        if ($this->paginator->prevPage()) {
            $this->schemaVersion++; // Force refresh

            // Regenerate field mapping schema with new pagination state
            $fieldMappingSchema = $this->getFieldMappingSchema();
            $this->formBuilder->setFieldMappingSchema($fieldMappingSchema);
            $this->formBuilder->setSchemaVersion($this->schemaVersion);

            // Force form refresh by clearing any cached schema
            $this->refreshFormSchema();

            // Update form data with new pagination state
            $this->data['current_page'] = $this->paginator->getCurrentPage();
            $this->data['per_page'] = $this->paginator->getPerPage();
            $this->data['total_fields'] = $this->paginator->getTotalFields();

            $this->sessionManager->autoSaveProgress($this->importSession, $this->data, $this->parsedSchema);
        }
    }

    public function changePerPage(int $perPage): void
    {
        $this->ensureHelperClassesInitialized();

        $changeInfo = $this->paginator->changePerPage($perPage);

        // Update the form data to keep it in sync
        $this->data['pagination_per_page'] = $this->paginator->getPerPage();

        // Force a re-render if per page changed
        if ($changeInfo['changed']) {
            $this->schemaVersion++; // Force refresh

            // Regenerate field mapping schema with new pagination state
            $fieldMappingSchema = $this->getFieldMappingSchema();
            $this->formBuilder->setFieldMappingSchema($fieldMappingSchema);
            $this->formBuilder->setSchemaVersion($this->schemaVersion);

            // Force form refresh by clearing any cached schema
            $this->refreshFormSchema();

            // Update form data with new pagination state
            $this->data['current_page'] = $this->paginator->getCurrentPage();
            $this->data['per_page'] = $this->paginator->getPerPage();
            $this->data['total_fields'] = $this->paginator->getTotalFields();

            $this->sessionManager->autoSaveProgress($this->importSession, $this->data, $this->parsedSchema);

            // Dispatch events for UI refresh
            $this->dispatch('refresh-field-mapping');
            $this->js('$wire.$refresh()');
        }
    }

    /**
     * Start polling when a job is active
     */
    public function bootedComponent(): void
    {
        if (isset($this->data['schema_import_job_id'])) {
            $this->pollJobStatus();
        }
    }

    /**
     * Livewire polling method for job status
     * Called by Livewire's poller every 3 seconds when active
     */
    public function pollJobStatus(): void
    {
        $jobId = $this->data['schema_import_job_id'] ?? null;

        if (!$jobId) {
            $this->stopPolling();
            return;
        }

        $status = $this->checkSchemaImportStatus();

        if ($status) {
            $this->jobStatus = $status;

            // If job is complete or failed, stop polling
            if (in_array($status['status'] ?? '', ['success', 'error', 'complete'])) {
                $this->stopPolling();
            }
        }
    }

    /**
     * Stop the polling
     */
    private function stopPolling(): void
    {
        $this->dispatch('stop-polling');
    }

    /**
     * Save the current import progress as a session
     */
    public function saveImportSession(string $sessionName, ?string $description = null): void
    {
        try {
            // Extract current field mappings from form data
            $currentFieldMappings = [];
            foreach ($this->data as $key => $value) {
                if (str_starts_with($key, 'field_mapping_')) {
                    $fieldId = str_replace('field_mapping_', '', $key);
                    $currentFieldMappings[$fieldId] = $value;
                }
            }

            if ($this->importSession) {
                // Update existing session
                $this->importSession->update([
                    'session_name' => $sessionName,
                    'description' => $description,
                ]);
                $this->importSession->updateFromImportState($this->data, $this->parsedSchema, $currentFieldMappings);
                $message = 'Import session updated successfully';
            } else {
                // Create new session
                $this->importSession = FormSchemaImportSession::createFromImportState(
                    $this->data,
                    $this->parsedSchema,
                    $currentFieldMappings
                );
                $this->importSession->update([
                    'session_name' => $sessionName,
                    'description' => $description,
                ]);
                $message = 'Import session saved successfully';
            }

            Notification::make()
                ->title('Session Saved')
                ->body($message . '. You can now leave and return to continue your work.')
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view_sessions')
                        ->label('View All Sessions')
                        ->url(FormSchemaImporterResource::getUrl('index')),
                ])
                ->send();
        } catch (\Exception $e) {
            Log::error("Error saving import session: " . $e->getMessage());

            Notification::make()
                ->title('Save Error')
                ->body('An error occurred while saving: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Generate a default session name based on current data
     */
    protected function generateDefaultSessionName(): string
    {
        $name = 'Schema Import';

        if (!empty($this->data['form_id'])) {
            $name = "Import: {$this->data['form_id']}";
        } elseif ($this->parsedSchema && isset($this->parsedSchema['form_id'])) {
            $name = "Import: {$this->parsedSchema['form_id']}";
        }

        $name .= ' - ' . now()->format('M j, Y g:i A');

        return $name;
    }

    /**
     * Reset the entire import process including session
     * Updated to use helper classes instead of direct properties
     */
    public function resetImportProcess(): void
    {
        $this->ensureHelperClassesInitialized();

        try {
            // Clear all component state
            $this->data = [];
            $this->parsedSchema = null;
            $this->fieldMappings = [];
            $this->selectOptions = [];
            $this->fieldDetails = [];
            $this->jobStatus = ['status' => 'idle', 'message' => ''];

            // Reset pagination using helper
            $this->paginator->resetPagination();
            $this->schemaVersion++; // Increment to force UI refresh

            // Re-initialize pagination control in form data
            $this->data['pagination_per_page'] = $this->paginator->getPerPage();

            // Clear any cached job data
            if (isset($this->data['schema_import_job_id'])) {
                $jobId = $this->data['schema_import_job_id'];

                // Use job manager to clean up cache entries
                $this->jobManager->cleanupJob($jobId);
            }

            // Cancel current session if exists
            if ($this->importSession) {
                $this->importSession->cancel();
                $this->importSession = null;
            }

            // Reset and refresh the form to ensure UI updates properly
            $this->form->fill([]);

            // Force full component refresh to ensure disabled state is properly updated
            $this->dispatch('$refresh');

            Notification::make()
                ->title('Import Process Reset')
                ->body('All data has been cleared. You can start fresh with a new schema.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error("Error resetting import process: " . $e->getMessage());

            Notification::make()
                ->title('Reset Error')
                ->body('An error occurred while resetting: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Update the import method to handle session completion
     */
    public function import(): void
    {
        try {
            if (empty($this->data['schema_content'])) {
                Notification::make()
                    ->title('No schema content')
                    ->body('Please upload or paste a schema first')
                    ->warning()
                    ->send();

                return;
            }

            // Create new FormSchemaImporter instance
            $importer = new FormSchemaImporter($this->data['schema_content']);

            // Extract current field mappings from form data
            $currentFieldMappings = [];
            foreach ($this->data as $key => $value) {
                if (str_starts_with($key, 'field_mapping_')) {
                    $currentFieldMappings[$key] = $value;
                }
            }

            Log::info("🔄 Starting import with field mappings", [
                'total_form_data_keys' => count($this->data),
                'field_mapping_keys_found' => count($currentFieldMappings),
                'mappings' => $currentFieldMappings
            ]);

            // Process the import
            $result = $importer->processImport([
                'form_id' => $this->data['form_id'],
                'title' => $this->data['form_title'],
                'ministry_id' => $this->data['ministry_id'],
                'create_new_form' => (bool)$this->data['create_new_form'],
                'create_new_version' => (bool)$this->data['create_new_version'],
                'field_mappings' => $currentFieldMappings,
            ]);

            if ($result['success']) {
                $formVersion = $result['formVersion'];

                // Mark session as completed if exists
                if ($this->importSession) {
                    $this->importSession->markCompleted($result);
                }

                Notification::make()
                    ->title('Import Successful')
                    ->body($result['message'])
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('View Form Version')
                            ->url(route('filament.forms.resources.form-versions.view', ['record' => $formVersion->id]))
                            ->openUrlInNewTab(),
                    ])
                    ->send();

                // Redirect to the form version page
                $this->redirect(route('filament.forms.resources.form-versions.view', ['record' => $formVersion->id]));
            } else {
                // Mark session as failed if exists
                if ($this->importSession) {
                    $this->importSession->markFailed($result['message']);
                }

                Notification::make()
                    ->title('Import Failed')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            // Mark session as failed if exists
            if ($this->importSession) {
                $this->importSession->markFailed($e->getMessage());
            }

            Notification::make()
                ->title('Error during import')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Auto-save session progress when significant state changes occur
     */
    protected function autoSaveSessionProgress(): void
    {
        if (!$this->importSession) {
            return; // No session to save to
        }

        try {
            // Extract current field mappings
            $currentFieldMappings = [];
            foreach ($this->data as $key => $value) {
                if (str_starts_with($key, 'field_mapping_')) {
                    $fieldId = str_replace('field_mapping_', '', $key);
                    $currentFieldMappings[$fieldId] = $value;
                }
            }

            // Update session with current state
            $this->importSession->updateFromImportState($this->data, $this->parsedSchema, $currentFieldMappings);

            Log::debug('Auto-saved session progress', [
                'session_id' => $this->importSession->id,
                'mappings_count' => count($currentFieldMappings)
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to auto-save session progress: ' . $e->getMessage());
            // Don't throw error for auto-save failures to avoid disrupting user workflow
        }
    }

    /**
     * Ensure helper classes are initialized (lazy initialization)
     * This prevents errors when methods are called before mount()
     *
     * @return void
     */
    private function ensureHelperClassesInitialized(): void
    {
        if (!isset($this->sessionManager)) {
            $this->initializeHelperClasses();
        }
    }

    /**
     * Force form schema refresh after pagination changes
     *
     * The schemaVersion increment and wire:key updates should be sufficient
     * to trigger a re-render of the form components.
     *
     * @return void
     */
    private function refreshFormSchema(): void
    {
        // The schemaVersion increment already triggers wire:key updates
        // which should force Livewire to re-render the components
        // No additional action needed here
    }
}
