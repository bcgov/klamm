<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Filament\Components\FormVersionBuilder;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\FormElement;
use App\Models\FormBuilding\FormElementTag;
use App\Models\FormMetadata\FormDataSource;
use App\Jobs\GenerateFormVersionJsonJob;
use App\Events\FormVersionUpdateEvent;
use App\Filament\Forms\Resources\FormResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Form;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\ActionSize;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Set;
use Filament\Forms\Components\FileUpload;
use App\Filament\Forms\Helpers\SchemaParser;
use Filament\Forms\Components\Wizard;
use App\Jobs\ImportFormVersionElementsJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Placeholder;

class BuildFormVersion extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = FormVersionResource::class;

    private ?array $parsedSchema = null;
    private ?array $parsedContent = null; // Store parsed content for preview

    private ?array $fieldMappingSchema = null; // Add field mapping schema storage
    private ?array $currentFieldMappings = null; // Store current field mappings for preview

    protected static string $view = 'filament.forms.resources.form-version-resource.pages.build-form-version';

    public array $data = [];
    public array $importWizard = []; // <-- Add this
    public array $importJobStatus = [
        'status' => null,
        'done' => false,
        'cacheKey' => null,
    ];

    protected function isEditable(): bool
    {
        return $this->record->status === 'draft';
    }

    protected function getFormattedStatusName(): string
    {
        return $this->record->getFormattedStatusName();
    }

    public function mount(int | string $record): void
    {
        if (!Gate::allows('form-developer')) {
            abort(403, 'Unauthorized. Only form developers can access the form builder.');
        }

        $this->record = $this->resolveRecord($record);
        $this->form->fill($this->mutateFormDataBeforeFill([]));
    }

    protected function shouldShowTooltips(): bool
    {
        $user = Auth::user();
        return $user && $user->tooltips_enabled;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Add notification banner for read-only mode
                ...((!$this->isEditable()) ? [
                    Placeholder::make('readonly_notice')
                        ->label('')
                        ->content(new HtmlString('
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                                <div class="flex">
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-amber-800">
                                            Read-Only Mode
                                        </h3>
                                        <div class="mt-2 text-sm text-amber-700">
                                            <p>This form version is <strong>' . $this->getFormattedStatusName() . '</strong> and cannot be edited. Only form versions in <strong>Draft</strong> status can be modified.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        '))
                        ->columnSpanFull(),
                ] : []),
                FormVersionBuilder::schema($this->isEditable())
            ])
            ->live()
            ->reactive()
            ->statePath('data')
            ->model($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_form_element')
                ->label('Add Form Element')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->outlined()
                ->visible($this->isEditable())
                ->form($this->getFormElementSchema())
                ->action(function (array $data) {
                    try {
                        $data['form_version_id'] = $this->record->id;

                        // Capture the template ID before removing it
                        $templateId = $data['template_id'] ?? null;
                        // Remove template_id as it's only used for prefilling
                        unset($data['template_id']);

                        // Extract tags data before creating the element
                        $tagIds = $data['tags'] ?? [];
                        unset($data['tags']);

                        // Extract data bindings data before creating the element
                        $dataBindingsData = $data['dataBindings'] ?? [];
                        unset($data['dataBindings']);

                        // Extract polymorphic data
                        $elementType = $data['elementable_type'];
                        $elementableData = $data['elementable_data'] ?? [];
                        unset($data['elementable_data']);

                        // Filter out null values from elementable data to let model defaults apply
                        $elementableData = array_filter($elementableData, function ($value) {
                            return $value !== null;
                        });

                        // Set source_element_id if created from template
                        if ($templateId) {
                            $data['source_element_id'] = $templateId;
                        }

                        // Create the polymorphic model first if there's data
                        $elementableModel = null;
                        if (!empty($elementableData) && class_exists($elementType)) {
                            $elementableModel = $elementType::create($elementableData);
                        } elseif (empty($elementableData) && class_exists($elementType)) {
                            // Create with empty array to trigger model defaults
                            $elementableModel = $elementType::create([]);
                        }

                        // Set the polymorphic relationship data
                        if ($elementableModel) {
                            $data['elementable_type'] = $elementType;
                            $data['elementable_id'] = $elementableModel->id;
                        }

                        // Create the main FormElement
                        $formElement = FormElement::create($data);

                        // Attach tags if any were selected
                        if (!empty($tagIds)) {
                            $formElement->tags()->attach($tagIds);
                        }

                        // Create data bindings if any were provided
                        if (!empty($dataBindingsData)) {
                            foreach ($dataBindingsData as $index => $bindingData) {
                                if (isset($bindingData['form_data_source_id']) && isset($bindingData['path'])) {
                                    \App\Models\FormBuilding\FormElementDataBinding::create([
                                        'form_element_id' => $formElement->id,
                                        'form_data_source_id' => $bindingData['form_data_source_id'],
                                        'path' => $bindingData['path'],
                                        'order' => $index + 1,
                                    ]);
                                }
                            }
                        }

                        // Fire update event for element creation
                        FormVersionUpdateEvent::dispatch(
                            $this->record->id,
                            $this->record->form_id,
                            $this->record->version_number,
                            ['created_element' => $formElement->toArray()],
                            'element_created',
                            false
                        );

                        $this->getSavedNotification('Form element created successfully!')?->send();

                        // Refresh the page to update the tree
                        $this->redirect($this->getResource()::getUrl('build', ['record' => $this->record]));
                    } catch (\InvalidArgumentException $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Cannot Create Element')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Error Creating Element')
                            ->body('An unexpected error occurred: ' . $e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Actions\Action::make('import_form_template')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->outlined()
                ->modalHeading('Import Form Template')
                ->modalDescription('Upload a JSON template to import form elements and structure.')
                ->form([
                    Wizard::make([
                        Wizard\Step::make('Upload Template')
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
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, ?\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $state) {
                                        if ($state) {
                                            $content = file_get_contents($state->getRealPath());
                                            $set('schema_content', $content);
                                            $parsed = SchemaParser::parseSchema($content);
                                            $set('parsed_content', json_encode($parsed));
                                            Log::debug('Wizard afterStateUpdated', [
                                                'schema_content' => $content,
                                                'parsed_content' => $parsed,
                                            ]);
                                            $this->importWizard = [
                                                'schema_content' => $content,
                                                'parsed_content' => $parsed,
                                            ];
                                        } else {
                                            $set('schema_content', null);
                                            $set('parsed_content', null);
                                        }
                                    }),
                            ]),
                        Wizard\Step::make('Preview & Import')
                            ->schema([
                                Forms\Components\Textarea::make('schema_content')
                                    ->label('Schema Content')
                                    ->rows(10)
                                    ->disabled()
                                    ->helperText('This is the raw JSON content of the uploaded schema file.'),
                                Forms\Components\Textarea::make('parsed_content')
                                    ->label('Parsed Schema')
                                    ->rows(10)
                                    ->disabled()
                                    ->formatStateUsing(fn($state) => \App\Filament\Forms\Resources\FormVersionResource\Pages\BuildFormVersion::formatJsonForTextarea($state))
                                    ->helperText('This is the parsed schema structure.'),
                            ]),
                        Wizard\Step::make('Import Elements')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('import_elements_info')
                                    ->content('Click the button below to import all parsed elements into this form version.'),

                            ]),
                    ])
                        ->statePath('importWizard') // <-- Change from 'data' to 'importWizard'
                ])
                ->action(function (array $data) {
                    try {
                        $this->importParsedSchemaElements();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Import Failed')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            ActionGroup::make([
                $this->makeDownloadJsonAction('download_json', 'Version 2.0 (Latest)', 2),
                $this->makeDownloadJsonAction('download_old_json', 'Version 1.0', 1),
            ])
                ->label('Download JSON')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size(ActionSize::Small)
                ->color('info')
                ->button(),
            Actions\Action::make('Preview Form')
                ->label('Preview')
                ->icon('heroicon-o-tv')
                ->action(function ($livewire) {
                    $formVersionId = $this->record->id;
                    $previewBaseUrl = env('FORM_PREVIEW_URL', '');
                    $previewUrl = rtrim($previewBaseUrl, '/') . '/preview/' . $formVersionId;
                    $livewire->js("window.open('$previewUrl', '_blank')");
                })
                ->color('primary'),
        ];
    }

    /**
     * Import a form template (JSON) and create elements recursively.
     * Supports both old and new template formats.
     */
    protected function importFormTemplate(array $template): void
    {
        // Determine format: new (data/elements) or old (fields)
        if (isset($template['data']['elements'])) {
            $elements = $template['data']['elements'];
        } elseif (isset($template['fields'])) {
            $elements = $template['fields'];
        } else {
            throw new \Exception('Template format not recognized.');
        }
        $this->importElementsRecursive($elements, null);
    }

    /**
     * Recursively import elements and create FormElement records.
     * @param array $elements
     * @param int|null $parentId
     */
    protected function importElementsRecursive(array $elements, $parentId = null): void
    {
        foreach ($elements as $element) {
            $type = $this->resolveElementableType($element['elementType'] ?? $element['type'] ?? '');
            if (!$type) {
                Log::error('Unknown elementable_type', ['elementType' => $element['elementType'] ?? $element['type'] ?? '', 'element' => $element]);
                continue; // Skip unknown types
            }
            $attributes = $this->extractElementAttributes($element);

            // Prepare options for select/radio
            $options = [];
            if (!empty($element['listItems']) && is_array($element['listItems'])) {
                $options = $element['listItems'];
            } elseif (!empty($element['options']) && is_array($element['options'])) {
                $options = $element['options'];
            }

            $elementData = [
                'form_version_id' => $this->record->id,
                'parent_id' => $parentId,
                'name' => $element['name'] ?? null,
                'label' => $element['label'] ?? null,
                'order' => 0,
                'elementable_type' => $type,
                // elementable_id will be set below
            ];

            $formElement = null;

            // Handle select input
            if ($type === \App\Models\FormBuilding\SelectInputFormElement::class) {
                $selectModel = \App\Models\FormBuilding\SelectInputFormElement::create($attributes);
                $elementData['elementable_id'] = $selectModel->id;
                $formElement = \App\Models\FormBuilding\FormElement::create($elementData);
                foreach ($options as $idx => $opt) {
                    $optionData = [
                        'label' => $opt['label'] ?? $opt['text'] ?? $opt['name'] ?? $opt['value'] ?? '',
                        'order' => $opt['order'] ?? ($idx + 1),
                        'description' => $opt['description'] ?? null,
                    ];
                    \App\Models\FormBuilding\SelectOptionFormElement::createForSelect($selectModel, $optionData);
                }
            }
            // Handle radio input
            elseif ($type === \App\Models\FormBuilding\RadioInputFormElement::class) {
                $radioModel = \App\Models\FormBuilding\RadioInputFormElement::create($attributes);
                $elementData['elementable_id'] = $radioModel->id;
                $formElement = \App\Models\FormBuilding\FormElement::create($elementData);
                foreach ($options as $idx => $opt) {
                    $optionData = [
                        'label' => $opt['label'] ?? $opt['text'] ?? $opt['name'] ?? $opt['value'] ?? '',
                        'order' => $opt['order'] ?? ($idx + 1),
                        'description' => $opt['description'] ?? null,
                    ];
                    \App\Models\FormBuilding\SelectOptionFormElement::createForRadio($radioModel, $optionData);
                }
            }
            // Handle containers
            elseif ($type === \App\Models\FormBuilding\ContainerFormElement::class) {
                $containerModel = \App\Models\FormBuilding\ContainerFormElement::create($attributes);
                $elementData['elementable_id'] = $containerModel->id;
                $formElement = \App\Models\FormBuilding\FormElement::create($elementData);
            }
            // Handle all other element types (e.g. text input, textarea, etc.)
            else {
                if (method_exists($type, 'create')) {
                    $elementableModel = $type::create($attributes);
                    $elementData['elementable_id'] = $elementableModel->id;
                }
                $formElement = \App\Models\FormBuilding\FormElement::create($elementData);
            }

            // Recursively import children for containers
            if (
                ($type === \App\Models\FormBuilding\ContainerFormElement::class)
                && !empty($element['elements']) && is_array($element['elements'])
            ) {
                $this->importElementsRecursive($element['elements'], $formElement->id);
            }
            // Some schemas may use 'children' instead of 'elements'
            elseif (!empty($element['children']) && is_array($element['children'])) {
                $this->importElementsRecursive($element['children'], $formElement->id);
            }
        }
    }

    /**
     * Map template element type to FormElement polymorphic type.
     * (Now uses resolveElementableType for consistency)
     */
    protected function mapElementType(array $element): string
    {
        return $this->resolveElementableType($element['elementType'] ?? $element['type'] ?? '')
            ?? \App\Models\FormBuilding\ContainerFormElement::class;
    }

    /**
     * Extract element attributes for the polymorphic model.
     */
    protected function extractElementAttributes(array $element): array
    {
        // Pass through all relevant keys except children/elements
        $exclude = ['elements', 'children', 'token', 'parentId', 'elementType', 'type'];
        $attributes = [];
        foreach ($element as $key => $value) {
            if (!in_array($key, $exclude, true)) {
                $attributes[$key] = $value;
            }
        }
        // Optionally handle listItems, dataBinding, etc.
        if (isset($element['listItems'])) {
            $attributes['listItems'] = $element['listItems'];
        }
        if (isset($element['dataBinding'])) {
            $attributes['dataBinding'] = $element['dataBinding'];
        }
        if (isset($element['dataFormat'])) {
            $attributes['dataFormat'] = $element['dataFormat'];
        }
        return $attributes;
    }

    protected function makeDownloadJsonAction(string $name, string $label, int $version): Actions\Action
    {
        return Actions\Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->color('info')
            ->outlined()
            ->action(function () use ($version) {
                $userId = Auth::id();

                if (!$userId) {
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Authentication Error')
                        ->body('You must be logged in to download JSON files.')
                        ->send();
                    return;
                }

                // Dispatch job to generate JSON for the given version
                GenerateFormVersionJsonJob::dispatch($this->record, $userId, $version);

                // Show immediate notification
                \Filament\Notifications\Notification::make()
                    ->info()
                    ->title('JSON Export Started')
                    ->body('Your JSON file is being generated. You will receive a notification when it\'s ready for download.')
                    ->send();
            });
    }



    public function save(): void
    {
        // Prevent saving if not editable
        if (!$this->isEditable()) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Cannot Save Changes')
                ->body('Form versions can only be saved when in draft status.')
                ->send();
            return;
        }

        $data = $this->form->getState();

        // Save CSS stylesheets
        $css_content_web = $data['css_content_web'] ?? '';
        $css_content_pdf = $data['css_content_pdf'] ?? '';
        StyleSheet::createStyleSheet($this->record, $css_content_web, 'web');
        StyleSheet::createStyleSheet($this->record, $css_content_pdf, 'pdf');

        // Save JavaScript form scripts
        $js_content_web = $data['js_content_web'] ?? '';
        $js_content_pdf = $data['js_content_pdf'] ?? '';
        FormScript::createFormScript($this->record, $js_content_web, 'web');
        FormScript::createFormScript($this->record, $js_content_pdf, 'pdf');

        // Fire update event for styles and scripts
        FormVersionUpdateEvent::dispatch(
            $this->record->id,
            $this->record->form_id,
            $this->record->version_number,
            null,
            'styles_scripts',
            false
        );

        $this->getSavedNotification()?->send();
    }

    protected function getSavedNotification(?string $message = null): ?\Filament\Notifications\Notification
    {
        return \Filament\Notifications\Notification::make()
            ->success()
            ->title('Saved')
            ->body($message ?? 'The form builder changes have been saved successfully.');
    }

    protected function getFormElementSchema(): array
    {
        return [
            \Filament\Forms\Components\Tabs::make('form_element_tabs')
                ->tabs([
                    \Filament\Forms\Components\Tabs\Tab::make('General')
                        ->icon('heroicon-o-cog')
                        ->schema([
                            \Filament\Forms\Components\Select::make('template_id')
                                ->label('Start from template')
                                ->placeholder('Select a template (optional)')
                                ->options(function () {
                                    $templates = FormElement::templates()
                                        ->with('elementable')
                                        ->get();

                                    $availableTypes = FormElement::getAvailableElementTypes();
                                    $groupedOptions = [];

                                    foreach ($templates as $template) {
                                        $elementType = $template->elementable_type;
                                        $groupName = $availableTypes[$elementType] ?? class_basename($elementType);

                                        if (!isset($groupedOptions[$groupName])) {
                                            $groupedOptions[$groupName] = [];
                                        }

                                        $groupedOptions[$groupName][$template->id] = $template->name;
                                    }

                                    // Sort groups alphabetically
                                    ksort($groupedOptions);

                                    return $groupedOptions;
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if (!$state) {
                                        return;
                                    }

                                    // Load the template element with its relationships
                                    $template = FormElement::with(['elementable', 'tags'])->find($state);

                                    if (!$template) {
                                        return;
                                    }

                                    // Prefill basic form element data
                                    $set('name', $template->name);
                                    $set('description', $template->description);
                                    $set('help_text', $template->help_text);
                                    $set('elementable_type', $template->elementable_type);
                                    $set('is_required', $template->is_required);
                                    $set('visible_web', $template->visible_web);
                                    $set('visible_pdf', $template->visible_pdf);
                                    $set('is_template', false); // New element should not be a template by default

                                    // Prefill tags
                                    if ($template->tags->isNotEmpty()) {
                                        $set('tags', $template->tags->pluck('id')->toArray());
                                    }

                                    // Prefill elementable data if it exists
                                    if ($template->elementable) {
                                        $elementableData = $template->elementable->toArray();
                                        // Remove timestamps and primary key
                                        unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);
                                        $set('elementable_data', $elementableData);
                                    }
                                })
                                ->searchable()
                                ->columnSpanFull(),
                            \Filament\Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->label('Element Name')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    // Auto-generate reference_id if it's empty and we have a name
                                    if (!empty($state) && empty($get('reference_id'))) {
                                        $slug = \Illuminate\Support\Str::slug($state, '-');
                                        $set('reference_id', $slug);
                                    }
                                })
                                ->when($this->shouldShowTooltips(), function ($component) {
                                    return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Human friendly identifier to help you find and reference this element');
                                }),
                            \Filament\Forms\Components\TextInput::make('reference_id')
                                ->label('Reference ID')
                                ->rules(['alpha_dash'])
                                ->when($this->shouldShowTooltips(), function ($component) {
                                    return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Human-readable identifier to aid creating ICM data bindings');
                                })
                                ->suffixAction(
                                    \Filament\Forms\Components\Actions\Action::make('regenerate_reference_id')
                                        ->icon('heroicon-o-arrow-path')
                                        ->tooltip('Regenerate from Element Name')
                                        ->action(function (callable $set, callable $get) {
                                            $name = $get('name');
                                            if (!empty($name)) {
                                                $slug = \Illuminate\Support\Str::slug($name, '-');
                                                $set('reference_id', $slug);
                                            }
                                        })
                                ),
                            \Filament\Forms\Components\Select::make('elementable_type')
                                ->label('Element Type')
                                ->when($this->shouldShowTooltips(), function ($component) {
                                    return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Various inputs, containers for grouping and repeating, text info for paragraphs, or custom HTML');
                                })
                                ->options(FormElement::getAvailableElementTypes())
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Clear existing elementable data when type changes
                                    $set('elementable_data', []);
                                }),
                            \Filament\Forms\Components\Textarea::make('description')
                                ->rows(3),
                            \Filament\Forms\Components\TextInput::make('help_text')
                                ->when($this->shouldShowTooltips(), function ($component) {
                                    return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'This text is read aloud by screen readers to describe the element');
                                })
                                ->maxLength(500),
                            \Filament\Forms\Components\Grid::make(2)
                                ->schema([
                                    \Filament\Forms\Components\Toggle::make('visible_web')
                                        ->label('Visible on Web')
                                        ->default(true),
                                    \Filament\Forms\Components\Toggle::make('visible_pdf')
                                        ->label('Visible on PDF')
                                        ->default(true),
                                ]),
                            \Filament\Forms\Components\Grid::make(2)
                                ->schema([
                                    \Filament\Forms\Components\Toggle::make('is_required')
                                        ->label('Is Required')
                                        ->default(false),
                                    \Filament\Forms\Components\Toggle::make('is_template')
                                        ->label('Is Template')
                                        ->when($this->shouldShowTooltips(), function ($component) {
                                            return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'If this element should be a template for later reuse');
                                        })
                                        ->default(false),
                                ]),
                            \Filament\Forms\Components\Select::make('tags')
                                ->label('Tags')
                                ->when($this->shouldShowTooltips(), function ($component) {
                                    return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Categorize related fields (use camelCase)');
                                })
                                ->multiple()
                                ->options(fn() => FormElementTag::pluck('name', 'id')->toArray())
                                ->createOptionAction(
                                    fn(Forms\Components\Actions\Action $action) => $action
                                        ->modalHeading('Create Tag')
                                        ->modalWidth('md')
                                )
                                ->createOptionForm([
                                    \Filament\Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(FormElementTag::class, 'name'),
                                    \Filament\Forms\Components\Textarea::make('description')
                                        ->rows(3),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $tag = FormElementTag::create($data);
                                    return $tag->id;
                                })
                                ->searchable()
                                ->preload(),
                        ]),
                    \Filament\Forms\Components\Tabs\Tab::make('Element Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema(function (callable $get) {
                            $elementType = $get('elementable_type');
                            if (!$elementType) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('select_element_type')
                                        ->label('')
                                        ->content('Please select an element type in the General tab first.')
                                ];
                            }
                            return $this->getElementSpecificSchema($elementType);
                        }),
                    \Filament\Forms\Components\Tabs\Tab::make('Data Bindings')
                        ->icon('heroicon-o-link')
                        ->schema(function (callable $get) {
                            // Get data sources assigned to this form version
                            $formVersion = $this->record;
                            if (!$formVersion || $formVersion->formDataSources->isEmpty()) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('no_data_sources')
                                        ->label('')
                                        ->content('Please add Data Sources in the Form Version before adding Data Bindings.')
                                        ->extraAttributes(['class' => 'text-warning'])
                                ];
                            }

                            return [
                                Repeater::make('dataBindings')
                                    ->label('Data Bindings')
                                    ->defaultItems(0)
                                    ->schema([
                                        Select::make('form_data_source_id')
                                            ->label('Data Source')
                                            ->when($this->shouldShowTooltips(), function ($component) {
                                                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'The ICM Entity this data binding uses');
                                            })
                                            ->options(function () use ($formVersion) {
                                                return $formVersion->formDataSources->pluck('name', 'id')->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live(onBlur: true),
                                        \Filament\Forms\Components\TextInput::make('path')
                                            ->label('Data Path')
                                            ->when($this->shouldShowTooltips(), function ($component) {
                                                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'The full string referencing the ICM data');
                                            })
                                            ->required()
                                            ->placeholder("$.['Contact'].['Birth Date']")
                                            ->helperText('The path to the data field in the selected data source'),
                                    ])
                                    ->orderColumn('order')
                                    ->itemLabel(
                                        fn(array $state): ?string =>
                                        isset($state['form_data_source_id']) && isset($state['path'])
                                            ? (FormDataSource::find($state['form_data_source_id'])?->name ?? 'Data Source') . ': ' . $state['path']
                                            : 'New Data Binding'
                                    )
                                    ->addActionLabel('Add Data Binding')
                                    ->reorderableWithButtons()
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),
                            ];
                        }),
                ])
                ->columnSpanFull(),
        ];
    }

    protected function getElementSpecificSchema(string $elementType): array
    {
        // Check if the element type class exists and has the getFilamentSchema method
        if (class_exists($elementType) && method_exists($elementType, 'getFilamentSchema')) {
            return $elementType::getFilamentSchema(false);
        }

        // Fallback for element types that don't have schema defined yet
        return [
            \Filament\Forms\Components\Placeholder::make('no_specific_properties')
                ->label('')
                ->content('This element type has no specific properties defined yet.')
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing CSS content from stylesheets
        $this->record->load(['webStyleSheet', 'pdfStyleSheet', 'webFormScript', 'pdfFormScript']);

        $cssContentWeb = $this->record->webStyleSheet?->getCssContent();
        $cssContentPdf = $this->record->pdfStyleSheet?->getCssContent();

        $data['css_content_web'] = $cssContentWeb ?? '';
        $data['css_content_pdf'] = $cssContentPdf ?? '';

        // Load existing JavaScript content from form scripts
        $jsContentWeb = $this->record->webFormScript?->getJsContent();
        $jsContentPdf = $this->record->pdfFormScript?->getJsContent();

        $data['js_content_web'] = $jsContentWeb ?? '';
        $data['js_content_pdf'] = $jsContentPdf ?? '';

        return $data;
    }

    public function getBreadcrumbs(): array
    {
        return [
            FormVersionResource::getUrl('index') => 'Form Versions',
            FormResource::getUrl('view', ['record' => $this->record->form->id]) => "{$this->record->form->form_id}",
            FormVersionResource::getUrl('view', ['record' => $this->record]) => "Version {$this->record->version_number}",
            '#' => 'Form Builder',
        ];
    }

    public function getTitle(): string
    {
        return "Form Builder - Version {$this->record->version_number}";
    }

    public function getHeading(): string
    {
        return "Form Builder";
    }

    /**
     * Fire a FormVersionUpdateEvent for broadcasting live updates
     */
    protected function fireUpdateEvent(array $updatedData = null, string $updateType = 'general', bool $isDraft = false): void
    {
        FormVersionUpdateEvent::dispatch(
            $this->record->id,
            $this->record->form_id,
            $this->record->version_number,
            $updatedData,
            $updateType,
            $isDraft
        );
    }

    /**
     * Handle live updates during form editing (draft mode)
     */
    public function onFormDataUpdated(): void
    {
        // This can be called when form data changes to broadcast draft updates
        $data = $this->form->getState();

        $this->fireUpdateEvent([
            'css_content_web' => $data['css_content_web'] ?? '',
            'css_content_pdf' => $data['css_content_pdf'] ?? '',
            'js_content_web' => $data['js_content_web'] ?? '',
            'js_content_pdf' => $data['js_content_pdf'] ?? '',
        ], 'draft_update', true);
    }

    /**
     * Livewire method that can be called from the frontend to trigger draft updates
     */
    public function updatedData(): void
    {
        // This method will be called whenever form data is updated
        $this->onFormDataUpdated();
    }

    /**
     * Method to manually trigger a form version update event
     */
    public function triggerUpdateEvent(string $updateType = 'manual'): void
    {
        $data = $this->form->getState();

        $this->fireUpdateEvent([
            'css_content_web' => $data['css_content_web'] ?? '',
            'css_content_pdf' => $data['css_content_pdf'] ?? '',
            'js_content_web' => $data['js_content_web'] ?? '',
            'js_content_pdf' => $data['js_content_pdf'] ?? '',
        ], $updateType, false);

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Update Broadcasted')
            ->body('Form version update has been broadcasted to all connected clients.')
            ->send();
    }

    /**
     * Get JavaScript code for handling real-time updates
     */
    public function getJavaScriptForRealTimeUpdates(): string
    {
        return "
        // Add event listeners for real-time updates
        document.addEventListener('DOMContentLoaded', function() {
            // Monitor form changes for real-time updates
            const formElements = document.querySelectorAll('input, textarea, select');

            let updateTimeout;

            formElements.forEach(element => {
                element.addEventListener('input', function() {
                    clearTimeout(updateTimeout);
                    updateTimeout = setTimeout(() => {
                        // Trigger draft update
                        window.Livewire.find('{$this->getId()}').call('onFormDataUpdated');
                    }, 1000); // Debounce updates by 1 second
                });
            });

            // Monitor Monaco editor changes if they exist
            if (window.monaco) {
                const monacoEditors = document.querySelectorAll('.monaco-editor');
                monacoEditors.forEach(editorElement => {
                    const editorInstance = monaco.editor.getModel(editorElement);
                    if (editorInstance) {
                        editorInstance.onDidChangeContent(() => {
                            clearTimeout(updateTimeout);
                            updateTimeout = setTimeout(() => {
                                window.Livewire.find('{$this->getId()}').call('onFormDataUpdated');
                            }, 2000); // Longer debounce for code editors
                        });
                    }
                });
            }
        });
        ";
    }

    /**
     * Extract content from uploaded file (supports both Livewire v2 and v3)
     */
    private function extractFileContent($uploadedFile): ?string
    {
        try {
            Log::info('Extracting file content', [
                'type' => gettype($uploadedFile),
                'class' => is_object($uploadedFile) ? get_class($uploadedFile) : 'not_object',
                'is_array' => is_array($uploadedFile)
            ]);

            // Handle array of files (take first file)
            if (is_array($uploadedFile) && !empty($uploadedFile)) {
                $uploadedFile = $uploadedFile[0];
                Log::info('Using first file from array', [
                    'type' => gettype($uploadedFile),
                    'class' => is_object($uploadedFile) ? get_class($uploadedFile) : 'not_object'
                ]);
            }

            // Handle different Livewire file upload types
            if (is_object($uploadedFile)) {
                $className = get_class($uploadedFile);
                Log::info('Processing file object', ['class' => $className]);

                // Livewire v3 TemporaryUploadedFile
                if (str_contains($className, 'TemporaryUploadedFile')) {
                    if (method_exists($uploadedFile, 'getRealPath')) {
                        $path = $uploadedFile->getRealPath();
                        Log::info('Got real path', ['path' => $path]);
                        if ($path && file_exists($path)) {
                            return file_get_contents($path);
                        }
                    }

                    if (method_exists($uploadedFile, 'getPathname')) {
                        $path = $uploadedFile->getPathname();
                        Log::info('Got pathname', ['path' => $path]);
                        if ($path && file_exists($path)) {
                            return file_get_contents($path);
                        }
                    }

                    if (method_exists($uploadedFile, 'path')) {
                        $path = $uploadedFile->path();
                        Log::info('Got path() result', ['path' => $path]);
                        if ($path && file_exists($path)) {
                            return file_get_contents($path);
                        }
                    }

                    // Try get() method for Livewire files
                    if (method_exists($uploadedFile, 'get')) {
                        $content = $uploadedFile->get();
                        Log::info('Got content via get()', ['content_length' => strlen($content ?? '')]);
                        return $content;
                    }

                    // Try readStream method
                    if (method_exists($uploadedFile, 'readStream')) {
                        $stream = $uploadedFile->readStream();
                        if ($stream) {
                            $content = stream_get_contents($stream);
                            fclose($stream);
                            Log::info('Got content via readStream()', ['content_length' => strlen($content ?? '')]);
                            return $content;
                        }
                    }
                }

                // Laravel UploadedFile
                if (method_exists($uploadedFile, 'getRealPath')) {
                    $path = $uploadedFile->getRealPath();
                    Log::info('Laravel file real path', ['path' => $path]);
                    if ($path && file_exists($path)) {
                        return file_get_contents($path);
                    }
                }

                // Symfony UploadedFile
                if (method_exists($uploadedFile, 'getPathname')) {
                    $path = $uploadedFile->getPathname();
                    Log::info('Symfony file pathname', ['path' => $path]);
                    if ($path && file_exists($path)) {
                        return file_get_contents($path);
                    }
                }

                // Log all available methods for debugging
                Log::info('Available methods on file object', [
                    'methods' => get_class_methods($uploadedFile)
                ]);
            }

            // Handle string path
            if (is_string($uploadedFile) && file_exists($uploadedFile)) {
                Log::info('Processing string path', ['path' => $uploadedFile]);
                return file_get_contents($uploadedFile);
            }

            throw new \Exception('Unable to read uploaded file - no valid path or content method found');
        } catch (\Exception $e) {
            Log::error('Error extracting file content: ' . $e->getMessage(), [
                'exception' => $e,
                'uploadedFile_type' => gettype($uploadedFile),
                'uploadedFile_class' => is_object($uploadedFile) ? get_class($uploadedFile) : 'not_object'
            ]);
            throw new \Exception('Could not read the uploaded file: ' . $e->getMessage());
        }
    }

    /**
     * Process schema import using the working parser approach
     */
    private function processSchemaImport(array $jsonData): array
    {
        try {
            // Initialize the schema parser (based on working ImportSchema implementation)
            $schemaParser = new \App\Filament\Forms\Helpers\SchemaParser();

            // Parse the schema to extract structured data
            $parsedSchema = $schemaParser->parseSchema(json_encode($jsonData));

            if (!$parsedSchema) {
                return [
                    'success' => false,
                    'message' => 'Could not parse the schema format. Please check that it\'s a valid form template.'
                ];
            }

            // Extract fields from the parsed schema
            $fields = $this->extractFieldsFromParsedSchema($parsedSchema);

            if (empty($fields)) {
                return [
                    'success' => false,
                    'message' => 'No fields found in the schema. The template may be empty or in an unsupported format.'
                ];
            }

            // Create form elements from the extracted fields
            $createdCount = $this->createFormElementsFromFields($fields);

            return [
                'success' => true,
                'message' => "Successfully imported {$createdCount} form elements from template."
            ];
        } catch (\Exception $e) {
            Log::error('Schema import processing error: ' . $e->getMessage(), [
                'form_version_id' => $this->record->id,
                'exception' => $e
            ]);

            return [
                'success' => false,
                'message' => 'Error processing schema: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract fields from parsed schema (handles both legacy and adze-template formats)
     */
    private function extractFieldsFromParsedSchema(array $parsedSchema): array
    {
        $fields = [];

        try {
            // Handle adze-template format (data.elements structure)
            if (isset($parsedSchema['data']) && isset($parsedSchema['data']['elements'])) {
                $fields = $this->extractFieldsRecursive($parsedSchema['data']['elements']);
            }
            // Handle legacy format (direct fields array)
            elseif (isset($parsedSchema['fields'])) {
                $fields = $this->extractFieldsRecursive($parsedSchema['fields']);
            }
            // Handle flat structure
            elseif (isset($parsedSchema['elements'])) {
                $fields = $this->extractFieldsRecursive($parsedSchema['elements']);
            }

            Log::info('Extracted fields from schema', [
                'total_fields' => count($fields),
                'schema_format' => $this->determineSchemaFormat($parsedSchema)
            ]);

            return $fields;
        } catch (\Exception $e) {
            Log::error('Error extracting fields from schema: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recursively extract fields from nested structure
     */
    private function extractFieldsRecursive(array $elements, string $parentPath = ''): array
    {
        $fields = [];

        foreach ($elements as $index => $element) {
            if (!is_array($element)) {
                continue;
            }

            // Generate current path
            $currentPath = $parentPath ? $parentPath . '.' . $index : (string)$index;

            // Skip empty elements or elements without required properties
            if (empty($element) || (!isset($element['elementType']) && !isset($element['type']))) {
                continue;
            }

            // Determine element type
            $elementType = $element['elementType'] ?? $element['type'] ?? 'unknown';

            // Skip container elements but process their children
            if ($this->isContainerElement($elementType, $element)) {
                if (isset($element['elements']) && is_array($element['elements'])) {
                    $childFields = $this->extractFieldsRecursive($element['elements'], $currentPath);
                    $fields = array_merge($fields, $childFields);
                }
                continue;
            }

            // Process actual form fields
            if ($this->isFormField($elementType, $element)) {
                $processedField = $this->processFieldElement($element, $currentPath);
                if ($processedField) {
                    $fields[] = $processedField;
                }
            }

            // Also check for nested elements in form fields
            if (isset($element['elements']) && is_array($element['elements'])) {
                $childFields = $this->extractFieldsRecursive($element['elements'], $currentPath);
                $fields = array_merge($fields, $childFields);
            }
        }

        return $fields;
    }

    /**
     * Check if element is a container (should not create form field)
     */
    private function isContainerElement(string $elementType, array $element): bool
    {
        $containerTypes = [
            'ContainerFormElements',
            'container',
            'section',
            'group',
            'fieldset'
        ];

        // Check element type
        if (in_array($elementType, $containerTypes)) {
            return true;
        }

        // Check container type property
        if (isset($element['containerType'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if element should create a form field
     */
    private function isFormField(string $elementType, array $element): bool
    {
        $fieldTypes = [
            'TextInputFormElements',
            'SelectInputFormElements',
            'RadioInputFormElements',
            'CheckboxInputFormElements',
            'TextareaInputFormElements',
            'DateInputFormElements',
            'NumberInputFormElements',
            'FileInputFormElements',
            'ButtonInputFormElements',
            'text-input',
            'dropdown',
            'radio',
            'checkbox',
            'textarea',
            'date',
            'number',
            'file',
            'button'
        ];

        return in_array($elementType, $fieldTypes);
    }

    /**
     * Process individual field element into standardized format
     */
    private function processFieldElement(array $element, string $path): ?array
    {
        try {
            // Extract basic field properties
            $field = [
                'token' => $element['token'] ?? $element['id'] ?? uniqid('field_'),
                'name' => $element['name'] ?? 'unnamed_field_' . time(),
                'label' => $element['label'] ?? $element['name'] ?? 'Untitled Field',
                'elementType' => $element['elementType'] ?? $element['type'] ?? 'TextInputFormElements',
                'path' => $path,
                'dataType' => $this->mapElementTypeToDataType($element['elementType'] ?? $element['type'] ?? ''),
                'required' => $element['required'] ?? false,
                'visible' => $element['isVisible'] ?? $element['visible'] ?? true,
                'enabled' => $element['isEnabled'] ?? $element['enabled'] ?? true,
                'helpText' => $element['helpText'] ?? $element['help_text'] ?? null,
                'placeholder' => $element['placeholder'] ?? null,
            ];

            // Handle specific field type properties
            $this->processFieldTypeSpecificProperties($field, $element);

            return $field;
        } catch (\Exception $e) {
            Log::warning('Error processing field element: ' . $e->getMessage(), [
                'element' => $element,
                'path' => $path
            ]);
            return null;
        }
    }

    /**
     * Process field type specific properties
     */
    private function processFieldTypeSpecificProperties(array &$field, array $element): void
    {
        $elementType = $field['elementType'];

        // Handle select/dropdown options
        if (in_array($elementType, ['SelectInputFormElements', 'dropdown']) && isset($element['listItems'])) {
            $field['options'] = $this->extractSelectOptions($element['listItems']);
        }

        // Handle radio options
        if (in_array($elementType, ['RadioInputFormElements', 'radio'])) {
            if (isset($element['listItems'])) {
                $field['options'] = $this->extractSelectOptions($element['listItems']);
            } elseif (isset($element['options'])) {
                $field['options'] = $element['options'];
            }
        }

        // Handle data binding
        if (isset($element['dataBinding']['dataBindingPath'])) {
            $field['dataBinding'] = $element['dataBinding']['dataBindingPath'];
        } elseif (isset($element['binding_ref'])) {
            $field['dataBinding'] = $element['binding_ref'];
        }

        // Handle validation rules
        if (isset($element['validation'])) {
            $field['validation'] = $element['validation'];
        }

        // Handle field format/subtype
        if (isset($element['dataFormat'])) {
            $field['format'] = $element['dataFormat'];
        } elseif (isset($element['subtype'])) {
            $field['format'] = $element['subtype'];
        }
    }

    /**
     * Extract select options from listItems array
     */
    private function extractSelectOptions(array $listItems): array
    {
        $options = [];

        foreach ($listItems as $item) {
            if (is_array($item)) {
                $value = $item['value'] ?? $item['name'] ?? $item['text'] ?? '';
                $label = $item['text'] ?? $item['label'] ?? $item['name'] ?? $value;
                $options[] = ['value' => $value, 'label' => $label];
            } elseif (is_string($item)) {
                $options[] = ['value' => $item, 'label' => $item];
            }
        }

        return $options;
    }

    /**
     * Map element type to data type
     */
    private function mapElementTypeToDataType(string $elementType): string
    {
        $mapping = [
            'TextInputFormElements' => 'text',
            'text-input' => 'text',
            'SelectInputFormElements' => 'select',
            'dropdown' => 'select',
            'RadioInputFormElements' => 'radio',
            'radio' => 'radio',
            'CheckboxInputFormElements' => 'checkbox',
            'checkbox' => 'checkbox',
            'TextareaInputFormElements' => 'textarea',
            'textarea' => 'textarea',
            'DateInputFormElements' => 'date',
            'date' => 'date',
            'NumberInputFormElements' => 'number',
            'number' => 'number',
            'FileInputFormElements' => 'file',
            'file' => 'file',
            'ButtonInputFormElements' => 'button',
            'button' => 'button',
        ];

        return $mapping[$elementType] ?? 'text';
    }

    /**
     * Create form elements from extracted fields
     */
    private function createFormElementsFromFields(array $fields): int
    {
        $createdCount = 0;

        foreach ($fields as $field) {
            try {
                $formElement = $this->createFormElement($field);
                if ($formElement) {
                    $createdCount++;
                }
            } catch (\Exception $e) {
                Log::warning('Error creating form element: ' . $e->getMessage(), [
                    'field' => $field
                ]);
                // Continue with other fields even if one fails
            }
        }

        return $createdCount;
    }

    /**
     * Create a single form element from field data
     */
    private function createFormElement(array $field): ?FormElement
    {
        try {
            if (empty($field['name']) || empty($field['label']) || empty($field['elementType'])) {
                Log::error('Missing required field data for FormElement', ['field' => $field]);
                return null;
            }

            $elementableType = $this->resolveElementableType($field['elementType']);
            if (empty($elementableType)) {
                Log::error('Elementable type mapping failed', ['field' => $field]);
                return null;
            }

            // Handle select/radio options
            $options = $field['options'] ?? [];

            if ($elementableType === \App\Models\FormBuilding\SelectInputFormElement::class) {
                $selectModel = \App\Models\FormBuilding\SelectInputFormElement::create([]);
                $elementData = [
                    'form_version_id' => $this->record->id,
                    'elementable_type' => $elementableType,
                    'elementable_id' => $selectModel->id,
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'required' => $field['required'] ?? false,
                    'help_text' => $field['helpText'] ?? null,
                    'placeholder' => $field['placeholder'] ?? null,
                    'order' => $this->getNextElementOrder(),
                    'properties' => $this->buildElementProperties($field),
                ];
                $formElement = FormElement::create($elementData);
                foreach ($options as $idx => $opt) {
                    $optionData = [
                        'label' => $opt['label'] ?? $opt['text'] ?? $opt['name'] ?? $opt['value'] ?? '',
                        'order' => $opt['order'] ?? ($idx + 1),
                        'description' => $opt['description'] ?? null,
                    ];
                    \App\Models\FormBuilding\SelectOptionFormElement::createForSelect($selectModel, $optionData);
                }
                return $formElement;
            }

            if ($elementableType === \App\Models\FormBuilding\RadioInputFormElement::class) {
                $radioModel = \App\Models\FormBuilding\RadioInputFormElement::create([]);
                $elementData = [
                    'form_version_id' => $this->record->id,
                    'elementable_type' => $elementableType,
                    'elementable_id' => $radioModel->id,
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'required' => $field['required'] ?? false,
                    'help_text' => $field['helpText'] ?? null,
                    'placeholder' => $field['placeholder'] ?? null,
                    'order' => $this->getNextElementOrder(),
                    'properties' => $this->buildElementProperties($field),
                ];
                $formElement = FormElement::create($elementData);
                foreach ($options as $idx => $opt) {
                    $optionData = [
                        'label' => $opt['label'] ?? $opt['text'] ?? $opt['name'] ?? $opt['value'] ?? '',
                        'order' => $opt['order'] ?? ($idx + 1),
                        'description' => $opt['description'] ?? null,
                    ];
                    \App\Models\FormBuilding\SelectOptionFormElement::createForRadio($radioModel, $optionData);
                }
                return $formElement;
            }

            // Default: create elementable model if needed, then FormElement
            if (method_exists($elementableType, 'create')) {
                $elementableModel = $elementableType::create([]);
                $elementData = [
                    'form_version_id' => $this->record->id,
                    'elementable_type' => $elementableType,
                    'elementable_id' => $elementableModel->id,
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'required' => $field['required'] ?? false,
                    'help_text' => $field['helpText'] ?? null,
                    'placeholder' => $field['placeholder'] ?? null,
                    'order' => $this->getNextElementOrder(),
                    'properties' => $this->buildElementProperties($field),
                ];
                return FormElement::create($elementData);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error creating form element: ' . $e->getMessage(), [
                'field' => $field
            ]);
            return null;
        }
    }

    /**
     * Map data type to element type for FormElement model
     */
    private function mapDataTypeToElementType(string $dataType): string
    {
        $mapping = [
            'text' => 'text',
            'select' => 'select',
            'radio' => 'radio',
            'checkbox' => 'checkbox',
            'textarea' => 'textarea',
            'date' => 'date',
            'number' => 'number',
            'file' => 'file',
            'button' => 'button',
        ];

        return $mapping[$dataType] ?? 'text';
    }

    /**
     * Get next element order for proper sequencing
     */
    private function getNextElementOrder(): int
    {
        $maxOrder = FormElement::where('form_version_id', $this->record->id)
            ->max('order');

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Build element properties JSON
     */
    private function buildElementProperties(array $field): array
    {
        $properties = [
            'imported' => true,
            'import_source' => 'template',
            'original_token' => $field['token'] ?? null,
            'original_path' => $field['path'] ?? null,
        ];

        // Add specific properties based on field type
        if (isset($field['options']) && !empty($field['options'])) {
            $properties['options'] = $field['options'];
        }

        if (isset($field['dataBinding'])) {
            $properties['data_binding'] = $field['dataBinding'];
        }

        if (isset($field['validation'])) {
            $properties['validation'] = $field['validation'];
        }

        if (isset($field['format'])) {
            $properties['format'] = $field['format'];
        }

        return $properties;
    }

    /**
     * Build field properties JSON
     */
    private function buildFieldProperties(array $field): array
    {
        $properties = [
            'imported' => true,
            'element_type' => $field['elementType'] ?? null,
            'visible' => $field['visible'] ?? true,
            'enabled' => $field['enabled'] ?? true,
        ];

        // Add field-specific properties
        if (isset($field['options']) && !empty($field['options'])) {
            $properties['options'] = $field['options'];
        }

        return $properties;
    }

    /**
     * Determine schema format for logging
     */
    private function determineSchemaFormat(array $schema): string
    {
        if (isset($schema['data']) && isset($schema['data']['elements'])) {
            return 'adze-template';
        } elseif (isset($schema['fields'])) {
            return 'legacy';
        } elseif (isset($schema['elements'])) {
            return 'direct-elements';
        }

        return 'unknown';
    }

    private static function formatJsonForTextarea($value): string
    {
        if (is_string($value)) {
            // Try to decode and re-encode for pretty print
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            return $value;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    /**
     * Import all parsed schema elements as new form elements
     */
    public function importParsedSchemaElements()
    {
        Log::debug('importParsedSchemaElements called', [
            'importWizard' => $this->importWizard,
            'schema_content' => $this->importWizard['schema_content'] ?? null,
        ]);

        $schemaContent = $this->importWizard['schema_content'] ?? null;
        if (empty($schemaContent)) {
            Log::error('No schema_content found in $this->importWizard', [
                'importWizard' => $this->importWizard,
            ]);
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('No Parsed Schema')
                ->body('No schema content found to import.')
                ->send();
            return;
        }

        // Generate a unique cache key for this import
        $cacheKey = 'import_form_version_' . $this->record->id . '_' . uniqid();
        Cache::put($cacheKey . '_status', 'queued', 3600);

        ImportFormVersionElementsJob::dispatch(
            $this->record->id,
            $schemaContent,
            $cacheKey,
            auth()->id()
        );

        session()->put('import_job_cache_key', $cacheKey);

        $this->importJobStatus = [
            'status' => 'queued',
            'done' => false,
            'cacheKey' => $cacheKey,
        ];

        \Filament\Notifications\Notification::make()
            ->info()
            ->title('Import Started')
            ->body('The import is being processed in the background. This page will refresh when it is complete.')
            ->persistent()
            ->send();
    }

    public function pollImportStatus()
    {
        $cacheKey = $this->importJobStatus['cacheKey'] ?? session('import_job_cache_key');
        if (!$cacheKey) {
            $this->importJobStatus['done'] = false;
            return;
        }
        $status = Cache::get($cacheKey . '_status');
        if ($status === 'complete') {
            session()->forget('import_job_cache_key');
            $this->importJobStatus['done'] = true;
            // Refresh the Livewire component state (triggers full reload)
            $this->redirect($this->getResource()::getUrl('build', ['record' => $this->record]));
        } else {
            $this->importJobStatus['done'] = false;
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        // If an import job is running, poll every 2 seconds
        if (
            isset($this->importJobStatus['cacheKey']) &&
            $this->importJobStatus['cacheKey'] &&
            !$this->importJobStatus['done']
        ) {
            $this->js(<<<'JS'
                setTimeout(() => {
                    window.livewire.find("{$this->getId()}").call('pollImportStatus');
                }, 2000);
            JS);
        }
        // ...existing code for rendering the view...
        return parent::render();
    }

    /**
     * Map incoming elementType string to the correct class name from getAvailableElementTypes().
     */
    private function resolveElementableType(string $elementType): ?string
    {
        // Map legacy/plural elementType strings to correct class names
        $map = [
            'TextInputFormElements' => \App\Models\FormBuilding\TextInputFormElement::class,
            'TextareaInputFormElements' => \App\Models\FormBuilding\TextareaInputFormElement::class,
            'TextInfoFormElements' => \App\Models\FormBuilding\TextInfoFormElement::class,
            'DateSelectInputFormElements' => \App\Models\FormBuilding\DateSelectInputFormElement::class,
            'CheckboxInputFormElements' => \App\Models\FormBuilding\CheckboxInputFormElement::class,
            'SelectInputFormElements' => \App\Models\FormBuilding\SelectInputFormElement::class,
            'RadioInputFormElements' => \App\Models\FormBuilding\RadioInputFormElement::class,
            'NumberInputFormElements' => \App\Models\FormBuilding\NumberInputFormElement::class,
            'ButtonInputFormElements' => \App\Models\FormBuilding\ButtonInputFormElement::class,
            'HTMLFormElements' => \App\Models\FormBuilding\HTMLFormElement::class,
            'ContainerFormElements' => \App\Models\FormBuilding\ContainerFormElement::class,
        ];

        if (isset($map[$elementType])) {
            return $map[$elementType];
        }

        // Accept both class names and short names (e.g. "TextInputFormElement")
        $available = FormElement::getAvailableElementTypes();
        foreach ($available as $class => $label) {
            if ($class === $elementType || class_basename($class) === $elementType) {
                return $class;
            }
        }
        return null;
    }
}
