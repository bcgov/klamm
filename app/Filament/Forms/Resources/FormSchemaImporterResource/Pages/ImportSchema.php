<?php

namespace App\Filament\Forms\Resources\FormSchemaImporterResource\Pages;

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

        // Load form field options for mapping
        $this->loadFormFieldOptions();
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
                                    ->content(fn() => new HtmlString(
                                        '<pre style="background:#f9fafb;border-radius:6px;padding:1em;overflow:auto;font-size:0.95em;">' .
                                            htmlspecialchars($this->getImportPreviewJson()) .
                                            '</pre>'
                                    ))
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
            $json = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Notification::make()
                    ->title('Invalid JSON')
                    ->body('The schema is not valid JSON: ' . json_last_error_msg())
                    ->danger()
                    ->send();

                return;
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

            // Extract fields and build mappings
            $this->extractFields();

            // Initialize field properties in data array to prevent Livewire Entangle errors
            $this->initializeFieldProperties();

            $fieldCount = count($this->fieldMappings);

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
     * Extract fields from the schema for mapping
     */
    protected function extractFields(): void
    {
        $this->fieldMappings = [];

        // Handle the new format that has a data.elements structure
        if (isset($this->parsedSchema['data']['elements'])) {
            $this->extractFieldsRecursively($this->parsedSchema['data']['elements']);
        }
        // Handle older format with fields directly
        elseif (isset($this->parsedSchema['fields'])) {
            $this->extractFieldsRecursively($this->parsedSchema['fields']);
        }
    }

    /**
     * Extract fields recursively from the schema
     * @param array $elements - The elements to process
     */
    protected function extractFieldsRecursively(array $elements): void
    {
        foreach ($elements as $element) {
            // If this is a container with child elements, process recursively
            if (isset($element['elementType']) && $element['elementType'] === 'ContainerFormElements' && isset($element['elements'])) {
                $this->extractFieldsRecursively($element['elements']);
            }
            // If this is a field (not a container), add to mappings
            elseif (isset($element['elementType']) && $element['elementType'] !== 'ContainerFormElements') {
                $id = $element['token'] ?? md5(json_encode($element));
                $this->fieldMappings[$id] = 'new';

                // For select fields, extract options if available
                if (
                    isset($element['dataFormat']) && in_array($element['dataFormat'], ['dropdown', 'radio', 'checkbox', 'select'])
                    && isset($element['options'])
                ) {
                    $this->selectOptions[$id] = $element['options'];
                }
            }
        }
    }

    /**
     * Recursively extract all fields (not containers) from the parsed schema JSON.
     */
    protected function extractFieldsFromSchema(array $elements, array &$result = []): array
    {
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

    public function getFieldMappingSchema(): array
    {
        // Log current state for debugging
        logger()->debug("🔄 Building field mapping schema. Data keys: " . json_encode(array_keys($this->data)));
        logger()->debug("📊 Parsed schema has " . (isset($this->parsedSchema['data']['elements']) ? count($this->parsedSchema['data']['elements']) : 0) . " elements");

        $schema = [];
        $fields = [];

        // Handle new format with data.elements
        if (isset($this->parsedSchema['data']) && isset($this->parsedSchema['data']['elements'])) {
            $fields = $this->extractFieldsFromSchema($this->parsedSchema['data']['elements']);
        }
        // Handle older format with fields directly
        elseif (isset($this->parsedSchema['fields'])) {
            $fields = $this->extractFieldsFromSchema($this->parsedSchema['fields']);
        }

        if (empty($fields)) {
            return [
                \Filament\Forms\Components\Placeholder::make('no_fields')
                    ->label('No fields found')
                    ->content('No fields were found in the schema or schema has not been parsed yet.')
            ];
        }

        foreach ($fields as $index => $field) {
            // Generate stable field ID based on the available field identifier
            $fieldId = $field['token'] ?? $field['id'] ?? md5($field['name'] ?? "field_$index");
            $selectFieldName = "field_mapping_{$fieldId}";
            $previewFieldName = "mapping_preview_{$fieldId}";

            // Extract field properties from either format
            $label = $field['label'] ?? '';
            $name = $field['name'] ?? '';

            // Type determination based on format
            $type = '';
            if (isset($field['elementType'])) {
                $type = $field['elementType'];
                // Additional details for data format if available
                if (isset($field['dataFormat'])) {
                    $type .= " ({$field['dataFormat']})";
                }
            } else {
                $type = $field['type'] ?? 'text-input';
            }

            // Handle repeating fields in either format
            $repeating = false;
            if (isset($field['repeats'])) {
                $repeating = $field['repeats'];
            } elseif (isset($field['repeating'])) {
                $repeating = $field['repeating'];
            } elseif (isset($field['is_repeating'])) {
                $repeating = $field['is_repeating'];
            }

            // Extract other common properties
            $helpText = $field['help_text'] ?? $field['helpText'] ?? '';
            $description = $field['description'] ?? '';
            $validations = [];

            // Extract validations from either format
            if (isset($field['validations'])) {
                $validations = $field['validations'];
            } elseif (isset($field['validation'])) {
                $validations = $field['validation'];
            }

            $validationsText = is_array($validations) ? json_encode($validations) : '';

            // Get mapping options with field details
            $options = $this->getMappingOptionsWithDetails($type, $label, $name, $repeating);

            // Extract options for select-type fields
            $fieldOptions = '';
            if (isset($field['options'])) {
                $fieldOptions = collect($field['options'])->map(function ($opt) {
                    return is_array($opt)
                        ? ($opt['label'] ?? $opt['name'] ?? $opt['value'] ?? json_encode($opt))
                        : $opt;
                })->implode(', ');
            }

            // Generate a better formatted HTML overview of the imported field
            $importFieldDetailsHtml = $this->generateImportFieldOverview($field);

            // Create schema components for this field
            $schema[] = \Filament\Forms\Components\Card::make()
                ->schema([

                    // ✅ Placeholder with HTML content overview
                    \Filament\Forms\Components\Placeholder::make("import_field_overview_{$fieldId}")
                        ->label('Import Field Overview')
                        ->content(fn() => new HtmlString($importFieldDetailsHtml)),

                    // ✅ Main Select Field - Actual Logic
                    \Filament\Forms\Components\Select::make($selectFieldName)
                        ->label('Map to Existing Field or Create New')
                        ->searchable()
                        ->searchPrompt('Search by ID, name, or label...')
                        ->placeholder('Select a field or create new')
                        ->optionsLimit(100)
                        ->default('new')
                        ->reactive()
                        ->afterStateHydrated(function ($state, Set $set, \Livewire\Component $livewire) use ($selectFieldName, $previewFieldName, $fieldId) {
                            // Set default to 'new' and set preview HTML for new field
                            if ($state === null || $state === 'new') {
                                $set($selectFieldName, 'new');
                                if (method_exists($livewire, 'getImportFieldDetailsForPreview')) {
                                    $previewHtml = $livewire->getImportFieldDetailsForPreview($fieldId);
                                    $set($previewFieldName, $previewHtml);
                                }
                            }
                        })
                        ->getSearchResultsUsing(function ($search) {
                            $options = [
                                'new' => 'Create New Field',
                            ];
                            $query = \App\Models\FormField::with('dataType');
                            if ($search) {
                                $query->where(function ($q) use ($search) {
                                    $q->where('name', 'like', "%{$search}%")
                                        ->orWhere('label', 'like', "%{$search}%")
                                        ->orWhereRaw('CAST(id AS CHAR) LIKE ?', ["%{$search}%"]);
                                });
                            }
                            $fields = $query->orderBy('label')->orderBy('id')->get();
                            foreach ($fields as $field) {
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
                                $optionLabel = "<span style='display:flex;align-items:center;'>" .
                                    "<span style='color:#666;min-width:50px;'>#$field->id</span>" .
                                    "<strong style='margin-right:8px;'>{$field->label}</strong> " .
                                    "<span style='color:#777;margin-right:8px;'>({$field->name})</span>" .
                                    "<span style='color:#444;background:#f3f4f6;padding:2px 6px;border-radius:4px;'>" .
                                    "$typeIcon $dataType</span>" .
                                    "</span>";
                                $options[(string)$field->id] = $optionLabel;
                            }
                            return $options;
                        })
                        ->live(debounce: 0) // Ensure immediate updates without debounce
                        ->preload() // Preloads the options for faster performance
                        ->allowHtml() // Allow HTML in the options for better formatting
                        ->selectablePlaceholder(false) // Makes placeholder non-selectable
                        ->helperText('Choose "Create New" or search for an existing field by name, ID or label')
                        ->afterStateUpdated(function ($state, Set $set, \Livewire\Component $livewire) use ($previewFieldName, $fieldId) {
                            $livewire->startLoadingFieldDetails();
                            try {
                                if ($state === 'new') {
                                    if (method_exists($livewire, 'getImportFieldDetailsForPreview')) {
                                        $previewHtml = $livewire->getImportFieldDetailsForPreview($fieldId);
                                        $set($previewFieldName, $previewHtml);
                                    } else {
                                        $set($previewFieldName, 'Field details will appear here when a field is selected');
                                    }
                                } else {
                                    if (method_exists($livewire, 'getExistingFieldDetailsForPreview')) {
                                        $previewHtml = $livewire->getExistingFieldDetailsForPreview((int)$state);
                                        $set($previewFieldName, $previewHtml);
                                    } else {
                                        // fallback to plain text
                                        $details = $livewire->getFormFieldDetails((int)$state);
                                        $formattedDetails = collect($details)->map(function ($value, $key) {
                                            if ($value === '') {
                                                return "**{$key}**";
                                            }
                                            return "**{$key}:** {$value}";
                                        })->implode("\n");
                                        $set($previewFieldName, $formattedDetails);
                                    }
                                }
                            } catch (\Exception $e) {
                                $set($previewFieldName, "**❌ Error:** " . $e->getMessage());
                            }
                            $livewire->stopLoadingFieldDetails();
                        })
                        ->options(function (?string $search = null) {
                            $options = [
                                'new' => 'Create New Field',
                            ];
                            $query = \App\Models\FormField::with('dataType');
                            if ($search) {
                                $query->where(function ($q) use ($search) {
                                    $q->where('name', 'like', "%{$search}%")
                                        ->orWhere('label', 'like', "%{$search}%")
                                        ->orWhereRaw('CAST(id AS CHAR) LIKE ?', ["%{$search}%"]);
                                });
                            }
                            // Remove or increase the limit to avoid missing fields
                            $fields = $query->orderBy('label')->orderBy('id')->get();
                            foreach ($fields as $field) {
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
                                $optionLabel = "<span style='display:flex;align-items:center;'>" .
                                    "<span style='color:#666;min-width:50px;'>#$field->id</span>" .
                                    "<strong style='margin-right:8px;'>{$field->label}</strong> " .
                                    "<span style='color:#777;margin-right:8px;'>({$field->name})</span>" .
                                    "<span style='color:#444;background:#f3f4f6;padding:2px 6px;border-radius:4px;'>" .
                                    "$typeIcon $dataType</span>" .
                                    "</span>";
                                $options[(string)$field->id] = $optionLabel;
                            }
                            return $options;
                        }),

                    // ✅ Field Preview Output (render as HTML)
                    \Filament\Forms\Components\Placeholder::make("preview_placeholder_{$fieldId}")
                        ->label('Selected Field Details 🔍')
                        ->content(fn(Get $get) => new HtmlString($get($previewFieldName) ?: '<div class="text-gray-500 italic">Field details will appear here when a field is selected</div>'))
                        ->columnSpanFull(),
                ]);
        }

        return $schema;
    }


    /**
     * Get mapping options for a field: existing fields (by label/type) or 'new', with details for preview.
     */
    protected function getMappingOptionsWithDetails($type, $label, $name, $repeating = false): array
    {
        // Map the field type from import format to system format
        $mappedType = $this->mapFieldType($type);

        // Get all form fields with all needed relationships eager loaded to avoid lazy loading errors
        $allFields = \App\Models\FormField::with([
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

        logger()->debug("📋 Field mapping options created: " . count($options) . " options available for selection");
        return $options;
    }

    /**
     * Map field types to system data types
     */
    protected function mapFieldType($type): string
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
                ->label('Parse Schema')
                ->action('parseSchema')
                ->color('gray'),

            Action::make('import')
                ->label('Import Schema')
                ->action('import')
                ->color('success')
                ->disabled(fn() => empty($this->parsedSchema)),
        ];
    }

    /**
     * Initialize data array with field properties after schema parsing
     * This fixes the "Livewire Entangle Error" by ensuring all dynamic fields have data properties
     */
    protected function initializeFieldProperties(): void
    {
        $fields = [];

        // Handle new format with data.elements
        if (isset($this->parsedSchema['data']) && isset($this->parsedSchema['data']['elements'])) {
            $fields = $this->extractFieldsFromSchema($this->parsedSchema['data']['elements']);
        }
        // Handle older format with fields directly
        elseif (isset($this->parsedSchema['fields'])) {
            $fields = $this->extractFieldsFromSchema($this->parsedSchema['fields']);
        }

        // Initialize data properties for all fields
        foreach ($fields as $index => $field) {
            // Generate stable field ID based on available field identifier
            $fieldId = $field['token'] ?? $field['id'] ?? md5($field['name'] ?? "field_$index");
            $selectFieldName = "field_mapping_{$fieldId}";
            $previewFieldName = "mapping_preview_{$fieldId}";
            $debugSelectName = "debug_select_{$fieldId}";

            // Initialize select field with 'new' default
            $this->data[$selectFieldName] = 'new';

            // Initialize preview field with empty string
            $this->data[$previewFieldName] = 'Field details will appear here when a field is selected';

            // Initialize debug select field
            $this->data[$debugSelectName] = null;

            // Log to debug console for visibility
            logger()->debug("📝 Initialized field properties: {$selectFieldName}, {$previewFieldName}");
        }
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
     * Set loading state when fetching field details
     */
    public function startLoadingFieldDetails()
    {
        $this->loadingFieldDetails = true;
    }

    /**
     * Set loading state to false when field details are loaded
     */
    public function stopLoadingFieldDetails(): void
    {
        $this->loadingFieldDetails = false;
    }

    /**
     * Check if field details are currently loading
     */
    public function isLoadingFieldDetails(): bool
    {
        return $this->loadingFieldDetails ?? false;
    }

    /**
     * Generate a rich HTML overview of the imported field
     * This replaces the older flat table approach with a more structured and visually appealing display
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
                    ? ($opt['label'] ?? $opt['name'] ?? $opt['value'] ?? json_encode($opt))
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
                    $additional[$key] = $value;
                }
            }
        }

        if (!empty($additional)) {
            $fieldDetails['⚙️ Additional Properties'] = $additional;
        }

        // Generate HTML with collapsible sections for better organization
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
                    $html .= '<td class="py-2 px-4 text-gray-800">' . htmlspecialchars($value) . '</td>';
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
                    ? $field->validations->map(fn($v) => "$v->type: $v->value")->join(', ')
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
                    $label = $opt->label ?? $opt->value ?? '';
                    $value = $opt->value ?? '';
                    $optionsHtml .= '<li><span class="font-medium">' . htmlspecialchars($label) . '</span>';
                    if ($value !== '' && $value !== $label) {
                        $optionsHtml .= ' <span class="text-gray-500">(' . htmlspecialchars($value) . ')</span>';
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

        // Render as HTML (same as generateImportFieldOverview)
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
                    $html .= '<td class="py-2 px-4 text-gray-800">' . htmlspecialchars($value) . '</td>';
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
     * Generate a simplified JSON preview of the imported form
     */
    public function getImportPreviewJson(): string
    {
        // If no schema, show message
        if (!$this->parsedSchema) {
            return json_encode(['error' => 'No schema loaded'], JSON_PRETTY_PRINT);
        }

        // Gather basic form info
        $formId = $this->data['form_id'] ?? $this->parsedSchema['form_id'] ?? null;
        $formTitle = $this->data['form_title'] ?? $this->parsedSchema['title'] ?? null;
        $ministryId = $this->data['ministry_id'] ?? null;

        // Gather mapped fields
        $fields = [];
        $importFields = [];
        if (isset($this->parsedSchema['data']) && isset($this->parsedSchema['data']['elements'])) {
            $importFields = $this->extractFieldsFromSchema($this->parsedSchema['data']['elements']);
        } elseif (isset($this->parsedSchema['fields'])) {
            $importFields = $this->extractFieldsFromSchema($this->parsedSchema['fields']);
        }

        foreach ($importFields as $index => $importField) {
            $fieldId = $importField['token'] ?? $importField['id'] ?? md5($importField['name'] ?? "field_$index");
            $mappingKey = "field_mapping_{$fieldId}";
            $mapping = $this->data[$mappingKey] ?? 'new';

            // If mapped to existing, show a summary of the mapping
            if ($mapping !== 'new') {
                // Try to get the existing field
                $existing = \App\Models\FormField::find($mapping);
                $fields[] = [
                    'import_field' => $importField['label'] ?? $importField['name'] ?? $fieldId,
                    'mapped_to' => $existing ? [
                        'id' => $existing->id,
                        'name' => $existing->name,
                        'label' => $existing->label,
                        'type' => $existing->dataType->name ?? null,
                    ] : $mapping,
                    'action' => 'map_existing',
                ];
            } else {
                // Show what will be created
                $fields[] = [
                    'import_field' => $importField['label'] ?? $importField['name'] ?? $fieldId,
                    'type' => $importField['elementType'] ?? $importField['type'] ?? null,
                    'action' => 'create_new',
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
