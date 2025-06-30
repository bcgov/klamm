<?php

namespace App\Filament\Forms\Helpers;

use App\Models\FormField;
use App\Models\FormSchemaImportSession;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;

/**
 * Import Form Builder
 *
 * Builds the complete form schema for the import wizard.
 * This class handles the creation of all form components including
 * wizard steps, field mapping interfaces, and dynamic content.
 */
class ImportFormBuilder
{
    private ?FormSchemaImportSession $importSession;
    private ?array $parsedSchema;
    private int $schemaVersion;
    private ?array $fieldMappingSchema = null; // Add field mapping schema storage
    private ?array $currentFieldMappings = null; // Store current field mappings for preview

    public function __construct(
        ?FormSchemaImportSession $importSession = null,
        ?array $parsedSchema = null,
        int $schemaVersion = 1
    ) {
        $this->importSession = $importSession;
        $this->parsedSchema = $parsedSchema;
        $this->schemaVersion = $schemaVersion;
    }

    /**
     * Set the current field mappings for preview generation
     *
     * @param array $mappings Current field mappings
     * @return void
     */
    public function setCurrentFieldMappings(array $mappings): void
    {
        $this->currentFieldMappings = $mappings;
    }

    /**
     * Build the complete wizard form schema
     *
     * @return array Complete form schema components
     */
    public function buildFormSchema(): array
    {
        return [
            // Session status info panel
            $this->buildSessionInfoSection(),

            // Main wizard
            Wizard::make([
                $this->buildSourceTargetStep(),
                $this->buildFieldMappingStep(),
                $this->buildPreviewStep(),
                $this->buildConfirmStep(),
            ])->skippable()->persistStepInQueryString(),
        ];
    }

    /**
     * Build the session information section
     *
     * @return Section Session info component
     */
    private function buildSessionInfoSection(): Section
    {
        return Section::make('Session Information')
            ->schema([
                Placeholder::make('session_info')
                    ->hiddenLabel()
                    ->content(function () {
                        return $this->getSessionInfoContent();
                    }),
            ])
            ->visible(fn() => $this->importSession !== null)
            ->collapsible()
            ->collapsed(false);
    }

    /**
     * Get session information content
     *
     * @return HtmlString Session status HTML
     */
    private function getSessionInfoContent(): HtmlString
    {
        if (!$this->importSession) {
            return new HtmlString(
                '<div class="text-sm text-gray-600">No active session - your progress will not be saved automatically. Use "Save Progress" to create a session.</div>'
            );
        }

        $statusColor = match ($this->importSession->status) {
            'completed' => 'text-green-600',
            'failed' => 'text-red-600',
            'cancelled' => 'text-orange-600',
            'in_progress' => 'text-blue-600',
            default => 'text-gray-600'
        };

        $lastActivity = $this->importSession->last_activity_at
            ? $this->importSession->last_activity_at->diffForHumans()
            : 'Unknown';
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
    }

    /**
     * Build the source and target step
     *
     * @return Step Source and target wizard step
     */
    private function buildSourceTargetStep(): Step
    {
        return Step::make('Import Source & Target')
            ->schema([
                $this->buildSchemaSourceSection(),
                $this->buildTargetFormSection(),
            ]);
    }

    /**
     * Build the schema source section
     *
     * @return Section Schema source form section
     */
    private function buildSchemaSourceSection(): Section
    {
        return Section::make('Form Schema Source')
            ->schema([
                $this->buildSourceTabs(),
                $this->buildSchemaLockedSection(),
                $this->buildSchemaSummarySection(),
            ]);
    }

    /**
     * Build the source input tabs
     *
     * @return Tabs Source input tabs component
     */
    private function buildSourceTabs(): Tabs
    {
        return Tabs::make('source_tabs')
            ->tabs([
                $this->buildUploadTab(),
                $this->buildPasteTab(),
            ]);
    }

    /**
     * Build the file upload tab
     *
     * @return Tab File upload tab component
     */
    private function buildUploadTab(): Tab
    {
        return Tab::make('Upload File')
            ->label(fn() => $this->parsedSchema !== null ? 'Upload File (Disabled)' : 'Upload File')
            ->schema([
                FileUpload::make('schema_file')
                    ->label('Schema File')
                    ->acceptedFileTypes(['application/json'])
                    ->maxSize(5120)
                    ->helperText(function () {
                        return $this->parsedSchema !== null
                            ? 'Schema already parsed - upload disabled'
                            : 'Upload a JSON file with form schema (max 5MB)';
                    })
                    ->disabled(fn() => $this->parsedSchema !== null)
                    ->key('schema_file_' . $this->schemaVersion)
                    ->reactive()
                    ->afterStateUpdated(function (Set $set, ?\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $state) {
                        if ($state) {
                            $content = file_get_contents($state->getRealPath());
                            $set('schema_content', $content);
                            $set('parsed_content', \App\Filament\Forms\Resources\FormSchemaImporterResource::parseSchema($content));
                        } else {
                            $set('schema_content', null);
                            $set('parsed_content', null);
                        }
                    }),
            ]);
    }

    /**
     * Build the paste JSON tab
     *
     * @return Tab Paste JSON tab component
     */
    private function buildPasteTab(): Tab
    {
        return Tab::make('Paste JSON')
            ->label(fn() => $this->parsedSchema !== null ? 'Paste JSON (Disabled)' : 'Paste JSON')
            ->schema([
                Textarea::make('schema_content')
                    ->label('Schema Content')
                    ->placeholder(function () {
                        return $this->parsedSchema !== null
                            ? 'Schema already parsed - editing disabled'
                            : 'Paste JSON form schema here...';
                    })
                    ->disabled(fn() => $this->parsedSchema !== null)
                    ->key('schema_content_' . $this->schemaVersion)
                    ->rows(15)
                    ->columnSpanFull()
                    ->reactive()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if ($state) {
                            $set('parsed_content', \App\Filament\Forms\Resources\FormSchemaImporterResource::parseSchema($state));
                        } else {
                            $set('parsed_content', null);
                        }
                    }),
            ]);
    }

    /**
     * Build the schema locked section
     *
     * @return Section Schema locked notification section
     */
    private function buildSchemaLockedSection(): Section
    {
        return Section::make('Schema Locked')
            ->description('Schema has been parsed successfully. To change the source data, use the "Parse Schema" action to reset and parse a new schema.')
            ->schema([
                Placeholder::make('lock_message')
                    ->content('🔒 Schema source is now locked to prevent accidental changes. All field mappings and configurations are preserved.')
                    ->columnSpanFull(),
            ])
            ->collapsed(false)
            ->collapsible(false)
            ->visible(fn() => $this->parsedSchema !== null);
    }

    /**
     * Build the schema summary section
     *
     * @return Section Schema summary display section
     */
    private function buildSchemaSummarySection(): Section
    {
        return Section::make('Schema Summary')
            ->schema([
                TextInput::make('parsed_content.form_id')
                    ->label('Form ID')
                    ->disabled(),
                TextInput::make('parsed_content.field_count')
                    ->label('Field Count')
                    ->disabled(),
                TextInput::make('parsed_content.container_count')
                    ->label('Container Count')
                    ->disabled(),
                TextInput::make('parsed_content.format')
                    ->label('Format')
                    ->disabled(),
            ])
            ->columns(2)
            ->visible(fn(Get $get): bool => (bool) $get('parsed_content'));
    }

    /**
     * Build the target form section
     *
     * @return Section Target form selection section
     */
    private function buildTargetFormSection(): Section
    {
        return Section::make('Target Form')
            ->description('Select the existing form to create a new version for')
            ->schema([
                Select::make('form')
                    ->label('Select Existing Form')
                    ->options(\App\Models\Form::pluck('form_title', 'id', 'form_id'))
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
                                $set('create_new_form', false);
                                $set('create_new_version', true);
                            }
                        }
                    }),

                Grid::make(2)->schema([
                    TextInput::make('form_id')
                        ->label('Form ID')
                        ->disabled()
                        ->dehydrated()
                        ->placeholder('Will be filled from selected form'),
                    TextInput::make('form_title')
                        ->label('Form Title')
                        ->disabled()
                        ->dehydrated()
                        ->placeholder('Will be filled from selected form'),
                ]),

                Select::make('ministry_id')
                    ->label('Ministry')
                    ->options(\App\Models\Ministry::pluck('name', 'id'))
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Will be filled from selected form'),

                Hidden::make('create_new_form')->default(false),
                Hidden::make('create_new_version')->default(true),
            ])
            ->columns(1);
    }

    /**
     * Build the field mapping step
     *
     * @return Step Field mapping wizard step
     */
    private function buildFieldMappingStep(): Step
    {
        return Step::make('Field Mapping')
            ->schema([
                Section::make('Field Mapping')
                    ->description('Map fields from the imported schema to existing fields in the system')
                    ->schema(function (Get $get) {
                        // Use the provided field mapping schema if available
                        if ($this->fieldMappingSchema !== null) {
                            return $this->fieldMappingSchema;
                        }

                        // Default placeholder if no schema
                        return [
                            \Filament\Forms\Components\Placeholder::make('field_mapping_placeholder')
                                ->content('Field mapping will be generated based on parsed schema')
                                ->columnSpanFull(),
                        ];
                    })
                    ->reactive()
                    ->live()
                    ->extraAttributes(['wire:key' => 'field-mapping-section-' . $this->schemaVersion])
                    ->columnSpanFull(),
            ])
            ->visible(function (Get $get) {
                return (bool) $get('parsed_content') || $this->parsedSchema !== null;
            });
    }

    /**
     * Build the preview step
     *
     * @return Step Import preview wizard step
     */
    private function buildPreviewStep(): Step
    {
        return Step::make('Import Preview')
            ->schema([
                Section::make('Preview')
                    ->schema([
                        Placeholder::make('preview')
                            ->label('Import Preview')
                            ->reactive()
                            ->live()
                            ->content(function (Get $get) {
                                return $this->generatePreviewContentFromGet($get);
                            })
                            ->columnSpanFull(),
                    ]),
            ])
            ->visible(function (Get $get) {
                return (bool) $get('parsed_content');
            });
    }

    /**
     * Generate preview content
     *
     * @return HtmlString|string Preview content
     */
    private function generatePreviewContent()
    {
        if ($this->parsedSchema === null) {
            return 'No schema has been parsed yet. Please upload and parse a schema first.';
        }

        try {
            // Create a SchemaFormatter instance to generate the preview
            $schemaFormatter = new \App\Filament\Forms\Helpers\SchemaFormatter();

            // Get current field mappings from the form data
            // If no mappings are available, show what would be created by default
            $fieldMappings = $this->currentFieldMappings ?? [];

            // Generate preview based on schema and field mappings
            $previewJson = $schemaFormatter->getImportPreviewWithMappings(
                $this->parsedSchema,
                $fieldMappings
            );

            // Format as HTML for better display
            $previewHtml = '<div class="bg-gray-50 p-4 rounded border">' .
                '<div class="mb-3"><h4 class="font-semibold text-gray-900">Import Preview</h4>' .
                '<p class="text-sm text-gray-600">This shows what will be created based on your field mappings:</p></div>' .
                '<pre class="text-xs bg-white p-3 rounded border overflow-auto max-h-96">' .
                htmlspecialchars($previewJson) .
                '</pre></div>';

            return new HtmlString($previewHtml);
        } catch (\Exception $e) {
            Log::error('Error generating import preview: ' . $e->getMessage());
            return 'Error generating preview: ' . $e->getMessage();
        }
    }

    /**
     * Generate preview content from form Get callable (reactive)
     *
     * @param Get $get Form Get callable to access current form state
     * @return HtmlString|string Preview content
     */
    private function generatePreviewContentFromGet(Get $get)
    {
        if ($this->parsedSchema === null) {
            return 'No schema has been parsed yet. Please upload and parse a schema first.';
        }

        try {
            // Create a SchemaFormatter instance to generate the preview
            $schemaFormatter = new \App\Filament\Forms\Helpers\SchemaFormatter();

            // Extract current field mappings from form state using Get callable
            $currentFieldMappings = [];
            $allFormData = $get('.'); // Get all form data

            if (is_array($allFormData)) {
                foreach ($allFormData as $key => $value) {
                    if (str_starts_with($key, 'field_mapping_')) {
                        $currentFieldMappings[$key] = $value;
                    }
                }
            }

            // Generate preview based on schema and current field mappings
            $previewJson = $schemaFormatter->getImportPreviewWithMappings(
                $this->parsedSchema,
                $currentFieldMappings
            );

            // Format as HTML for better display
            $previewHtml = '<div class="bg-gray-50 p-4 rounded border">' .
                '<div class="mb-3"><h4 class="font-semibold text-gray-900">Import Preview</h4>' .
                '<p class="text-sm text-gray-600">This shows what will be created based on your current field mappings:</p></div>' .
                '<pre class="text-xs bg-white p-3 rounded border overflow-auto max-h-96">' .
                htmlspecialchars($previewJson) .
                '</pre></div>';

            return new HtmlString($previewHtml);
        } catch (\Exception $e) {
            Log::error('Error generating reactive import preview: ' . $e->getMessage());
            return 'Error generating preview: ' . $e->getMessage();
        }
    }

    /**
     * Build the confirmation step
     *
     * @return Step Final confirmation wizard step
     */
    private function buildConfirmStep(): Step
    {
        return Step::make('Confirm Import')
            ->schema([
                Section::make('Final Confirmation')
                    ->schema([
                        Toggle::make('confirm_import')
                            ->label('I confirm this import')
                            ->required()
                            ->helperText('Please confirm you want to proceed with this import')
                            ->live(),

                        Actions::make([
                            Action::make('import_schema')
                                ->label('Import Schema')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('success')
                                ->size('lg')
                                ->hidden(fn(Get $get) => !$get('confirm_import') || empty($this->parsedSchema))
                                ->action('import')
                                ->requiresConfirmation()
                                ->modalHeading('Confirm Schema Import')
                                ->modalDescription('Are you sure you want to import this schema? This will create a new form version with the mapped fields.')
                                ->modalSubmitActionLabel('Yes, Import Schema'),
                        ]),
                    ]),
            ]);
    }

    /**
     * Update the parsed schema state
     *
     * @param array|null $parsedSchema New parsed schema
     * @return void
     */
    public function setParsedSchema(?array $parsedSchema): void
    {
        $this->parsedSchema = $parsedSchema;
    }

    /**
     * Update the schema version for cache busting
     *
     * @param int $schemaVersion New schema version
     * @return void
     */
    public function setSchemaVersion(int $schemaVersion): void
    {
        $this->schemaVersion = $schemaVersion;
    }

    /**
     * Update the import session
     *
     * @param FormSchemaImportSession|null $importSession New import session
     * @return void
     */
    public function setImportSession(?FormSchemaImportSession $importSession): void
    {
        $this->importSession = $importSession;
    }

    /**
     * Set the field mapping schema
     *
     * @param array|null $fieldMappingSchema Field mapping schema components
     * @return void
     */
    public function setFieldMappingSchema(?array $fieldMappingSchema): void
    {
        $this->fieldMappingSchema = $fieldMappingSchema;
    }
}
