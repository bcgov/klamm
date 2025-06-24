<?php

namespace App\Filament\Forms\Resources\FormSchemaImporterResource\Pages;

use App\Filament\Forms\Helpers\SchemaFormatter;
use App\Filament\Forms\Helpers\SchemaParser;
use App\Filament\Forms\Imports\FormSchemaImporter;
use App\Filament\Forms\Resources\FormSchemaImporterResource;
use App\Models\Form as FormModel;
use App\Models\FormField;
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
 * It works with SchemaParser to parse and extract fields from schemas,
 * and SchemaFormatter to format and display field information.
 */
class ImportSchema extends Page implements HasForms
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static string $resource = FormSchemaImporterResource::class;

    protected static string $view = 'filament.forms.resources.form-schema-importer-resource.pages.import-schema';

    protected static ?string $title = 'Import Form Schema';

    public ?array $data = [];
    public ?array $parsedSchema = null;
    public ?array $fieldMappings = [];
    public ?array $selectOptions = [];
    public ?array $formFieldOptions = [];
    public array $fieldDetails = [];
    public bool $loadingFieldDetails = false;
    public array $jobStatus = ['status' => 'idle', 'message' => ''];
    public array $fieldMappingOptions = [];

    // New properties for pagination
    public int $currentPage = 1;
    public int $perPage = 10;
    public int $totalFields = 0;
    public array $paginatedFields = [];

    public function mount(?FormModel $record = null): void
    {
        // Initialize form with record data if provided
        if ($record) {
            $this->form->fill([
                'form_id' => $record->form_id,
                'form_title' => $record->form_title,
                'ministry_id' => $record->ministry_id,
                'form' => $record->id,
                'create_new_form' => false,
                'create_new_version' => true,
            ]);
        }

        // Check if there's an ongoing import job
        if (isset($this->data['schema_import_job_id'])) {
            $status = $this->checkSchemaImportStatus();
            if ($status) {
                $this->jobStatus = $status;
            }
        }

        // Load form field options for mapping
        $this->loadFormFieldOptions();

        // Initialize pagination properties
        $this->currentPage = 1;
        $this->perPage = 10; // Adjust this number as needed for UI performance

        // Cache field mapping options once for all fields - using lightweight labels
        $this->fieldMappingOptions = \App\Filament\Forms\Helpers\SchemaFormatter::getAllMappingOptions(true);
    }

    protected function loadFormFieldOptions(): void
    {
        // Get form fields grouped by data type
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

    public function form(FilamentForm $form): FilamentForm
    {
        return $form->schema([
            \Filament\Forms\Components\Wizard::make([
                \Filament\Forms\Components\Wizard\Step::make('Import Source')
                    ->schema([
                        \Filament\Forms\Components\Section::make('Form Schema Source')
                            ->schema([
                                \Filament\Forms\Components\Tabs::make('source_tabs')
                                    ->tabs([
                                        \Filament\Forms\Components\Tabs\Tab::make('Upload File')
                                            ->schema([
                                                \Filament\Forms\Components\FileUpload::make('schema_file')
                                                    ->label('Schema File')
                                                    ->acceptedFileTypes(['application/json'])
                                                    ->maxSize(5120)
                                                    ->helperText('Upload a JSON file with form schema (max 5MB)')
                                                    ->reactive()
                                                    ->afterStateUpdated(function (Set $set, ?\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $state) {
                                                        if ($state) {
                                                            $content = file_get_contents($state->getRealPath());
                                                            $set('schema_content', $content);
                                                            $set('parsed_content', FormSchemaImporterResource::parseSchema($content));
                                                        } else {
                                                            $set('schema_content', null);
                                                            $set('parsed_content', null);
                                                        }
                                                    }),
                                            ]),
                                        \Filament\Forms\Components\Tabs\Tab::make('Paste JSON')
                                            ->schema([
                                                \Filament\Forms\Components\Textarea::make('schema_content')
                                                    ->label('Schema Content')
                                                    ->placeholder('Paste JSON form schema here...')
                                                    ->rows(15)
                                                    ->columnSpanFull()
                                                    ->reactive()
                                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                                        if ($state) {
                                                            $set('parsed_content', FormSchemaImporterResource::parseSchema($state));
                                                        } else {
                                                            $set('parsed_content', null);
                                                        }
                                                    }),
                                            ]),
                                    ]),
                                \Filament\Forms\Components\Section::make('Schema Summary')
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('parsed_content.form_id')
                                            ->label('Form ID')
                                            ->disabled(),
                                        \Filament\Forms\Components\TextInput::make('parsed_content.field_count')
                                            ->label('Field Count')
                                            ->disabled(),
                                        \Filament\Forms\Components\TextInput::make('parsed_content.container_count')
                                            ->label('Container Count')
                                            ->disabled(),
                                        \Filament\Forms\Components\TextInput::make('parsed_content.format')
                                            ->label('Format')
                                            ->disabled(),
                                    ])
                                    ->columns(2)
                                    ->visible(fn(Get $get): bool => (bool) $get('parsed_content')),
                            ]),
                    ]),
                \Filament\Forms\Components\Wizard\Step::make('Form Details')
                    ->schema([
                        \Filament\Forms\Components\Section::make('Form Configuration')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('form_id')
                                    ->label('Form ID')
                                    ->required()
                                    ->default(fn(Get $get) => $get('parsed_content.form_id'))
                                    ->maxLength(255),
                                \Filament\Forms\Components\TextInput::make('form_title')
                                    ->label('Form Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->default(fn(Get $get) => $get('parsed_content.title')),
                                \Filament\Forms\Components\Select::make('ministry_id')
                                    ->label('Ministry')
                                    ->options(\App\Models\Ministry::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),
                                \Filament\Forms\Components\Select::make('form')
                                    ->label('Use existing form?')
                                    ->options(\App\Models\Form::pluck('form_title', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Select an existing form or create a new one'),
                            ])
                            ->columns(2),
                        \Filament\Forms\Components\Section::make('Import Options')
                            ->schema([
                                \Filament\Forms\Components\Toggle::make('create_new_form')
                                    ->label('Create a new form if one doesn\'t exist')
                                    ->default(true)
                                    ->reactive(),
                                \Filament\Forms\Components\Toggle::make('create_new_version')
                                    ->label('Create a new form version')
                                    ->default(true)
                                    ->reactive(),
                            ])
                            ->columns(2),
                    ]),
                \Filament\Forms\Components\Wizard\Step::make('Field Mapping')
                    ->schema([
                        \Filament\Forms\Components\Section::make('Field Mapping')
                            ->description('Map fields from the imported schema to existing fields in the system')
                            ->schema($this->getFieldMappingSchema())
                            ->columnSpanFull(),
                    ])
                    ->visible(function (Get $get) {
                        return (bool) $get('parsed_content');
                    }),
                \Filament\Forms\Components\Wizard\Step::make('Import Preview')
                    ->schema([
                        \Filament\Forms\Components\Section::make('Preview')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('preview')
                                    ->label('Import Preview')
                                    ->content(function () {
                                        if ($this->parsedSchema === null) {
                                            return 'No schema has been parsed yet. Please upload and parse a schema first.';
                                        }

                                        try {
                                            // Ensure we have valid data for the preview
                                            $previewData = $this->data ?? [];

                                            // Safety check before generating the preview
                                            if (!is_array($this->parsedSchema)) {
                                                Log::warning('Attempted to generate preview with invalid schema type: ' . gettype($this->parsedSchema));
                                                return 'Invalid schema format. Please try uploading the file again.';
                                            }

                                            return new HtmlString(
                                                '<pre style="background:#f9fafb;border-radius:6px;padding:1em;overflow:auto;font-size:0.95em;">' .
                                                    htmlspecialchars((new SchemaFormatter())->getImportPreviewJson($this->parsedSchema, $previewData)) .
                                                    '</pre>'
                                            );
                                        } catch (\Exception $e) {
                                            Log::error('Error generating import preview: ' . $e->getMessage(), [
                                                'exception' => $e,
                                                'has_schema' => isset($this->parsedSchema),
                                                'schema_type' => isset($this->parsedSchema) ? gettype($this->parsedSchema) : 'null'
                                            ]);
                                            return 'Error generating preview: ' . $e->getMessage();
                                        }
                                    })
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->visible(function (Get $get) {
                        return (bool) $get('parsed_content');
                    }),
                \Filament\Forms\Components\Wizard\Step::make('Confirm Import')
                    ->schema([
                        \Filament\Forms\Components\Section::make('Final Confirmation')
                            ->schema([
                                \Filament\Forms\Components\Toggle::make('confirm_import')
                                    ->label('I confirm this import')
                                    ->required()
                                    ->helperText('Please confirm you want to proceed with this import'),
                            ]),
                    ]),
            ])->skippable()->persistStepInQueryString(),
        ])->statePath('data');
    }

    public function parseSchema(): void
    {
        $content = $this->data['schema_content'] ?? null;

        if (!$content) {
            Notification::make()
                ->title('No schema content')
                ->body('Please upload or paste a schema first')
                ->warning()
                ->send();

            return;
        }

        try {
            $schemaParser = new SchemaParser();

            // Parse the schema
            $this->parsedSchema = $schemaParser->parseSchema($content);

            // Check if parsing was successful before continuing
            if ($this->parsedSchema === null) {
                Notification::make()
                    ->danger()
                    ->title('Schema Parsing Error')
                    ->body('Could not parse the uploaded schema file. Please check the file format and try again.')
                    ->send();
                return;
            }

            // Extract fields and build mappings
            $extractedData = $schemaParser->extractFieldMappings($this->parsedSchema);
            $this->fieldMappings = $extractedData['mappings'] ?? [];
            $this->selectOptions = $extractedData['selectOptions'] ?? [];

            // Reset pagination to first page
            $this->currentPage = 1;

            // Initialize field properties in data array to prevent Livewire Entangle errors
            // This also sets up totalFields for pagination
            $this->initializeFieldProperties();

            $fieldCount = count($this->fieldMappings);

            // Determine schema format for notification
            $format = 'legacy';
            if (isset($this->parsedSchema['data']) && isset($this->parsedSchema['data']['elements'])) {
                $format = 'adze-template';
            }

            Notification::make()
                ->title('Schema parsed successfully')
                ->body("Detected {$fieldCount} fields in {$format} format")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error("Error parsing schema: " . $e->getMessage());
            Notification::make()
                ->title('Error parsing schema')
                ->body($e->getMessage())
                ->danger()
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
        // Log current state for debugging
        logger()->debug("🔄 Building field mapping schema with pagination. Page {$this->currentPage}, showing {$this->perPage} per page");

        $schema = [];
        $fields = [];

        // Only extract fields if we have a parsed schema
        if ($this->parsedSchema !== null) {
            // Extract all fields first
            $allFields = $this->extractFieldsFromSchema($this->parsedSchema);
            $this->totalFields = count($allFields);

            logger()->debug("📊 Parsed schema has {$this->totalFields} total fields");

            // Apply pagination
            $start = ($this->currentPage - 1) * $this->perPage;
            $fields = array_slice($allFields, $start, $this->perPage);

            // Store the paginated fields in component state for efficient re-rendering
            $this->paginatedFields = $fields;

            logger()->debug("📄 Showing fields " . ($start + 1) . "-" . min($start + $this->perPage, $this->totalFields) . " of {$this->totalFields}");
        } else {
            logger()->debug("⚠️ No parsed schema available");
        }

        if (empty($fields)) {
            if ($this->totalFields > 0) {
                // This means we're on an empty page but have fields
                return [
                    \Filament\Forms\Components\Placeholder::make('no_fields_on_page')
                        ->label('No fields on this page')
                        ->content('No fields are available on this page. Try changing the page number.')
                ];
            }

            return [
                \Filament\Forms\Components\Placeholder::make('no_fields')
                    ->label('No fields found')
                    ->content('No fields were found in the schema or schema has not been parsed yet.')
            ];
        }

        // Add pagination controls at the top
        $schema[] = \Filament\Forms\Components\Grid::make(3)
            ->schema([
                \Filament\Forms\Components\Placeholder::make('pagination_info')
                    ->label('')
                    ->content(fn() => "Showing " . (($this->currentPage - 1) * $this->perPage + 1) . "-" .
                        min($this->currentPage * $this->perPage, $this->totalFields) . " of {$this->totalFields} fields"),

                \Filament\Forms\Components\Actions::make([
                    \Filament\Forms\Components\Actions\Action::make('prev_page')
                        ->label('Previous')
                        ->icon('heroicon-o-chevron-left')
                        ->color('gray')
                        ->visible(fn() => $this->currentPage > 1)
                        ->action(fn() => $this->prevPage()),

                    \Filament\Forms\Components\Actions\Action::make('current_page')
                        ->label("Page {$this->currentPage} of " . ceil($this->totalFields / $this->perPage))
                        ->color('gray')
                        ->disabled(),

                    \Filament\Forms\Components\Actions\Action::make('next_page')
                        ->label('Next')
                        ->icon('heroicon-o-chevron-right')
                        ->iconPosition('after')
                        ->color('gray')
                        ->visible(fn() => $this->currentPage < ceil($this->totalFields / $this->perPage))
                        ->action(fn() => $this->nextPage()),
                ]),

                \Filament\Forms\Components\Select::make('per_page')
                    ->label('Fields per page')
                    ->options([
                        5 => '5 fields',
                        10 => '10 fields',
                        15 => '15 fields',
                        25 => '25 fields',
                        50 => '50 fields',
                    ])
                    ->default($this->perPage)
                    ->afterStateUpdated(function ($state) {
                        $this->perPage = (int) $state;
                        $this->currentPage = 1; // Reset to first page on per-page change
                        $this->dispatch('refresh');
                    })
                    ->live()
                    ->selectablePlaceholder(false)
                    ->columnSpan(1),
            ]);

        foreach ($fields as $index => $field) {
            // Generate stable field ID based on the available field identifier
            $fieldId = $field['token'] ?? $field['id'] ?? md5($field['name'] ?? "field_$index");
            $selectFieldName = "field_mapping_{$fieldId}";
            $previewFieldName = "mapping_preview_{$fieldId}";

            // Extract basic field properties for minimal UI
            $label = $field['label'] ?? '';
            $name = $field['name'] ?? '';

            // Simplified type determination
            $type = isset($field['elementType'])
                ? $field['elementType'] . (isset($field['dataFormat']) ? " ({$field['dataFormat']})" : '')
                : ($field['type'] ?? 'text-input');

            // Create a simplified version of the field card with lazy-loaded details
            $schema[] = \Filament\Forms\Components\Card::make()
                ->schema([
                    // Header with basic info
                    \Filament\Forms\Components\Grid::make(2)
                        ->schema([
                            \Filament\Forms\Components\Placeholder::make("field_info_{$fieldId}")
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="text-lg font-medium">' . htmlspecialchars($label) . '</div>' .
                                        '<div class="text-sm text-gray-500">Name: ' . htmlspecialchars($name) . ' | Type: ' . htmlspecialchars($type) . '</div>'
                                )),

                            // ✅ Main Select Field - Simplified
                            \Filament\Forms\Components\Select::make($selectFieldName)
                                ->label('Map to')
                                ->searchable()
                                ->searchPrompt('Search fields...')
                                ->placeholder('Select a field or create new')
                                ->default('new')
                                ->live()
                                ->options($this->fieldMappingOptions)
                                ->afterStateUpdated(function ($state, \Livewire\Component $livewire) use ($fieldId) {
                                    // Store selection but don't generate preview yet
                                    $livewire->setMappingSelection($fieldId, $state);
                                })
                        ]),

                    // ✅ Toggle for field details - optimized to load on demand
                    \Filament\Forms\Components\Toggle::make("show_details_{$fieldId}")
                        ->label('Show field details')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, \Livewire\Component $livewire) use ($fieldId, $previewFieldName) {
                            if ($state) {
                                // Only load details when the toggle is turned on
                                $livewire->loadFieldDetails($fieldId, $previewFieldName);
                            } else {
                                // Clear details when toggled off to save memory
                                $set($previewFieldName, null);
                            }
                        }),

                    // ✅ Field Preview Output - only shown when toggle is on
                    \Filament\Forms\Components\Placeholder::make($previewFieldName)
                        ->label('Field Details')
                        ->content(fn(Get $get) => new HtmlString($get($previewFieldName) ?: '<div class="text-gray-500 italic">Toggle switch above to view field details</div>'))
                        ->visible(fn(Get $get) => $get("show_details_{$fieldId}"))
                        ->columnSpanFull(),
                ]);
        }

        // Add pagination controls at the bottom too if we have multiple pages
        if ($this->totalFields > $this->perPage) {
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
                            ->visible(fn() => $this->currentPage > 1)
                            ->action(fn() => $this->prevPage()),

                        \Filament\Forms\Components\Actions\Action::make('current_page_bottom')
                            ->label("Page {$this->currentPage} of " . ceil($this->totalFields / $this->perPage))
                            ->color('gray')
                            ->disabled(),

                        \Filament\Forms\Components\Actions\Action::make('next_page_bottom')
                            ->label('Next')
                            ->icon('heroicon-o-chevron-right')
                            ->iconPosition('after')
                            ->color('gray')
                            ->visible(fn() => $this->currentPage < ceil($this->totalFields / $this->perPage))
                            ->action(fn() => $this->nextPage()),
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

    /**
     * Import the schema with the provided mappings
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

            // Process the import
            $result = $importer->processImport([
                'form_id' => $this->data['form_id'],
                'title' => $this->data['form_title'],
                'ministry_id' => $this->data['ministry_id'],
                'create_new_form' => (bool)$this->data['create_new_form'],
                'create_new_version' => (bool)$this->data['create_new_version'],
                'field_mappings' => $this->fieldMappings,
            ]);

            if ($result['success']) {
                $formVersion = $result['formVersion'];

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
                Notification::make()
                    ->title('Import Failed')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error during import')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getActions(): array
    {
        return [
            Action::make('parse_schema')
                ->label('Parse Schema (Queued)')
                ->requiresConfirmation(false)
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

            Action::make('import')
                ->label('Import Schema')
                ->action('import')
                ->color('success')
                ->disabled(fn() => empty($this->parsedSchema)),
        ];
    }

    /**
     * Initialize data array with field properties after schema parsing
     * Memory-optimized version that only stores essential field state
     */
    protected function initializeFieldProperties(): void
    {
        $fields = [];

        if ($this->parsedSchema !== null) {
            $fields = $this->extractFieldsFromSchema($this->parsedSchema);
        }

        // Store the total number of fields for pagination
        $this->totalFields = count($fields);

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
        logger()->debug("📝 Memory-optimized initialization complete for {$this->totalFields} fields");
    }

    /**
     * Get detailed information about a specific form field
     * This is used by both direct calls and Livewire reactive components
     */
    public function getFormFieldDetails($fieldId)
    {
        try {
            logger()->debug("🔍 getFormFieldDetails called with ID: {$fieldId} (type: " . gettype($fieldId) . ")");

            // Handle "Create New" option with explicit string comparison
            if ($fieldId === 'new') {
                logger()->debug("📝 Creating details for NEW field");

                // Check if we have imported field data to show what will be created
                if (isset($this->parsedSchema['data']) && isset($this->parsedSchema['data']['elements'])) {
                    return [
                        'Action ✨' => 'Will create a new field with imported properties',
                        'Source 📄' => 'From Adze template format',
                        'Form ID 🆔' => $this->parsedSchema['form_id'] ?? 'Unknown',
                        'Field Count 🔢' => count($this->fieldMappings) ?? 0,
                        'Note 📝' => 'This field will be created using the properties from the imported schema. You can customize it further after import.',
                        'Import Status 📥' => '✅ Ready to import'
                    ];
                } else {
                    return [
                        'Action ✨' => 'Will create a new field with imported properties',
                        'Note 📝' => 'This field will be created using the properties from the imported schema. You can customize it further after import.',
                        'Import Status 📥' => '✅ Ready to import'
                    ];
                }
            }

            // Convert to integer if it's a numeric string to ensure consistent type handling
            if (is_string($fieldId) && is_numeric($fieldId)) {
                $fieldId = (int)$fieldId;
                logger()->debug("🔄 Converting string fieldId to int: {$fieldId}");
            }

            // Now make sure we have a valid integer ID for database lookup
            if (!is_int($fieldId) || $fieldId <= 0) {
                throw new \Exception("Invalid field ID: {$fieldId}");
            }

            // Get the field with all its relationships
            logger()->debug("🔎 Looking up field with ID: {$fieldId} (type: " . gettype($fieldId) . ")");

            try {
                // Get with relationships
                $field = \App\Models\FormField::with([
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

                logger()->debug("✅ Found field: {$field->name} (ID: {$field->id}) with type: " . ($field->dataType->name ?? 'unknown'));
            } catch (\Exception $e) {
                logger()->error("❌ Error finding field: " . $e->getMessage());
                return [
                    'Error ⚠️' => 'Field not found with ID: ' . $fieldId,
                    'Details 🔍' => $e->getMessage(),
                    'Suggestion 💡' => 'Please select a different field or choose "Create New"'
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

            logger()->debug("📋 Added content details for field {$field->id}");

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
                    $optionsList = $field->selectOptionInstances->map(function ($opt) {
                        return ($opt->label ?? $opt->value ?? '(option)');
                    })->join(', ');
                    $details['Select Options'] = $optionsList;
                    $details['Options Count'] = $field->selectOptionInstances->count();
                } else {
                    $details['Select Options'] = 'No options defined';
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

            logger()->debug("✅ Returning " . count($details) . " details for field ID {$field->id}");
            return $details;
        } catch (\Exception $e) {
            logger()->error("❌ Error in getFormFieldDetails: " . $e->getMessage());
            logger()->debug("🔍 Stack trace: " . $e->getTraceAsString());

            return [
                'Error ⚠️' => $e->getMessage(),
                'Stack Trace 🔍' => substr($e->getTraceAsString(), 0, 200) . '...',
                'Suggestion 💡' => 'Please report this error to the development team',
                'Debug Info 🐛' => 'Field ID: ' . $fieldId . ' | Type: ' . gettype($fieldId)
            ];
        }
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
     *
     * @return array|null The job status or null if no job ID is set
     */
    public function checkSchemaImportStatus()
    {
        $jobId = $this->data['schema_import_job_id'] ?? null;

        if (!$jobId) {
            return null;
        }

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

            // Apply the parsed schema to the component state
            try {
                // Get the structure information
                $structure = Cache::get("schema_structure_{$jobId}");
                if (!$structure) {
                    Log::warning("Schema structure not found in cache", ['job_id' => $jobId]);
                    return [
                        'status' => 'error',
                        'message' => 'Schema structure not found in cache. Please try again.'
                    ];
                }

                // Start building a complete schema
                $parsedSchema = $status['schema'] ?? [];

                // Check if data is stored in chunks
                if (isset($status['chunked']) && $status['chunked']) {
                    Log::info("Loading chunked schema data", ['job_id' => $jobId]);

                    // Get the reassembled schema
                    $this->parsedSchema = $this->reassembleChunkedSchema($jobId, $parsedSchema);
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
                        $this->parsedSchema = $parsedSchema;
                    } else {
                        Log::error("Schema elements not found in cache", ['job_id' => $jobId]);
                        return [
                            'status' => 'error',
                            'message' => 'Schema elements not found in cache. Please try again.'
                        ];
                    }
                }

                // Store the raw content
                $this->data['schema_content'] = $status['raw_content'] ?? '';

                // Check if we have a valid schema before continuing
                if ($this->parsedSchema === null || empty($this->parsedSchema)) {
                    Log::warning("Invalid or empty parsed schema after reassembly", [
                        'job_id' => $jobId,
                        'schema_type' => gettype($this->parsedSchema)
                    ]);

                    Notification::make()
                        ->warning()
                        ->title('Schema Not Available')
                        ->body('The parsed schema is not available or incomplete. The import job may have failed.')
                        ->send();

                    // Return error status
                    return [
                        'status' => 'error',
                        'message' => 'The parsed schema is not available or incomplete. The import job may have failed.'
                    ];
                }

                // Extract field mappings
                $schemaParser = new \App\Filament\Forms\Helpers\SchemaParser();
                $extractedData = $schemaParser->extractFieldMappings($this->parsedSchema);
                $this->fieldMappings = $extractedData['mappings'] ?? [];
                $this->selectOptions = $extractedData['selectOptions'] ?? [];

                // Initialize field properties
                $this->initializeFieldProperties();

                // Log success of schema processing
                Log::info("Schema successfully processed and applied to component", [
                    'job_id' => $jobId,
                    'field_count' => count($this->fieldMappings),
                    'has_parsed_schema' => !empty($this->parsedSchema)
                ]);

                // Return success status
                return [
                    'status' => 'success',
                    'message' => 'Schema import completed successfully',
                    'summary' => $status['summary'] ?? []
                ];
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
     * Reassemble schema data that was stored in chunks
     *
     * @param string $jobId The job ID
     * @param array $baseSchema The base schema structure
     * @return array The reassembled schema
     */
    protected function reassembleChunkedSchema(string $jobId, array $baseSchema): array
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
     * Get import field details for preview in the same format as existing fields
     */
    public function getImportFieldDetailsForPreview($fieldId)
    {
        // Find the imported field by $fieldId in the parsed schema
        $fields = [];
        if (isset($this->parsedSchema['data']) && isset($this->parsedSchema['data']['elements'])) {
            $fields = $this->extractFieldsFromSchema($this->parsedSchema['data']['elements']);
        } elseif (isset($this->parsedSchema['fields'])) {
            $fields = $this->extractFieldsFromSchema($this->parsedSchema['fields']);
        }
        foreach ($fields as $index => $field) {
            $importFieldId = $field['token'] ?? $field['id'] ?? md5($field['name'] ?? "field_$index");
            if ($importFieldId == $fieldId) {
                return $this->generateImportFieldOverview($field);
            }
        }
        return '<div class="text-gray-500 italic">No import field data found for preview.</div>';
    }

    public function getExistingFieldDetailsForPreview($fieldId)
    {
        try {
            $field = \App\Models\FormField::with([
                'dataType',
                'validations',
                'fieldGroups',
                'webStyles',
                'pdfStyles',
                'formFieldDateFormat',
                'formFieldValue',
                'selectOptionInstances',
                'formVersions'
            ])->findOrFail($fieldId);

            return $this->generateExistingFieldOverview($field);
        } catch (\Exception $e) {
            return '<div class="text-red-500">Error loading field: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    public function generateExistingFieldOverview($field): string
    {
        // Basic info - more minimal format
        $fieldDetails = [
            '🔑 Basic Info' => [
                'Field ID' => $field->id,
                'Name' => $field->name,
                'Type' => $field->dataType->name ?? 'unknown',
                'Label' => $field->label,
                'Created' => $field->created_at ? $field->created_at->format('Y-m-d') : '',
            ],
            '📝 Content & Display' => [
                'Help Text' => mb_strlen($field->help_text ?? '') > 100 ? mb_substr($field->help_text, 0, 97) . '...' : ($field->help_text ?: 'None'),
                'Description' => mb_strlen($field->description ?? '') > 100 ? mb_substr($field->description, 0, 97) . '...' : ($field->description ?: 'None'),
                'Data Binding' => $field->data_binding ?: 'None',
            ],
        ];

        // Validation with limits
        if ($field->validations && $field->validations->count() > 0) {
            $validations = $field->validations->take(5)->map(fn($v) => "$v->type: $v->value")->join(', ');
            if ($field->validations->count() > 5) {
                $validations .= ', ... ' . ($field->validations->count() - 5) . ' more';
            }
            $fieldDetails['✓ Validation'] = ['Rules' => $validations];
        } else {
            $fieldDetails['✓ Validation'] = ['Rules' => 'None'];
        }

        // Organization info with limits
        if ($field->fieldGroups && $field->fieldGroups->count() > 0) {
            $groups = $field->fieldGroups->take(3)->pluck('name')->join(', ');
            if ($field->fieldGroups->count() > 3) {
                $groups .= ', ... ' . ($field->fieldGroups->count() - 3) . ' more';
            }
            $fieldDetails['🗂️ Organization'] = ['Field Groups' => $groups];
        } else {
            $fieldDetails['🗂️ Organization'] = ['Field Groups' => 'Not assigned to any groups'];
        }

        // Options for select/radio/checkbox - with limits
        if (in_array($field->dataType->name ?? '', ['select', 'multiselect', 'radio', 'checkbox'])) {
            $optionsCount = $field->selectOptionInstances ? $field->selectOptionInstances->count() : 0;

            if ($optionsCount > 0) {
                $optionsHtml = '<ul class="list-disc pl-5">';
                // Only show first 5 options
                foreach ($field->selectOptionInstances->take(5) as $opt) {
                    $label = $opt->label ?? $opt->value ?? '';
                    $value = $opt->value ?? '';
                    $optionsHtml .= '<li><span class="font-medium">' . htmlspecialchars($label) . '</span>';
                    if ($value !== '' && $value !== $label) {
                        $optionsHtml .= ' <span class="text-gray-500">(' . htmlspecialchars($value) . ')</span>';
                    }
                    $optionsHtml .= '</li>';
                }

                // Show count of remaining options
                if ($optionsCount > 5) {
                    $optionsHtml .= '<li><span class="text-gray-500">... ' . ($optionsCount - 5) . ' more options</span></li>';
                }

                $optionsHtml .= '</ul>';

                $fieldDetails['� Options'] = [
                    'Count' => $optionsCount,
                    'Options' => $optionsHtml,
                ];
            } else {
                $fieldDetails['🔽 Options'] = [
                    'Options' => 'No options defined',
                ];
            }
        }

        // Usage statistics
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

        // Render as HTML with fewer DOM elements (div-based layout instead of tables)
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
     * Generate a simplified JSON preview of the imported form


    /**
     * Poll schema import status - can be called from frontend to check status
     *
     * @return array Status information
     */
    public function pollSchemaImportStatus(): array
    {
        $jobId = $this->data['schema_import_job_id'] ?? null;

        if (!$jobId) {
            $this->jobStatus = ['status' => 'idle', 'message' => ''];
            return $this->jobStatus;
        }

        $status = $this->checkSchemaImportStatus();

        if (!$status) {
            $this->jobStatus = ['status' => 'pending', 'message' => 'Job is still in queue'];
            return $this->jobStatus;
        }

        // Update the job status property for UI display
        $this->jobStatus = $status;

        // Debugging to help diagnose issues
        Log::debug("📊 Polling job status", [
            'jobId' => $jobId,
            'status' => $status['status'] ?? 'unknown',
            'has_schema' => isset($this->parsedSchema) && $this->parsedSchema !== null
        ]);

        // If status is success, call $refresh to ensure UI updates
        if (($status['status'] ?? '') === 'success' || ($status['status'] ?? '') === 'complete') {
            $this->dispatch('refresh');

            // Force refresh of component
            $this->reset(['jobStatus']);
            $this->jobStatus = $status;
        }

        return $status;
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
     * Navigate to the next page of fields
     */
    public function nextPage(): void
    {
        $maxPage = ceil($this->totalFields / $this->perPage);
        if ($this->currentPage < $maxPage) {
            $this->currentPage++;
            $this->dispatch('refresh');
        }
    }

    /**
     * Navigate to the previous page of fields
     */
    public function prevPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $this->dispatch('refresh');
        }
    }

    /**
     * Store field mapping selection without generating full preview
     * This reduces memory usage by only storing the minimal state
     */
    public function setMappingSelection(string $fieldId, string $selectedValue): void
    {
        // Store the mapping choice in component state
        $this->fieldMappings[$fieldId] = $selectedValue;

        // Log the selection for debugging
        logger()->debug("📍 Field mapping stored: {$fieldId} => {$selectedValue}");
    }

    /**
     * Load field details on demand when requested
     * This is called when a user toggles to view details
     */
    public function loadFieldDetails(string $fieldId, string $previewFieldName): void
    {
        $this->startLoadingFieldDetails();

        try {
            // Get the current mapping selection for this field
            $selectedValue = $this->fieldMappings[$fieldId] ?? 'new';

            if ($selectedValue === 'new') {
                // Generate preview for the import field
                $previewHtml = $this->getImportFieldDetailsForPreview($fieldId);
            } else {
                // Generate preview for existing field
                $previewHtml = $this->getExistingFieldDetailsForPreview((int)$selectedValue);
            }

            // Update the form data with the preview HTML
            $this->data[$previewFieldName] = $previewHtml;

            logger()->debug("🔍 Loaded details for field {$fieldId} with mapping {$selectedValue}");
        } catch (\Exception $e) {
            // Handle errors
            $errorHtml = '<div class="text-red-500 p-2">' .
                '<p class="font-medium">Error loading field details:</p>' .
                '<p>' . htmlspecialchars($e->getMessage()) . '</p>' .
                '</div>';

            $this->data[$previewFieldName] = $errorHtml;
            logger()->error("❌ Error loading field details: " . $e->getMessage());
        }

        $this->stopLoadingFieldDetails();
    }
}
