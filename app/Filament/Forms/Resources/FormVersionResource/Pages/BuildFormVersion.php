<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Filament\Components\FormVersionBuilder;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\FormElement;
use App\Jobs\GenerateFormVersionJsonJob;
use App\Events\FormVersionUpdateEvent;
use App\Filament\Forms\Resources\FormResource;
use App\Helpers\DataBindingsHelper;
use App\Helpers\ElementPropertiesHelper;
use App\Helpers\GeneralTabHelper;
use App\Services\FormVersionJsonService;
use Filament\Resources\Pages\Page;
use Filament\Forms\Form;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\ActionSize;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Set;
use Filament\Forms\Components\FileUpload;
use App\Filament\Forms\Helpers\SchemaParser;
use Filament\Forms\Components\Wizard;
use App\Jobs\ImportFormVersionElementsJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Support\Exceptions\Halt;
use App\Helpers\FormElementHelper;
use App\Helpers\FormVersionHelper;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

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
    public array $importWizard = [];
    public array $importJobStatus = [
        'status' => null,
        'done' => false,
        'cacheKey' => null,
    ];
    public array $invalidByElement = [];
    protected bool $validationPopupShown = false;

    protected function isEditable(): bool
    {
        return $this->record->status === 'draft';
    }

    protected function getFormattedStatusName(): string
    {
        return $this->record->getFormattedStatusName();
    }

    public function mount(int|string $record): void
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
                ->extraAttributes(['data-action' => 'add_form_element'])
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
                        $elementType = $data['elementable_type'] ?? null;

                        // If using template and elementType is not set, get it from the template
                        if ($templateId && !$elementType) {
                            $template = FormElement::find($templateId);
                            if ($template) {
                                $elementType = $template->elementable_type;
                            }
                        }

                        $elementableData = $data['elementable_data'] ?? [];
                        unset($data['elementable_data']);

                        // Extract options data for select/radio elements before creating the main model
                        $optionsData = null;
                        if (isset($elementableData['options'])) {
                            $optionsData = $elementableData['options'];
                            unset($elementableData['options']);
                        }

                        // Filter out null values from elementable data to let model defaults apply
                        // Keep false values and empty strings as they are valid values
                        $elementableData = array_filter($elementableData, function ($value) {
                            return $value !== null;
                        });

                        // Set source_element_id if created from template
                        if ($templateId) {
                            $data['source_element_id'] = $templateId;
                        }

                        // If creating from template, use the cloning method
                        if ($templateId) {
                            $template = FormElement::find($templateId);
                            if ($template && $template->isTemplate()) {
                                // Clone the template with all its children
                                $formElement = $template->cloneWithChildren($this->record->id);

                                // Update the cloned element with any overrides from the form data
                                $updateData = [];
                                if (!empty($data['name'])) {
                                    $updateData['name'] = $data['name'];
                                }
                                if (!empty($data['description'])) {
                                    $updateData['description'] = $data['description'];
                                }
                                if (!empty($data['help_text'])) {
                                    $updateData['help_text'] = $data['help_text'];
                                }
                                if (isset($data['is_required'])) {
                                    $updateData['is_required'] = $data['is_required'];
                                }
                                if (isset($data['visible_web'])) {
                                    $updateData['visible_web'] = $data['visible_web'];
                                }
                                if (isset($data['visible_pdf'])) {
                                    $updateData['visible_pdf'] = $data['visible_pdf'];
                                }
                                if (isset($data['is_read_only'])) {
                                    $updateData['is_read_only'] = $data['is_read_only'];
                                }
                                if (isset($data['save_on_submit'])) {
                                    $updateData['save_on_submit'] = $data['save_on_submit'];
                                }

                                if (!empty($updateData)) {
                                    $formElement->update($updateData);
                                }

                                // Update elementable data if provided
                                if (!empty($elementableData) && $formElement->elementable) {
                                    $formElement->elementable->update($elementableData);
                                }

                                // Update tags if provided
                                if (!empty($tagIds)) {
                                    $formElement->tags()->sync($tagIds);
                                }
                            } else {
                                throw new \InvalidArgumentException('Template not found or is not a valid template.');
                            }
                        } else {
                            // Ensure elementable_type is set for non-template creation
                            if (!isset($data['elementable_type'])) {
                                throw new \InvalidArgumentException('Element type is required when not using a template.');
                            }
                            // Create normally without template
                            // Create the polymorphic model first if there's data
                            $elementableModel = null;
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
                                FormElementHelper::createSelectOptions($elementableModel, $optionsData);
                            }

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
                                            'condition' => $bindingData['condition'] ?? null,
                                            'order' => $index + 1,
                                        ]);
                                    }
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

                        // Show appropriate success message
                        if ($templateId) {
                            $template = FormElement::find($templateId);
                            $childrenCount = $template ? $template->children()->count() : 0;
                            $message = $childrenCount > 0
                                ? "Form element created successfully from template with {$childrenCount} child element(s)!"
                                : 'Form element created successfully from template!';
                        } else {
                            $message = 'Form element created successfully!';
                        }

                        $this->getSavedNotification($message)?->send();

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
                ->visible($this->isEditable())
                ->modalHeading('Import Form Template')
                ->modalDescription('Upload a JSON template to import form elements and structure.')
                ->steps([
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
                                        $this->importWizard = [
                                            'schema_content' => $content,
                                            'parsed_content' => $parsed,
                                        ];
                                    } else {
                                        $set('schema_content', null);
                                        $set('parsed_content', null);
                                    }
                                }),
                        ])
                        ->afterValidation(function (Get $get) {
                            // Reset parsed schema if a new file is uploaded
                            if ($get('schema_content') == null) {
                                throw new Halt();
                            }
                        }),
                    Wizard\Step::make('Preview & Import')
                        ->schema([
                            \Filament\Forms\Components\Textarea::make('schema_content')
                                ->label('Schema Content')
                                ->rows(10)
                                ->disabled()
                                ->helperText('This is the raw JSON content of the uploaded schema file.'),
                            \Filament\Forms\Components\Textarea::make('parsed_content')
                                ->label('Parsed Schema')
                                ->rows(10)
                                ->disabled()
                                ->formatStateUsing(fn($state) => \App\Filament\Forms\Resources\FormVersionResource\Pages\BuildFormVersion::formatJsonForTextarea($state))
                                ->helperText('This is the parsed schema structure.'),
                        ]),

                ])
                ->modalHeading('Confirm Import')
                ->modalDescription('Importing a form from a JSON export will introduce new form fields to the existing form.')
                ->modalSubmitActionLabel('Import Form')
                ->action(function () {
                    $this->importParsedSchemaElements();
                }),

            ActionGroup::make([
                $this->makeDownloadJsonAction('download_old_json', 'Download v1', 1),
                $this->makeDownloadJsonAction('download_json', 'Download v2', 2),
                $this->makeCopyJsonAction('copy_json_v1', 'Copy v1 to Clipboard', 1),
                $this->makeCopyJsonAction('copy_json_v2', 'Copy v2 to Clipboard', 2),
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
                    Notification::make()
                        ->danger()
                        ->title('Authentication Error')
                        ->body('You must be logged in to download JSON files.')
                        ->send();
                    return;
                }

                // Use shared validation method
                $issues = $this->validateFormFields();

                if (!empty($issues)) {
                    $this->handleValidationFailures($issues);
                    return;
                }

                // All clear - dispatch export
                GenerateFormVersionJsonJob::dispatch($this->record, $userId, $version);

                Notification::make()
                    ->info()
                    ->title('Generating JSON')
                    ->body('Your JSON file is being generated. You will be notified when it is ready.')
                    ->send();
            });
    }

    protected function makeCopyJsonAction(string $name, string $label, int $version): Actions\Action
    {
        return Actions\Action::make($name)
            ->label($label)
            ->icon('heroicon-o-clipboard-document')
            ->color('info')
            ->outlined()
            ->action(function () use ($version) {
                try {
                    // Use shared validation method
                    $issues = $this->validateFormFields();

                    if (!empty($issues)) {
                        $this->handleValidationFailures($issues);
                        return;
                    }

                    // Generate JSON content using shared method
                    $jsonContent = $this->generateJsonForClipboard($version);

                    // Copy to clipboard using JavaScript
                    $this->dispatch('copy-to-clipboard', content: $jsonContent);

                    Notification::make()
                        ->success()
                        ->title('Copied to Clipboard')
                        ->body("JSON v{$version} has been copied to your clipboard.")
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->danger()
                        ->title('Copy Failed')
                        ->body('Failed to copy JSON to clipboard: ' . $e->getMessage())
                        ->persistent()
                        ->send();
                }
            });
    }

    /**
     * Validate form fields and return validation issues
     * Returns empty array if validation passes, or array of issues if validation fails
     */
    protected function validateFormFields(): array
    {
        $rules = $this->formStandardFieldRules();

        return $this->collectFormFieldIssues($rules, [$this, 'isFieldElement']);
    }

    /**
     * Handle validation failures by showing notifications and markers
     */
    protected function handleValidationFailures(array $issues): void
    {
        if ($this->validationPopupShown) {
            return;
        }
        $this->validationPopupShown = true;
        $this->invalidByElement = $this->collectFormFieldMarkers(
            $this->formStandardFieldRules(),
            [$this, 'isFieldElement']
        );
        $this->dispatch('ff-markers-updated', markers: $this->invalidByElement)
            ->to('form-element-tree-builder');

        $lines = array_map(
            fn($i) => sprintf('%s: %s — %s', $i['field'], $i['value'], $i['reason']),
            $issues
        );

        $payload = "Found invalid fields. Please fix the following:\n\n" .
            implode("\n", $lines) .
            "\n\nRule(s): Reference ID must be set and must not start with a number.";

        $bodyHtml = new HtmlString(
            '<div class="text-xs leading-5 whitespace-pre-wrap break-words max-w-full">'
                . nl2br(e($payload)) .
                '</div>'
        );

        Notification::make()
            ->danger()
            ->title('Form Standard Check')
            ->body($bodyHtml)
            ->persistent()
            ->send();
    }

    /**
     * Generate JSON data for a specific version
     */
    protected function generateJsonData(int $version): array
    {
        $jsonService = new FormVersionJsonService();

        $formVersion = \App\Models\FormBuilding\FormVersion::with([
            'form',
            'formElements.elementable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\FormBuilding\SelectInputFormElement::class => ['options'],
                    \App\Models\FormBuilding\RadioInputFormElement::class => ['options'],
                ]);
            },
            'formElements.dataBindings.formDataSource',
            'formDataSources' => function ($query) {
                $query->orderBy('form_versions_form_data_sources.order');
            },
            'webStyleSheet',
            'pdfStyleSheet',
            'webFormScript',
            'pdfFormScript'
        ])->find($this->record->id);

        switch ($version) {
            case 1:
                return $jsonService->generatePreMigrationJson($formVersion);
            case 2:
                return $jsonService->generateJson($formVersion);
            default:
                throw new \Exception("Unsupported format version: {$version}");
        }
    }

    /**
     * Generate JSON string for clipboard copying
     */
    protected function generateJsonForClipboard(int $version): string
    {
        $jsonData = $this->generateJsonData($version);
        return json_encode($jsonData, JSON_PRETTY_PRINT);
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
                        ->schema(function (callable $get) {
                            return GeneralTabHelper::getCreateSchema(
                                fn() => $this->shouldShowTooltips(),
                                true,
                                fn() => !empty($get('template_id'))
                            );
                        }),
                    \Filament\Forms\Components\Tabs\Tab::make('Element Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema(function (callable $get) {
                            return ElementPropertiesHelper::getCreateSchema(
                                $get('elementable_type')
                            );
                        })
                        ->hidden(fn(Get $get): bool => !empty($get('template_id'))),
                    \Filament\Forms\Components\Tabs\Tab::make('Data Bindings')
                        ->icon('heroicon-o-link')
                        ->schema(function (callable $get) {
                            return DataBindingsHelper::getCreateSchema(
                                $this->record,
                                fn() => $this->shouldShowTooltips()
                            );
                        })
                        ->hidden(fn(Get $get): bool => !empty($get('template_id'))),
                ])
                ->columnSpanFull(),
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
        return "{$this->record->form->form_id} Version {$this->record->version_number} - Form Builder";
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
     * Define which fields to validate and how to read/format them.
     * Add more entries here later (e.g., tags, visibility script).
     *
     * Each field config:
     *  - label: human label shown in the message
     *  - getter($el): returns raw value to validate
     *  - format?(mixed $value): optional, string for the message
     *  - rules: array of closures (fn($value, $el) => true|null on pass, or string reason on fail)
     */
    private function formStandardFieldRules(): array
    {
        return [
            'reference_id' => [
                'label' => 'Reference ID',
                'getter' => fn($el) => is_string($el->reference_id) ? trim($el->reference_id) : '',
                'rules' => [
                    fn($v) => ($v !== '') ?: 'empty',
                    fn($v) => ($v === '' || !preg_match('/^\d/', $v)) ?: 'starts with a number',
                ],
            ],

            // ——— for later if we want to validate other fields ———

            // 'custom_visibility' => [
            //     'label'  => 'Custom Visibility Script',
            //     'getter' => function ($el) {
            //         $props  = $el->element_properties;
            //         $arr    = is_array($props) ? $props : (json_decode($props ?? '[]', true) ?: []);
            //         $script = Arr::get($arr, 'visibilityCondition.value')
            //                 ?? Arr::get($arr, 'visibilityCondition')
            //                 ?? Arr::get($arr, 'visibility.condition')
            //                 ?? '';
            //         return is_string($script) ? trim($script) : '';
            //     },
            //     'rules' => [
            //         // Example heuristics (since PHP can’t parse JS): must contain "return" if non-empty
            //         fn ($v) => ($v === '' || stripos($v, 'return') !== false) ?: 'must contain a return statement',
            //         fn ($v) => (strlen((string)$v) <= 2000) ?: 'too long',
            //     ],
            // ],
        ];
    }

    /**
     * Run configured sanity checks on non-container elements.
     * Returns a flat list of issues with only: field, value, reason.
     */
    private function collectFormFieldIssues(array $fieldConfigs, ?callable $elementFilter = null): array
    {
        $elements = FormVersionHelper::visibleFieldElements($this->record->id);

        $issues = [];

        foreach ($elements as $el) {
            // optional higher-level filter (e.g., only real fields)
            if ($elementFilter && !$elementFilter($el)) {
                continue;
            }

            foreach ($fieldConfigs as $key => $cfg) {
                $label = $cfg['label'] ?? Str::headline(str_replace('_', ' ', $key));
                $getter = $cfg['getter'] ?? fn($e) => $e->{$key} ?? null;
                $format = $cfg['format'] ?? fn($v) => $this->formatIssueValue($v);
                $rules = $cfg['rules'] ?? [];

                $value = $getter($el);

                foreach ($rules as $rule) {
                    try {
                        $result = $rule($value, $el);
                    } catch (\Throwable $e) {
                        $result = 'validator error';
                    }

                    if ($result === true || $result === null) {
                        continue; // pass
                    }

                    $reason = is_string($result) && $result !== '' ? $result : 'invalid';

                    $issues[] = [
                        'field' => $label,
                        'value' => $format($value),
                        'reason' => $reason,
                    ];
                }
            }
        }

        // merge duplicate field+value reasons
        $bucket = [];
        foreach ($issues as $it) {
            $k = $it['field'] . '|' . $it['value'];
            $bucket[$k]['field'] = $it['field'];
            $bucket[$k]['value'] = $it['value'];
            $bucket[$k]['reasons'] = isset($bucket[$k]['reasons'])
                ? array_values(array_unique(array_merge($bucket[$k]['reasons'], [$it['reason']])))
                : [$it['reason']];
        }

        $flat = [];
        foreach ($bucket as $b) {
            $flat[] = [
                'field' => $b['field'],
                'value' => $b['value'],
                'reason' => implode(', ', $b['reasons']),
            ];
        }

        return $flat;
    }

    // Format any value into string for the message
    private function formatIssueValue($value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            $flat = array_map(function ($v) {
                if (is_scalar($v) || $v === null)
                    return (string) $v;
                return json_encode($v, JSON_UNESCAPED_UNICODE);
            }, $value);
            $flat = array_filter($flat, fn($s) => trim((string) $s) !== '');
            $out = implode(', ', array_slice($flat, 0, 10));
            return count($flat) > 10 ? $out . ' …' : $out;
        }
        if (is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    private function collectFormFieldMarkers(array $fieldConfigs, ?callable $elementFilter = null): array
    {
        $elements = FormVersionHelper::visibleFieldElements($this->record->id);

        $markers = [];

        foreach ($elements as $el) {
            if ($elementFilter && !$elementFilter($el)) {
                continue;
            }

            foreach ($fieldConfigs as $key => $cfg) {
                $getter = $cfg['getter'] ?? fn($e) => $e->{$key} ?? null;
                $rules = $cfg['rules'] ?? [];
                $value = $getter($el);

                foreach ($rules as $rule) {
                    try {
                        $result = $rule($value, $el);
                    } catch (\Throwable $e) {
                        $result = 'validator error';
                    }

                    if ($result === true || $result === null)
                        continue;

                    $reason = is_string($result) && $result !== '' ? $result : 'invalid';

                    $markers[$el->id][$key] = [
                        'reason' => $reason,
                        'value' => is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value),
                    ];

                    // for Reference ID only we can break early
                    break;
                }
            }
        }

        return $markers;
    }
    private function isFieldElement($el): bool
    {
        $type = (string) ($el->elementable_type ?? '');
        $basename = class_basename($type);

        // Skip containers / structure / non-data widgets
        if (
            Str::contains($basename, [
                'Container',
                'Section',
                'Group',
                'Page',
                'Button',
                'Display',
                'TextDisplay',
                'Heading',
                'Title',
                'Divider',
                'Separator',
                'Label',
                'Note',
            ])
        ) {
            return false;
        }

        // Respect DB flag when present
        if (isset($el->save_on_submit)) {
            if ($el->save_on_submit === false || $el->save_on_submit === 0 || $el->save_on_submit === '0') {
                return false;
            }
        }

        // Allow common inputs; fallback true for custom field classes
        return true;
    }

    private function showFieldIssuesNotification(array $issues): void
    {
        $lines = array_map(
            fn($i) => sprintf('%s: %s — %s', $i['field'], $i['value'], $i['reason']),
            $issues
        );

        $payload = "Found invalid fields. Please fix the following:\n\n"
            . implode("\n", $lines)
            . "\n\nRule(s): Reference ID must be set and must not start with a number.";

        $bodyHtml = new HtmlString(
            '<div class="text-xs leading-5 whitespace-pre-wrap break-words max-w-full">'
                . nl2br(e($payload))
                . '</div>'
        );

        Notification::make()
            ->danger()
            ->title('Form Standard Check')
            ->body($bodyHtml)
            ->persistent()
            ->send();
    }

    /**
     * Import all parsed schema elements as new form elements
     */
    public function importParsedSchemaElements()
    {
        $schemaContent = $this->importWizard['schema_content'] ?? null;
        if (empty($schemaContent)) {
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
            Auth::id()
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
        $error = Cache::get($cacheKey . '_error');

        if ($status === 'complete') {
            Cache::forget($cacheKey . '_status');
            Cache::forget($cacheKey . '_progress');
            session()->forget('import_job_cache_key');
            $this->importJobStatus['done'] = true;
            $this->importJobStatus['status'] = 'complete';
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Import Complete')
                ->body('Form elements have been successfully imported. The page will refresh to show the new elements.')
                ->send();

            $this->js('setTimeout(() => { window.location.reload(); }, 1500);');
        } elseif ($status === 'error') {
            Cache::forget($cacheKey . '_status');
            Cache::forget($cacheKey . '_error');
            Cache::forget($cacheKey . '_progress');
            session()->forget('import_job_cache_key');

            $this->importJobStatus['done'] = true;
            $this->importJobStatus['status'] = 'error';

            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body($error ?: 'An error occurred during import.')
                ->persistent()
                ->send();
        } else {
            $this->importJobStatus['done'] = false;
            $this->importJobStatus['status'] = $status ?: 'processing';
            $progress = Cache::get($cacheKey . '_progress');
            if ($progress) {
                $this->importJobStatus['progress'] = $progress;
            }
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $cacheKey = session('import_job_cache_key');
        if ($cacheKey && !isset($this->importJobStatus['cacheKey'])) {
            $this->importJobStatus = [
                'status' => Cache::get($cacheKey . '_status', 'unknown'),
                'done' => false,
                'cacheKey' => $cacheKey,
            ];
        }

        // If an import job is running, poll every 2 seconds
        if (
            isset($this->importJobStatus['cacheKey']) &&
            $this->importJobStatus['cacheKey'] &&
            !$this->importJobStatus['done']
        ) {
            $this->js(<<<JS
                setTimeout(() => {
                    if (typeof window.Livewire !== 'undefined') {
                        const component = window.Livewire.find('{$this->getId()}');
                        if (component) {
                            component.call('pollImportStatus');
                        }
                    }
                }, 2000);
            JS);
        }

        return parent::render();
    }
}
