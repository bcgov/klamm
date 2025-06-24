<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormSchemaImporterResource\Pages;
use App\Models\Form as FormModel;
use App\Models\Ministry;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FormSchemaImporterResource extends Resource
{
    protected static ?string $model = FormModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Form Administration';

    protected static ?string $navigationLabel = 'Form Schema Import';

    protected static ?string $slug = 'form-schema-import';

    protected static bool $shouldRegisterNavigation = false;

    public static function getFormSchema(): array
    {
        return [
            Wizard::make([
                Step::make('Import Source')
                    ->schema([
                        Section::make('Form Schema Source')
                            ->schema([
                                Tabs::make('source_tabs')
                                    ->tabs([
                                        Tab::make('Upload File')
                                            ->schema([
                                                FileUpload::make('schema_file')
                                                    ->label('Schema File')
                                                    ->acceptedFileTypes(['application/json'])
                                                    ->maxSize(5120) // 5MB
                                                    ->helperText('Upload a JSON file with form schema (max 5MB)')
                                                    ->reactive()
                                                    ->afterStateUpdated(function (Set $set, ?TemporaryUploadedFile $state) {
                                                        if ($state) {
                                                            $content = file_get_contents($state->getRealPath());
                                                            $set('schema_content', $content);
                                                            $set('parsed_content', self::parseSchema($content));
                                                        } else {
                                                            $set('schema_content', null);
                                                            $set('parsed_content', null);
                                                        }
                                                    }),
                                            ]),

                                        Tab::make('Paste JSON')
                                            ->schema([
                                                Textarea::make('schema_content')
                                                    ->label('Schema Content')
                                                    ->placeholder('Paste JSON form schema here...')
                                                    ->rows(15)
                                                    ->columnSpanFull()
                                                    ->reactive()
                                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                                        if ($state) {
                                                            $set('parsed_content', self::parseSchema($state));
                                                        } else {
                                                            $set('parsed_content', null);
                                                        }
                                                    }),
                                            ]),
                                    ]),

                                Section::make('Schema Summary')
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
                                    ->visible(fn(Get $get): bool => (bool) $get('parsed_content')),
                            ]),
                    ]),

                Step::make('Form Details')
                    ->schema([
                        Section::make('Form Configuration')
                            ->schema([
                                TextInput::make('form_id')
                                    ->label('Form ID')
                                    ->required()
                                    ->default(fn(Get $get) => $get('parsed_content.form_id'))
                                    ->maxLength(255),

                                TextInput::make('form_title')
                                    ->label('Form Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->default(fn(Get $get) => $get('parsed_content.title')),

                                Select::make('ministry_id')
                                    ->label('Ministry')
                                    ->options(Ministry::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload(),

                                Select::make('form')
                                    ->label('Use existing form?')
                                    ->options(FormModel::pluck('form_title', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Select an existing form or create a new one'),
                            ])
                            ->columns(2),

                        Section::make('Import Options')
                            ->schema([
                                Toggle::make('create_new_form')
                                    ->label('Create a new form if one doesn\'t exist')
                                    ->default(true)
                                    ->reactive(),

                                Toggle::make('create_new_version')
                                    ->label('Create a new form version')
                                    ->default(true)
                                    ->reactive(),
                            ])
                            ->columns(2),
                    ]),

                Step::make('Field Mapping')
                    ->schema([
                        Section::make('Field Mapping')
                            ->description('Map fields from the imported schema to existing fields in the system')
                            ->schema([
                                // Dynamic content will be filled in by the Livewire component
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(function (Get $get) {
                        // Only show step if parsed content exists
                        return (bool) $get('parsed_content');
                    }),

                Step::make('Import Preview')
                    ->schema([
                        Section::make('Preview')
                            ->schema([
                                Textarea::make('preview')
                                    ->label('Import Preview')
                                    ->disabled()
                                    ->rows(20)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->visible(function (Get $get) {
                        // Only show step if parsed content exists
                        return (bool) $get('parsed_content');
                    }),

                Step::make('Confirm Import')
                    ->schema([
                        Section::make('Final Confirmation')
                            ->schema([
                                Toggle::make('confirm_import')
                                    ->label('I confirm this import')
                                    ->required()
                                    ->helperText('Please confirm you want to proceed with this import'),
                            ]),
                    ]),
            ])
                ->skippable()
                ->persistStepInQueryString(),
        ];
    }

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

            // Handle import format
            if (isset($data['data']) && isset($data['data']['elements'])) {
                $countElements($data['data']['elements']);
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => $fieldCount,
                    'container_count' => $containerCount,
                    'format' => 'adze-template',
                ];
            }
            // Handle old format
            elseif (isset($data['fields'])) {
                $countElements($data['fields']);
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => $fieldCount,
                    'container_count' => $containerCount,
                    'format' => $data['format'] ?? 'legacy',
                ];
            } else {
                // Unknown format
                return [
                    'form_id' => $data['form_id'] ?? null,
                    'title' => $data['title'] ?? null,
                    'field_count' => 0,
                    'container_count' => 0,
                    'format' => 'unknown',
                ];
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('form_id')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('form_title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ministry.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('formVersions_count')
                    ->counts('formVersions')
                    ->label('Versions')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('import')
                    ->label('Import Schema')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(fn(FormModel $record): string => static::getUrl('import', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchemaImport::route('/'),
            'import' => Pages\ImportSchema::route('/import/{record?}'),
        ];
    }
}
