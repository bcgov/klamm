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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Placeholder;

class BuildFormVersion extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = FormVersionResource::class;

    protected static string $view = 'filament.forms.resources.form-version-resource.pages.build-form-version';

    public array $data = [];

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
                            \Filament\Forms\Components\Grid::make(2)
                                ->schema([
                                    \Filament\Forms\Components\Toggle::make('is_read_only')
                                        ->label('Read Only')
                                        ->default(false),
                                    \Filament\Forms\Components\Toggle::make('save_on_submit')
                                        ->label('Save on Submit')
                                        ->when($this->shouldShowTooltips(), function ($component) {
                                            return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'If this element\'s data should be saved when the form is submitted');
                                        })
                                        ->default(true),
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
}
