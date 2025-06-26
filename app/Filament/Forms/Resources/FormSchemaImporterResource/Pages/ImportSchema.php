<?php

namespace App\Filament\Forms\Resources\FormSchemaImporterResource\Pages;

use App\Filament\Forms\Helpers\SchemaFormatter;
use App\Filament\Forms\Helpers\SchemaParser;
use App\Filament\Forms\Imports\FormSchemaImporter;
use App\Filament\Forms\Resources\FormSchemaImporterResource;
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
    public array $fieldMappingOptions = [];    // Pagination properties - public for Livewire state management
    public int $currentPage = 1;
    public int $perPage = 10;
    public int $totalFields = 0;
    public array $paginatedFields = [];
    public int $schemaVersion = 1; // Cache buster for form schema
    public ?FormSchemaImportSession $importSession = null; // Current import session

    public function mount(?string $session = null): void
    {
        // Try to load an existing session first
        if ($session) {
            $this->importSession = FormSchemaImportSession::where('session_token', $session)
                ->forCurrentUser()
                ->first();

            if ($this->importSession) {
                // Restore session data
                $sessionData = $this->importSession->toImportState();

                // Restore component state
                $this->parsedSchema = $this->importSession->getParsedSchemaAttribute();
                $this->fieldMappings = $this->importSession->field_mappings ?? [];
                $this->currentPage = $sessionData['current_page'] ?? 1;
                $this->perPage = $sessionData['per_page'] ?? 10;

                // Convert field mappings to form data format
                foreach ($this->fieldMappings as $fieldId => $mappingValue) {
                    $sessionData["field_mapping_{$fieldId}"] = $mappingValue;
                }

                // Fill the form with the enhanced session data
                $this->form->fill($sessionData);

                // Ensure the data property is properly set (this is what Filament uses internally)
                if (!isset($this->data)) {
                    $this->data = [];
                }
                $this->data = array_merge($this->data, $sessionData);

                // Load form field options since we have a parsed schema
                $this->loadFormFieldOptions();

                // Mark session as in progress
                $this->importSession->markInProgress();

                Notification::make()
                    ->title('Session Resumed')
                    ->body("Resumed import session: {$this->importSession->session_name}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Session Not Found')
                    ->body('The requested import session could not be found or you do not have access to it.')
                    ->warning()
                    ->send();
            }
        }

        // Handle form_id parameter from CreateFormVersion (query string fallback)
        $formId = request()->query('form_id');
        if ($formId && !$this->importSession) {
            $form = FormModel::find($formId);
            if ($form) {
                $this->form->fill([
                    'form_id' => $form->form_id,
                    'form_title' => $form->form_title,
                    'ministry_id' => $form->ministry_id,
                    'form' => $form->id,
                    'create_new_form' => false,
                    'create_new_version' => true,
                ]);
            }
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
        $this->totalFields = 0;
        $this->schemaVersion = 1;

        // Initialize pagination control in form data
        $this->data['pagination_per_page'] = $this->perPage;

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
            // Session status info panel (only show if we have an active session)
            \Filament\Forms\Components\Section::make('Session Information')
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('session_info')
                        ->hiddenLabel()
                        ->content(function () {
                            if (!$this->importSession) {
                                return new HtmlString('<div class="text-sm text-gray-600">No active session - your progress will not be saved automatically. Use "Save Progress" to create a session.</div>');
                            }

                            $statusColor = match ($this->importSession->status) {
                                'completed' => 'text-green-600',
                                'failed' => 'text-red-600',
                                'cancelled' => 'text-orange-600',
                                'in_progress' => 'text-blue-600',
                                default => 'text-gray-600'
                            };

                            $lastActivity = $this->importSession->last_activity_at ? $this->importSession->last_activity_at->diffForHumans() : 'Unknown';
                            $progress = $this->importSession->completion_percentage;

                            return new HtmlString("
                                <div class='space-y-2'>
                                    <div class='flex items-center justify-between'>
                                        <div>
                                            <span class='font-medium'>Session:</span> {$this->importSession->session_name}
                                            <span class='ml-2 px-2 py-1 text-xs rounded-full bg-gray-100 {$statusColor}'>{$this->importSession->status_label}</span>
                                        </div>
                                        <div class='text-sm text-gray-500'>Last activity: {$lastActivity}</div>
                                    </div>
                                    <div class='w-full bg-gray-200 rounded-full h-2'>
                                        <div class='bg-blue-600 h-2 rounded-full' style='width: {$progress}%'></div>
                                    </div>
                                    <div class='text-xs text-gray-500'>Progress: {$progress}% complete</div>
                                </div>
                            ");
                        }),
                ])
                ->visible(fn() => $this->importSession !== null)
                ->collapsible()
                ->collapsed(false),

            \Filament\Forms\Components\Wizard::make([
                \Filament\Forms\Components\Wizard\Step::make('Import Source')
                    ->schema([
                        \Filament\Forms\Components\Section::make('Form Schema Source')
                            ->schema([
                                \Filament\Forms\Components\Tabs::make('source_tabs')
                                    ->tabs([
                                        \Filament\Forms\Components\Tabs\Tab::make('Upload File')
                                            ->label(fn() => $this->parsedSchema !== null ? 'Upload File (Disabled)' : 'Upload File')
                                            ->schema([
                                                \Filament\Forms\Components\FileUpload::make('schema_file')
                                                    ->label('Schema File')
                                                    ->acceptedFileTypes(['application/json'])
                                                    ->maxSize(5120)
                                                    ->helperText(function () {
                                                        return $this->parsedSchema !== null
                                                            ? 'Schema already parsed - upload disabled'
                                                            : 'Upload a JSON file with form schema (max 5MB)';
                                                    })
                                                    ->disabled(fn() => $this->parsedSchema !== null)
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
                                            ->label(fn() => $this->parsedSchema !== null ? 'Paste JSON (Disabled)' : 'Paste JSON')
                                            ->schema([
                                                \Filament\Forms\Components\Textarea::make('schema_content')
                                                    ->label('Schema Content')
                                                    ->placeholder(function () {
                                                        return $this->parsedSchema !== null
                                                            ? 'Schema already parsed - editing disabled'
                                                            : 'Paste JSON form schema here...';
                                                    })
                                                    ->disabled(fn() => $this->parsedSchema !== null)
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

                                // Status message when schema is parsed and inputs are locked
                                \Filament\Forms\Components\Section::make('Schema Locked')
                                    ->description('Schema has been parsed successfully. To change the source data, use the "Parse Schema" action to reset and parse a new schema.')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('lock_message')
                                            ->content('🔒 Schema source is now locked to prevent accidental changes. All field mappings and configurations are preserved.')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsed(false)
                                    ->collapsible(false)
                                    ->visible(fn() => $this->parsedSchema !== null),

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
                \Filament\Forms\Components\Wizard\Step::make('Form Selection')
                    ->schema([
                        \Filament\Forms\Components\Section::make('Target Form')
                            ->description('Select the existing form to create a new version for')
                            ->schema([
                                \Filament\Forms\Components\Select::make('form')
                                    ->label('Select Existing Form')
                                    ->options(\App\Models\Form::pluck('form_title', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('A new version will be created for the selected form')
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        if ($state) {
                                            $form = \App\Models\Form::find($state);
                                            if ($form) {
                                                $set('form_id', $form->form_id);
                                                $set('form_title', $form->form_title);
                                                $set('ministry_id', $form->ministry_id);
                                                // Always set to create new version
                                                $set('create_new_form', false);
                                                $set('create_new_version', true);
                                            }
                                        }
                                    }),

                                // Display fields - read-only to show what will be used
                                \Filament\Forms\Components\Grid::make(2)
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('form_id')
                                            ->label('Form ID')
                                            ->disabled()
                                            ->dehydrated()
                                            ->placeholder('Will be filled from selected form'),
                                        \Filament\Forms\Components\TextInput::make('form_title')
                                            ->label('Form Title')
                                            ->disabled()
                                            ->dehydrated()
                                            ->placeholder('Will be filled from selected form'),
                                    ]),

                                \Filament\Forms\Components\Select::make('ministry_id')
                                    ->label('Ministry')
                                    ->options(\App\Models\Ministry::pluck('name', 'id'))
                                    ->disabled()
                                    ->dehydrated()
                                    ->placeholder('Will be filled from selected form'),

                                // Hidden fields to store the values
                                \Filament\Forms\Components\Hidden::make('create_new_form')
                                    ->default(false),
                                \Filament\Forms\Components\Hidden::make('create_new_version')
                                    ->default(true),
                            ]),
                    ]),
                \Filament\Forms\Components\Wizard\Step::make('Field Mapping')
                    ->schema([
                        \Filament\Forms\Components\Section::make('Field Mapping')
                            ->description('Map fields from the imported schema to existing fields in the system')
                            ->schema($this->getFieldMappingSchema())
                            ->extraAttributes(['wire:key' => 'field-mapping-section-' . $this->schemaVersion])
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
                                    ->reactive()
                                    ->live()
                                    ->content(function () {
                                        if ($this->parsedSchema === null) {
                                            return 'No schema has been parsed yet. Please upload and parse a schema first.';
                                        }

                                        try {
                                            // Get form data including field mappings
                                            $formData = $this->data ?? [];

                                            // Extract field mapping data from form
                                            $fieldMappings = [];
                                            foreach ($formData as $key => $value) {
                                                if (str_starts_with($key, 'field_mapping_')) {
                                                    $fieldMappings[$key] = $value;
                                                }
                                            }

                                            // Safety check before generating the preview
                                            if (!is_array($this->parsedSchema)) {
                                                Log::warning('Attempted to generate preview with invalid schema type: ' . gettype($this->parsedSchema));
                                                return 'Invalid schema format. Please try uploading the file again.';
                                            }

                                            // Use new method that considers field mappings
                                            $schemaFormatter = new SchemaFormatter();
                                            $previewJson = empty($fieldMappings)
                                                ? $schemaFormatter->getImportPreviewJson($this->parsedSchema, $formData)
                                                : $schemaFormatter->getImportPreviewWithMappings($this->parsedSchema, $fieldMappings, $formData);

                                            return new HtmlString(
                                                '<pre style="background:#f9fafb;border-radius:6px;padding:1em;overflow:auto;font-size:0.95em;">' .
                                                    htmlspecialchars($previewJson) .
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
            $this->resetPagination();

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
        // Only continue if we have a parsed schema
        if ($this->parsedSchema === null) {
            return [
                \Filament\Forms\Components\Placeholder::make('no_schema')
                    ->label('No schema loaded')
                    ->content('No schema has been parsed yet. Please upload and parse a schema first.')
            ];
        }

        // Initialize SchemaFormatter for field mapping
        $schemaFormatter = new \App\Filament\Forms\Helpers\SchemaFormatter();

        // Extract all fields first for pagination
        $allFields = $this->extractFieldsFromSchema($this->parsedSchema);
        $this->totalFields = count($allFields);

        // Ensure currentPage is valid
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }

        $maxPage = $this->totalFields > 0 ? (int) ceil($this->totalFields / $this->perPage) : 1;
        if ($this->currentPage > $maxPage) {
            $this->currentPage = $maxPage;
        }

        // Apply pagination
        $start = ($this->currentPage - 1) * $this->perPage;
        $fields = array_slice($allFields, $start, $this->perPage);

        // Store the paginated fields in component state for efficient re-rendering
        $this->paginatedFields = $fields;

        // Calculate pagination info
        $paginationStart = ($this->currentPage - 1) * $this->perPage + 1;
        $paginationEnd = min($this->currentPage * $this->perPage, $this->totalFields);
        $totalPages = max(1, (int) ceil($this->totalFields / $this->perPage));

        // Handle empty fields case
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

        // Initialize schema with pagination controls at the top
        $schema = [
            \Filament\Forms\Components\Grid::make(3)
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('pagination_info')
                        ->label('')
                        ->content(function () use ($paginationStart, $paginationEnd) {
                            if ($this->totalFields === 0) {
                                return "No fields to display";
                            }
                            return "Showing {$paginationStart}-{$paginationEnd} of {$this->totalFields} fields";
                        })
                        ->extraAttributes(['wire:key' => 'pagination-info-' . $this->currentPage . '-' . $this->perPage . '-' . $this->schemaVersion]),

                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('prev_page')
                            ->label('Previous')
                            ->icon('heroicon-o-chevron-left')
                            ->color('gray')
                            ->visible(fn() => $this->currentPage > 1)
                            ->action('prevPage'),

                        \Filament\Forms\Components\Actions\Action::make('current_page')
                            ->label(function () use ($totalPages) {
                                return "Page {$this->currentPage} of {$totalPages}";
                            })
                            ->color('gray')
                            ->disabled()
                            ->extraAttributes(['wire:key' => 'current-page-top-' . $this->currentPage . '-' . $this->perPage . '-' . $this->schemaVersion]),

                        \Filament\Forms\Components\Actions\Action::make('next_page')
                            ->label('Next')
                            ->icon('heroicon-o-chevron-right')
                            ->iconPosition('after')
                            ->color('gray')
                            ->visible(function () use ($totalPages) {
                                return $this->currentPage < $totalPages;
                            })
                            ->action('nextPage'),
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
                        ->default($this->perPage)
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

        // Create a mini-schema with just the paginated fields to optimize memory usage
        $paginatedSchema = $this->parsedSchema;

        if (isset($paginatedSchema['data']) && isset($paginatedSchema['data']['elements'])) {
            $paginatedSchema['data']['elements'] = $fields;
        } elseif (isset($paginatedSchema['fields'])) {
            $paginatedSchema['fields'] = $fields;
        }

        // Use the SchemaFormatter to get the field mapping schema with our paginated fields
        // Always show previews since the toggle has been removed in favor of always-on optimized previews
        $fieldSchemaComponents = $schemaFormatter->getFieldMappingSchemaWithPreview($paginatedSchema, true);

        // Merge the pagination controls with the field schema
        $schema = array_merge($schema, $fieldSchemaComponents);

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
                            ->action('prevPage'),

                        \Filament\Forms\Components\Actions\Action::make('current_page_bottom')
                            ->label(function () use ($totalPages) {
                                return "Page {$this->currentPage} of {$totalPages}";
                            })
                            ->color('gray')
                            ->disabled()
                            ->extraAttributes(['wire:key' => 'current-page-bottom-' . $this->currentPage . '-' . $this->perPage . '-' . $this->schemaVersion]),

                        \Filament\Forms\Components\Actions\Action::make('next_page_bottom')
                            ->label('Next')
                            ->icon('heroicon-o-chevron-right')
                            ->iconPosition('after')
                            ->color('gray')
                            ->visible(function () use ($totalPages) {
                                return $this->currentPage < $totalPages;
                            })
                            ->action('nextPage'),
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
                ->label(fn() => $this->parsedSchema !== null ? 'Parse Schema (Already Parsed)' : 'Parse Schema (Queued)')
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

            Action::make('reset_import')
                ->label('Reset Import Process')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset Import Process')
                ->modalDescription('Are you sure you want to reset the entire import process? This will clear all uploaded data, field mappings, and start fresh. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Reset Everything')
                ->modalCancelActionLabel('Cancel')
                ->visible(fn() => $this->parsedSchema !== null || !empty($this->data['schema_content']))
                ->action('resetImportProcess'),

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

    public function nextPage(): void
    {
        $maxPage = max(1, (int) ceil($this->totalFields / $this->perPage));
        if ($this->currentPage < $maxPage) {
            $this->currentPage++;
            $this->schemaVersion++; // Force refresh when page changes
        }
    }

    public function prevPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $this->schemaVersion++; // Force refresh when page changes
        }
    }

    public function changePerPage(int $perPage): void
    {
        $oldPerPage = $this->perPage;
        $this->perPage = max(1, $perPage); // Ensure perPage is at least 1
        $this->currentPage = 1; // Reset to first page when changing per page

        // Update the form data to keep it in sync
        $this->data['pagination_per_page'] = $this->perPage;

        // Force a re-render of the form schema to update pagination controls and field list
        if ($oldPerPage !== $this->perPage) {
            // Increment schema version to force cache-bust
            $this->schemaVersion++;

            // Dispatch a custom event to trigger form refresh
            $this->dispatch('refresh-field-mapping');

            // Also trigger a general component refresh
            $this->js('$wire.$refresh()');
        }
    }

    /**
     * Reset pagination to initial state
     */
    public function resetPagination(): void
    {
        $this->currentPage = 1;
        $this->schemaVersion++; // Increment to force refresh
        // Don't reset perPage as user may have set their preference
    }

    /**
     * Livewire property updater for currentPage
     */
    public function updatedCurrentPage(): void
    {
        // Ensure currentPage is within valid bounds
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }

        $maxPage = $this->totalFields > 0 ? (int) ceil($this->totalFields / $this->perPage) : 1;
        if ($this->currentPage > $maxPage) {
            $this->currentPage = $maxPage;
        }
    }
    /**
     * Livewire property updater for perPage
     */
    public function updatedPerPage(): void
    {
        $this->currentPage = 1;

        // Ensure perPage is reasonable
        if ($this->perPage < 1) {
            $this->perPage = 10;
        }

        // Update the form data to keep it in sync
        $this->data['pagination_per_page'] = $this->perPage;

        // Increment schema version to force cache-bust
        $this->schemaVersion++;

        // Force refresh to update pagination display and field list
        $this->dispatch('refresh-field-mapping');
        $this->js('$wire.$refresh()');
    }



    /**
     * Store field mapping selection without generating full preview
     * This reduces memory usage by only storing the minimal state
     */

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
     */
    public function resetImportProcess(): void
    {
        try {
            // Clear all component state
            $this->data = [];
            $this->parsedSchema = null;
            $this->fieldMappings = [];
            $this->selectOptions = [];
            $this->formFieldOptions = [];
            $this->fieldDetails = [];
            $this->jobStatus = ['status' => 'idle', 'message' => ''];

            // Reset pagination
            $this->currentPage = 1;
            $this->perPage = 10;
            $this->totalFields = 0;
            $this->paginatedFields = [];
            $this->schemaVersion = 1;

            // Re-initialize pagination control in form data
            $this->data['pagination_per_page'] = $this->perPage;

            // Clear any cached job data
            if (isset($this->data['schema_import_job_id'])) {
                $jobId = $this->data['schema_import_job_id'];

                // Clean up cache entries for this job
                Cache::forget("schema_import_status_{$jobId}");
                Cache::forget("schema_structure_{$jobId}");
                Cache::forget("schema_elements_{$jobId}");

                // Clean up chunked data if it exists
                $chunksCount = Cache::get("schema_elements_chunks_{$jobId}");
                if ($chunksCount) {
                    for ($i = 0; $i < $chunksCount; $i++) {
                        Cache::forget("schema_elements_chunk_{$jobId}_{$i}");
                    }
                    Cache::forget("schema_elements_chunks_{$jobId}");
                }
            }

            // Cancel current session if exists
            if ($this->importSession) {
                $this->importSession->cancel();
                $this->importSession = null;
            }

            // Reload form field options
            $this->loadFormFieldOptions();

            // Reload cached field mapping options
            $this->fieldMappingOptions = \App\Filament\Forms\Helpers\SchemaFormatter::getAllMappingOptions(true);

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
}
