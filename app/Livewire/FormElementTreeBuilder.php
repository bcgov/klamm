<?php

namespace App\Livewire;

use App\Models\FormBuilding\FormElement;
use App\Events\FormVersionUpdateEvent;
use App\Models\FormBuilding\FormElementTag;
use App\Models\FormBuilding\FormVersion;
use App\Models\FormMetadata\FormDataSource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms;
use SolutionForest\FilamentTree\Actions\DeleteAction;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Actions\ViewAction;
use SolutionForest\FilamentTree\Widgets\Tree as BaseWidget;

class FormElementTreeBuilder extends BaseWidget
{
    protected static string $model = FormElement::class;

    protected static int $maxDepth = 5;

    protected ?string $treeTitle = 'Form Elements';

    protected bool $enableTreeTitle = true;

    public $formVersionId;

    // Add properties to store pending data
    protected $pendingElementableData = [];
    protected $pendingElementType = null;

    public function mount($formVersionId = null)
    {
        $this->formVersionId = $formVersionId;
    }

    protected function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Tabs::make('form_element_tabs')
                ->tabs([
                    \Filament\Forms\Components\Tabs\Tab::make('General')
                        ->icon('heroicon-o-cog')
                        ->schema([
                            Select::make('template_id')
                                ->label('Start from template')
                                ->placeholder('Select a template (optional)')
                                ->options(function () {
                                    return FormElement::templates()
                                        ->with('elementable')
                                        ->get()
                                        ->pluck('name', 'id')
                                        ->toArray();
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
                                    $set('is_visible', $template->is_visible);
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
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Select::make('elementable_type')
                                ->label('Element Type')
                                ->options(FormElement::getAvailableElementTypes())
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Clear existing elementable data when type changes
                                    $set('elementable_data', []);
                                }),
                            Textarea::make('description')
                                ->rows(3),
                            TextInput::make('help_text')
                                ->maxLength(500),
                            Toggle::make('is_visible')
                                ->label('Visible')
                                ->default(true),
                            Toggle::make('visible_web')
                                ->label('Visible on Web')
                                ->default(true),
                            Toggle::make('visible_pdf')
                                ->label('Visible on PDF')
                                ->default(true),
                            Toggle::make('is_template')
                                ->label('Is Template')
                                ->default(false),
                            Select::make('tags')
                                ->label('Tags')
                                ->multiple()
                                ->relationship('tags', 'name')
                                ->createOptionAction(
                                    fn(Forms\Components\Actions\Action $action) => $action
                                        ->modalHeading('Create Tag')
                                        ->modalWidth('md')
                                )
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(FormElementTag::class, 'name'),
                                    Textarea::make('description')
                                        ->rows(3),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    return FormElementTag::create($data)->id;
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
                            // Get the form version ID from the form element
                            $formVersionId = $this->formVersionId;
                            if (!$formVersionId) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('no_form_version')
                                        ->label('')
                                        ->content('Form version not available.')
                                ];
                            }

                            // Get data sources assigned to this form version
                            $formVersion = FormVersion::find($formVersionId);
                            if (!$formVersion || $formVersion->formDataSources->isEmpty()) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('no_data_sources')
                                        ->label('')
                                        ->content('No Data Sources are assigned to this Form Version.')
                                ];
                            }

                            return [
                                Repeater::make('dataBindings')
                                    ->label('Data Bindings')
                                    ->relationship()
                                    ->defaultItems(0)
                                    ->schema([
                                        Select::make('form_data_source_id')
                                            ->label('Data Source')
                                            ->options(function () use ($formVersion) {
                                                return $formVersion->formDataSources->pluck('name', 'id')->toArray();
                                            })
                                            ->disabled(),
                                        TextInput::make('path')
                                            ->label('Data Path')
                                            ->disabled(),
                                    ])
                                    ->disabled()
                                    ->columnSpanFull(),
                            ];
                        }),
                ])
                ->columnSpanFull(),
        ];
    }

    public function getEditFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Tabs::make('form_element_tabs')
                ->tabs([
                    \Filament\Forms\Components\Tabs\Tab::make('General')
                        ->icon('heroicon-o-cog')
                        ->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            \Filament\Forms\Components\Hidden::make('elementable_type'),
                            TextInput::make('elementable_type_display')
                                ->label('Element Type')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(function ($state, callable $get) {
                                    $elementType = $get('elementable_type');
                                    return FormElement::getAvailableElementTypes()[$elementType] ?? $elementType;
                                }),
                            Textarea::make('description')
                                ->rows(3),
                            TextInput::make('help_text')
                                ->maxLength(500),
                            Toggle::make('is_visible')
                                ->label('Visible')
                                ->default(true),
                            Toggle::make('visible_web')
                                ->label('Visible on Web')
                                ->default(true),
                            Toggle::make('visible_pdf')
                                ->label('Visible on PDF')
                                ->default(true),
                            Toggle::make('is_template')
                                ->label('Is Template')
                                ->default(false),
                            Select::make('tags')
                                ->label('Tags')
                                ->multiple()
                                ->relationship('tags', 'name')
                                ->createOptionAction(
                                    fn(Forms\Components\Actions\Action $action) => $action
                                        ->modalHeading('Create Tag')
                                        ->modalWidth('md')
                                )
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(FormElementTag::class, 'name'),
                                    Textarea::make('description')
                                        ->rows(3),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    return FormElementTag::create($data)->id;
                                })
                                ->searchable()
                                ->preload(),
                        ]),
                    \Filament\Forms\Components\Tabs\Tab::make('Element Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema(function (callable $get) {
                            // For edit, get the element type from the form data
                            $elementType = $get('elementable_type');
                            if (!$elementType) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('no_element_type')
                                        ->label('')
                                        ->content('No element type available.')
                                ];
                            }
                            return $this->getElementSpecificSchema($elementType);
                        }),
                    \Filament\Forms\Components\Tabs\Tab::make('Data Bindings')
                        ->icon('heroicon-o-link')
                        ->schema(function (callable $get) {
                            // Get the form version ID from the form element
                            $formVersionId = $this->formVersionId;
                            if (!$formVersionId) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('no_form_version')
                                        ->label('')
                                        ->content('Form version not available.')
                                ];
                            }

                            // Get data sources assigned to this form version
                            $formVersion = FormVersion::find($formVersionId);
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
                                    ->relationship()
                                    ->schema([
                                        Select::make('form_data_source_id')
                                            ->label('Data Source')
                                            ->options(function () use ($formVersion) {
                                                return $formVersion->formDataSources->pluck('name', 'id')->toArray();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live(onBlur: true)
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                        TextInput::make('path')
                                            ->label('Data Path')
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

    public function getViewFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Tabs::make('form_element_tabs')
                ->tabs([
                    \Filament\Forms\Components\Tabs\Tab::make('General')
                        ->icon('heroicon-o-cog')
                        ->schema([
                            TextInput::make('name')
                                ->disabled(),
                            TextInput::make('elementable_type')
                                ->label('Element Type')
                                ->disabled(),
                            Textarea::make('description')
                                ->disabled()
                                ->rows(3),
                            TextInput::make('help_text')
                                ->disabled(),
                            Toggle::make('is_visible')
                                ->label('Visible')
                                ->disabled(),
                            Toggle::make('visible_web')
                                ->label('Visible on Web')
                                ->disabled(),
                            Toggle::make('visible_pdf')
                                ->label('Visible on PDF')
                                ->disabled(),
                            Toggle::make('is_template')
                                ->label('Is Template')
                                ->disabled(),
                            Select::make('tags')
                                ->label('Tags')
                                ->multiple()
                                ->relationship('tags', 'name')
                                ->disabled()
                                ->searchable(),
                        ]),
                    \Filament\Forms\Components\Tabs\Tab::make('Element Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema(function (callable $get) {
                            $elementType = $get('elementable_type');
                            if (!$elementType) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('select_element_type')
                                        ->label('')
                                        ->content('No specific properties available.')
                                ];
                            }
                            return $this->getElementSpecificSchema($elementType, true);
                        }),
                    \Filament\Forms\Components\Tabs\Tab::make('Data Bindings')
                        ->icon('heroicon-o-link')
                        ->schema(function (callable $get) {
                            // Get the form version ID from the form element
                            $formVersionId = $this->formVersionId;
                            if (!$formVersionId) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('no_form_version')
                                        ->label('')
                                        ->content('Form version not available.')
                                ];
                            }

                            // Get data sources assigned to this form version
                            $formVersion = FormVersion::find($formVersionId);
                            if (!$formVersion || $formVersion->formDataSources->isEmpty()) {
                                return [
                                    \Filament\Forms\Components\Placeholder::make('no_data_sources')
                                        ->label('')
                                        ->content('No Data Sources are assigned to this Form Version.')
                                ];
                            }

                            return [
                                Repeater::make('dataBindings')
                                    ->label('Data Bindings')
                                    ->relationship()
                                    ->schema([
                                        Select::make('form_data_source_id')
                                            ->label('Data Source')
                                            ->options(function () use ($formVersion) {
                                                return $formVersion->formDataSources->pluck('name', 'id')->toArray();
                                            })
                                            ->disabled(),
                                        TextInput::make('path')
                                            ->label('Data Path')
                                            ->disabled(),
                                    ])
                                    ->disabled()
                                    ->columnSpanFull(),
                            ];
                        }),
                ])
                ->columnSpanFull(),
        ];
    }

    protected function getElementSpecificSchema(string $elementType, bool $disabled = false): array
    {
        // Check if the element type class exists and has the getFilamentSchema method
        if (class_exists($elementType) && method_exists($elementType, 'getFilamentSchema')) {
            return $elementType::getFilamentSchema($disabled);
        }

        // Fallback for element types that don't have schema defined yet
        return [
            \Filament\Forms\Components\Placeholder::make('no_specific_properties')
                ->label('')
                ->content('This element type has no specific properties defined yet.')
        ];
    }

    protected function getTreeActions(): array
    {
        return [
            ViewAction::make()
                ->form($this->getViewFormSchema())
                ->fillForm(function ($record) {
                    $data = $record->toArray();

                    // Load polymorphic data for viewing
                    if (!$record->relationLoaded('elementable')) {
                        $record->load('elementable');
                    }

                    if ($record->elementable) {
                        $elementableData = $record->elementable->toArray();
                        unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);

                        // Load options for select/radio elements
                        if (method_exists($record->elementable, 'options')) {
                            $record->elementable->load('options');
                            $options = $record->elementable->options->map(function ($option) {
                                return [
                                    'label' => $option->label,
                                    'description' => $option->description,
                                ];
                            })->toArray();
                            $elementableData['options'] = $options;
                        }

                        $data['elementable_data'] = $elementableData;
                    }

                    // Load data bindings
                    if (!$record->relationLoaded('dataBindings')) {
                        $record->load('dataBindings');
                    }

                    return $data;
                }),
            EditAction::make()
                ->form($this->getEditFormSchema())
                ->fillForm(function ($record) {
                    $data = $record->toArray();

                    // Load polymorphic data for editing
                    if (!$record->relationLoaded('elementable')) {
                        $record->load('elementable');
                    }

                    if ($record->elementable) {
                        $elementableData = $record->elementable->toArray();
                        unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);

                        // Load options for select/radio elements
                        if (method_exists($record->elementable, 'options')) {
                            $record->elementable->load('options');
                            $options = $record->elementable->options->map(function ($option) {
                                return [
                                    'label' => $option->label,
                                    'description' => $option->description,
                                ];
                            })->toArray();
                            $elementableData['options'] = $options;
                        }

                        $data['elementable_data'] = $elementableData;
                    }

                    // Ensure elementable_type is available even though the field is disabled
                    $data['elementable_type'] = $record->elementable_type;
                    $data['elementable_type_display'] = $record->elementable_type;

                    // Load data bindings
                    if (!$record->relationLoaded('dataBindings')) {
                        $record->load('dataBindings');
                    }

                    return $data;
                })
                ->action(function ($record, array $data) {
                    $data = $this->mutateFormDataBeforeSave($data);
                    $this->handleRecordUpdate($record, $data);
                }),
            DeleteAction::make(),
        ];
    }

    public function getTreeRecordIcon(?\Illuminate\Database\Eloquent\Model $record = null): ?string
    {
        if (!$record) {
            return 'heroicon-o-cube';
        }

        // Different icons based on element type
        $elementType = $record->elementable_type;
        return match (class_basename($elementType)) {
            'TextInputFormElement' => 'heroicon-o-pencil-square',
            'TextareaInputFormElement' => 'heroicon-o-document-text',
            'NumberInputFormElement' => 'heroicon-o-calculator',
            'SelectInputFormElement' => 'heroicon-o-list-bullet',
            'RadioInputFormElement' => 'heroicon-o-radio',
            'CheckboxInputFormElement' => 'heroicon-o-check-circle',
            'ButtonInputFormElement' => 'heroicon-o-cursor-arrow-ripple',
            'ContainerFormElement' => 'heroicon-o-rectangle-group',
            'HTMLFormElement' => 'heroicon-o-code-bracket',
            'TextInfoFormElement' => 'heroicon-o-information-circle',
            default => 'heroicon-o-cube',
        };
    }

    protected function getTreeQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTreeQuery();

        if ($this->formVersionId) {
            $query->where('form_version_id', $this->formVersionId);
        }

        // Eager load relationships to prevent lazy loading issues
        $query->with(['children.children', 'parent', 'elementable']);

        return $query;
    }

    protected function getSortedQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getSortedQuery();

        if ($this->formVersionId) {
            $query->where('form_version_id', $this->formVersionId);
        }

        // Eager load relationships for sorted query as well
        $query->with(['children.children', 'parent', 'elementable']);

        return $query;
    }

    public function getTreeRecordTitle(?\Illuminate\Database\Eloquent\Model $record = null): string
    {
        if (!$record) {
            return '';
        }

        $elementTypeName = FormElement::getElementTypeName($record->elementable_type);
        return "[{$elementTypeName}] {$record->name}";
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load polymorphic data into elementable_data field for editing
        $record = $this->getMountedTreeActionForm()?->getModel();

        if ($record) {
            // Load the polymorphic relationship if not already loaded
            if (!$record->relationLoaded('elementable')) {
                $record->load('elementable');
            }

            if ($record->elementable) {
                $elementableData = $record->elementable->toArray();
                // Remove timestamps and primary key
                unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);

                // Load options for select/radio elements
                if (method_exists($record->elementable, 'options')) {
                    $record->elementable->load('options');
                    $options = $record->elementable->options->map(function ($option) {
                        return [
                            'label' => $option->label,
                            'description' => $option->description,
                        ];
                    })->toArray();
                    $elementableData['options'] = $options;
                }

                $data['elementable_data'] = $elementableData;
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract polymorphic data before main record update
        $elementType = $data['elementable_type'];
        $elementableData = $data['elementable_data'] ?? [];

        // Filter out null values from elementable data to let model defaults apply
        $elementableData = array_filter($elementableData, function ($value) {
            return $value !== null;
        });

        // Remove elementable_data from main form data as it will be handled separately
        unset($data['elementable_data']);

        // Store for use in handleRecordUpdate
        $this->pendingElementableData = $elementableData;
        $this->pendingElementType = $elementType;

        return $data;
    }


    protected function handleRecordUpdate($record, array $data): void
    {
        try {
            // Update the main FormElement first
            $record->update($data);

            // Handle polymorphic relationship
            $elementableData = $this->pendingElementableData;
            $elementType = $this->pendingElementType;

            // Extract options data for select/radio elements before updating the main model
            $optionsData = null;
            if (isset($elementableData['options'])) {
                $optionsData = $elementableData['options'];
                unset($elementableData['options']);
            }

            if ($elementType) {
                // Ensure the record has the elementable relationship loaded
                $record->load('elementable');

                if ($record->elementable && $record->elementable_type === $elementType) {
                    // Update existing polymorphic model of the same type
                    if (!empty($elementableData)) {
                        $record->elementable->update($elementableData);
                    }

                    // Handle options update for existing select/radio elements
                    if ($optionsData !== null && is_array($optionsData)) {
                        $this->updateSelectOptions($record->elementable, $optionsData);
                    }
                } else {
                    // Handle type change or missing polymorphic model
                    if ($record->elementable) {
                        $record->elementable->delete();
                    }

                    // Create new polymorphic model
                    if (class_exists($elementType)) {
                        $elementableModel = $elementType::create($elementableData ?: []);
                        $record->update([
                            'elementable_type' => $elementType,
                            'elementable_id' => $elementableModel->id,
                        ]);
                        // Refresh the relationship
                        $record->load('elementable');

                        // Handle options for new select/radio elements
                        if ($optionsData !== null && is_array($optionsData)) {
                            $this->createSelectOptions($elementableModel, $optionsData);
                        }
                    }
                }
            }

            // Clear pending data
            $this->pendingElementableData = [];
            $this->pendingElementType = null;

            // Fire update event for element modification
            if ($this->formVersionId) {
                $formVersion = FormVersion::find($this->formVersionId);
                if ($formVersion) {
                    FormVersionUpdateEvent::dispatch(
                        $formVersion->id,
                        $formVersion->form_id,
                        $formVersion->version_number,
                        ['updated_element' => $record->fresh()->toArray()],
                        'element_updated',
                        false
                    );
                }
            }
        } catch (\InvalidArgumentException $e) {
            // Handle our custom validation exceptions
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Cannot Update Element')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            // Re-throw to prevent the update from completing
            throw $e;
        } catch (\Exception $e) {
            // Handle any other exceptions
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Update Failed')
                ->body('An unexpected error occurred while updating the element: ' . $e->getMessage())
                ->persistent()
                ->send();

            throw $e;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($this->formVersionId) {
            $data['form_version_id'] = $this->formVersionId;
        }

        // Remove template_id as it's only used for prefilling
        unset($data['template_id']);

        // Validate parent can have children if parent_id is set
        if (isset($data['parent_id']) && $data['parent_id'] && $data['parent_id'] !== -1) {
            $parent = FormElement::find($data['parent_id']);
            if ($parent && !$parent->canHaveChildren()) {
                // Send a user-friendly notification
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('Cannot Add Here')
                    ->body("Only container elements can have children. '{$parent->name}' (type: {$parent->element_type}) cannot contain child elements.")
                    ->persistent()
                    ->send();

                throw new \InvalidArgumentException("Only container elements can have children. Cannot add child to '{$parent->name}' (type: {$parent->element_type}).");
            }
        }

        // Extract polymorphic data
        $elementType = $data['elementable_type'];
        $elementableData = $data['elementable_data'] ?? [];
        unset($data['elementable_data']);

        // Filter out null values from elementable data to let model defaults apply
        $elementableData = array_filter($elementableData, function ($value) {
            return $value !== null;
        });

        // Store for use after main record creation
        $this->pendingElementableData = $elementableData;
        $this->pendingElementType = $elementType;

        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            // Create the polymorphic model first if there's data
            $elementableModel = null;
            $elementType = $this->pendingElementType;
            $elementableData = $this->pendingElementableData;

            // Extract options data for select/radio elements before creating the main model
            $optionsData = null;
            if (isset($elementableData['options'])) {
                $optionsData = $elementableData['options'];
                unset($elementableData['options']);
            }

            if (!empty($elementableData) && class_exists($elementType)) {
                $elementableModel = $elementType::create($elementableData);
            } elseif (class_exists($elementType)) {
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

            // Handle options for select/radio elements
            if ($elementableModel && $optionsData && is_array($optionsData)) {
                $this->createSelectOptions($elementableModel, $optionsData);
            }

            // Clear pending data
            $this->pendingElementableData = [];
            $this->pendingElementType = null;

            // Fire update event for element creation
            if ($this->formVersionId) {
                $formVersion = FormVersion::find($this->formVersionId);
                if ($formVersion) {
                    FormVersionUpdateEvent::dispatch(
                        $formVersion->id,
                        $formVersion->form_id,
                        $formVersion->version_number,
                        ['created_element' => $formElement->fresh()->toArray()],
                        'element_created',
                        false
                    );
                }
            }

            return $formElement;
        } catch (\Exception $e) {
            // Clean up any created polymorphic model if main creation fails
            if (isset($elementableModel) && $elementableModel) {
                $elementableModel->delete();
            }
            throw $e;
        }
    }

    /**
     * Get custom CSS classes for tree records
     */
    public function getTreeRecordClasses(?\Illuminate\Database\Eloquent\Model $record = null): string
    {
        if (!$record) {
            return '';
        }

        $classes = [];

        // Add class based on whether element can have children
        if ($record->canHaveChildren()) {
            $classes[] = 'can-have-children';
        } else {
            $classes[] = 'cannot-have-children';
        }

        // Add specific type class
        $elementType = class_basename($record->elementable_type ?? '');
        $classes[] = 'element-type-' . strtolower($elementType);

        return implode(' ', $classes);
    }

    /**
     * Override the tree update method to handle validation gracefully
     */
    public function updateTree(?array $list = null): array
    {
        // Validate the proposed tree structure before attempting to save
        if (!$list || !$this->validateTreeStructure($list)) {
            // Validation failed, notification already shown in validateTreeStructure
            // Force a refresh of the tree data from the database
            return $this->refreshTreeData();
        }

        try {
            // If validation passes, proceed with the update
            $result = parent::updateTree($list);

            // Fire update event for tree structure changes (moves/reorders)
            if ($this->formVersionId) {
                $formVersion = FormVersion::find($this->formVersionId);
                if ($formVersion) {
                    FormVersionUpdateEvent::dispatch(
                        $formVersion->id,
                        $formVersion->form_id,
                        $formVersion->version_number,
                        ['tree_structure' => $list],
                        'elements_moved',
                        false
                    );
                }
            }

            return $result;
        } catch (\InvalidArgumentException $e) {
            // Handle model validation exceptions with user-friendly notification
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Cannot Update Tree')
                ->body('Only container elements can have children. The tree structure has been reverted.')
                ->persistent()
                ->send();

            // Force a refresh of the tree data from the database
            return $this->refreshTreeData();
        } catch (\Exception $e) {
            // Handle any other exceptions
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Update Failed')
                ->body('An unexpected error occurred while updating the tree structure.')
                ->persistent()
                ->send();

            // Force a refresh of the tree data from the database
            return $this->refreshTreeData();
        }
    }

    /**
     * Refresh the tree data from the database
     */
    protected function refreshTreeData(): array
    {
        // Get fresh data from the database in the same format the tree expects
        return $this->getTreeQuery()
            ->with(['children' => function ($query) {
                $query->orderBy('order');
            }])
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get()
            ->toArray();
    }

    /**
     * Validate the proposed tree structure before saving
     */
    protected function validateTreeStructure(array $list): bool
    {
        foreach ($list as $item) {
            // Check if this item has a parent_id and validate the parent-child relationship
            if (isset($item['parent_id']) && $item['parent_id'] && $item['parent_id'] !== -1) {
                $parent = FormElement::find($item['parent_id']);
                $child = FormElement::find($item['id']);

                if ($parent && $child && !$parent->canHaveChildren()) {
                    // Show user-friendly notification
                    \Filament\Notifications\Notification::make()
                        ->danger()
                        ->title('Cannot Move Element')
                        ->body("'{$child->name}' cannot be moved into '{$parent->name}' (type: {$parent->element_type}). Only Container elements can have children. The tree has been reverted to its previous state.")
                        ->persistent()
                        ->send();

                    return false;
                }
            }

            // Recursively validate children if they exist
            if (isset($item['children']) && is_array($item['children']) && !empty($item['children'])) {
                if (!$this->validateTreeStructure($item['children'])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        if (!$this->formVersionId) {
            return view('livewire.form-element-tree-builder');
        }

        // Ensure custom CSS classes are applied to tree items
        $this->prepareTreeItemClasses();

        return parent::render();
    }

    /**
     * Prepare custom CSS classes for tree items
     */
    protected function prepareTreeItemClasses(): void
    {
        // This method will be called before rendering to ensure
        // that the tree items have the proper CSS classes applied
        // The actual class application happens in the getTreeRecordClasses method
    }

    /**
     * Create select options for select/radio elements
     */
    protected function createSelectOptions($elementableModel, array $optionsData): void
    {
        if (!$elementableModel || empty($optionsData)) {
            return;
        }

        // Check if the model supports options (SelectInputFormElement or RadioInputFormElement)
        if (!method_exists($elementableModel, 'options')) {
            return;
        }

        foreach ($optionsData as $index => $optionData) {
            if (empty($optionData['label'])) {
                continue; // Skip options without labels
            }

            $optionData['order'] = $index + 1;

            // Create the option using the existing helper method
            if ($elementableModel instanceof \App\Models\FormBuilding\SelectInputFormElement) {
                \App\Models\FormBuilding\SelectOptionFormElement::createForSelect($elementableModel, $optionData);
            } elseif ($elementableModel instanceof \App\Models\FormBuilding\RadioInputFormElement) {
                \App\Models\FormBuilding\SelectOptionFormElement::createForRadio($elementableModel, $optionData);
            }
        }
    }

    /**
     * Update select options for select/radio elements
     */
    protected function updateSelectOptions($elementableModel, array $optionsData): void
    {
        if (!$elementableModel || !method_exists($elementableModel, 'options')) {
            return;
        }

        // Delete existing options
        $elementableModel->options()->delete();

        // Create new options
        $this->createSelectOptions($elementableModel, $optionsData);
    }
}
