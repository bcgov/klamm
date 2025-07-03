<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Filament\Components\FormVersionBuilder;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\FormVersion;
use App\Models\FormBuilding\FormElement;
use App\Models\FormBuilding\FormElementTag;
use App\Jobs\GenerateFormVersionJsonJob;
use App\Events\FormVersionUpdateEvent;
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

class BuildFormVersion extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = FormVersionResource::class;

    protected static string $view = 'filament.forms.resources.form-version-resource.pages.build-form-version';

    public array $data = [];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill($this->mutateFormDataBeforeFill([]));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FormVersionBuilder::schema()
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
                ->form($this->getFormElementSchema())
                ->action(function (array $data) {
                    try {
                        $data['form_version_id'] = $this->record->id;

                        // Remove template_id as it's only used for prefilling
                        unset($data['template_id']);

                        // Extract tags data before creating the element
                        $tagIds = $data['tags'] ?? [];
                        unset($data['tags']);

                        // Extract polymorphic data
                        $elementType = $data['elementable_type'];
                        $elementableData = $data['elementable_data'] ?? [];
                        unset($data['elementable_data']);

                        // Filter out null values from elementable data to let model defaults apply
                        $elementableData = array_filter($elementableData, function ($value) {
                            return $value !== null;
                        });

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

                        // Attach tags if any were selected
                        if (!empty($tagIds)) {
                            $formElement->tags()->attach($tagIds);
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

            // Actions\Action::make('broadcast_update')
            //     ->label('Broadcast Update')
            //     ->icon('heroicon-o-signal')
            //     ->color('info')
            //     ->outlined()
            //     ->action(function () {
            //         $this->triggerUpdateEvent('manual_broadcast');
            //     }),

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
                                    $set('name', $template->name . ' (Copy)');
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
                            \Filament\Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            \Filament\Forms\Components\Textarea::make('description')
                                ->rows(3),
                            \Filament\Forms\Components\TextInput::make('help_text')
                                ->maxLength(500),
                            \Filament\Forms\Components\Select::make('elementable_type')
                                ->label('Element Type')
                                ->options(\App\Models\FormBuilding\FormElement::getAvailableElementTypes())
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Clear existing elementable data when type changes
                                    $set('elementable_data', []);
                                }),
                            \Filament\Forms\Components\Toggle::make('is_visible')
                                ->label('Visible')
                                ->default(true),
                            \Filament\Forms\Components\Toggle::make('visible_web')
                                ->label('Visible on Web')
                                ->default(true),
                            \Filament\Forms\Components\Toggle::make('visible_pdf')
                                ->label('Visible on PDF')
                                ->default(true),
                            \Filament\Forms\Components\Toggle::make('is_template')
                                ->label('Is Template')
                                ->default(false),
                            \Filament\Forms\Components\Select::make('tags')
                                ->label('Tags')
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
