<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Filament\Components\FormVersionBuilder;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\FormVersion;
use App\Models\FormBuilding\FormElement;
use Filament\Resources\Pages\Page;
use Filament\Forms\Form;
use Filament\Actions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

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
                ->form($this->getFormElementSchema())
                ->action(function (array $data) {
                    $data['form_version_id'] = $this->record->id;

                    // Extract polymorphic data
                    $elementType = $data['elementable_type'];
                    $elementableData = $data['elementable_data'] ?? [];
                    unset($data['elementable_data']);

                    // Create the main FormElement
                    $formElement = FormElement::create($data);

                    // Create and attach the polymorphic model if there's data
                    if (!empty($elementableData) && class_exists($elementType)) {
                        $elementableModel = new $elementType($elementableData);
                        $formElement->elementable()->save($elementableModel);
                    }

                    $this->getSavedNotification('Form element created successfully!')?->send();

                    // Refresh the page to update the tree
                    $this->redirect($this->getResource()::getUrl('build', ['record' => $this->record]));
                }),
            Actions\Action::make('save')
                ->label('Save Changes')
                ->icon('heroicon-o-check')
                ->action('save')
                ->extraAttributes([
                    'style' => 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;'
                ]),
            Actions\Action::make('Preview Form')
                ->label('Preview Form')
                ->icon('heroicon-o-rocket-launch')
                ->action(function ($livewire) {
                    $formVersionId = $this->record->id;
                    $previewBaseUrl = env('FORM_PREVIEW_URL', '');
                    $previewUrl = rtrim($previewBaseUrl, '/') . '/preview/' . $formVersionId;
                    $livewire->js("window.open('$previewUrl', '_blank')");
                })
                ->color('primary'),
        ];
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
}
