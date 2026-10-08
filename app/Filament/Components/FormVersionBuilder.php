<?php

namespace App\Filament\Components;

use App\Events\FormVersionUpdateEvent;
use App\Filament\Components\AutocompleteBadgeList;
use App\Filament\Plugins\MonacoEditor\CustomMonacoEditor;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\StyleSheet;
use Closure;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\View;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Log;

class FormVersionBuilder
{
    public static function schema($editable = true)
    {
        $styleSheetOptions = self::fetchStyleSheetOptions();
        $formScriptOptions = self::fetchFormScriptOptions();

        return Tabs::make()
            ->id('build-tabs')
            ->columnSpanFull()
            ->persistTab()
            ->tabs([
                self::makeBuildTab($editable),
                self::makeScriptsTab($editable, $formScriptOptions),
                self::makeStyleTab($editable, $styleSheetOptions),
            ]);
    }

    /**
     * Fetch autocomplete options for Monaco editor from form element tree.
     *
     * @param int|null $formVersionId
     * @param string $context 'style' or 'script' to adjust formatting if needed
     * @return array
     */
    public static function fetchElementTreeAutocompleteOptions($formVersionId, $context = 'style')
    {
        if (!$formVersionId)
            return [];
        $elements = \App\Helpers\FormVersionHelper::visibleElements($formVersionId);
        return $elements->map(function ($element) use ($context) {
            // Format the type using the same logic as the TextInput::formatStateUsing
            $elementType = $element->elementable_type;
            $availableTypes = \App\Models\FormBuilding\FormElement::getAvailableElementTypes();
            $typeDisplay = $availableTypes[$elementType] ?? $elementType ?? 'Element';
            $labelBase = $element->label ?? $element->name ?? 'Element';
            $label = $labelBase . ' (' . $typeDisplay . ')';

            // Create the full reference ID (reference_id + uuid)
            $fullReferenceId = $element->getFullReferenceId();

            return [
                'label' => $label,
                // Insert selector and label/type as a comment for inline context
                'insertText' => $context == 'style' ? "[id='" . $fullReferenceId . "'] /* " . addslashes($label) . ' */ ' : "'" . $fullReferenceId . "' /* " . addslashes($label) . ' */ ',
                'detail' => "Selector: #$fullReferenceId\nLabel: $label\nName: {$element->name}\nType: $typeDisplay",
                'documentation' => "**Selector:** `#$fullReferenceId`  \n**Label:** $label  \n**Name:** {$element->name}  \n**Type:** $typeDisplay  \n**Reference ID:** " . ($element->reference_id ?: 'None') . "  \n**UUID:** {$element->uuid}",
            ];
        })->values()->toArray();
    }

    /**
     * Fetch options for style sheets, categorized by type. Used to populate "Attach Styles" dropdown with template stylesheets.
     * @return array{"Form Stylesheets": array, "Template Stylesheets": array|array{"Form Stylesheets": array, "Template Stylesheets": string[]}|array{"Form Stylesheets": string[], "Template Stylesheets": string[]}}
     */
    private static function fetchStyleSheetOptions(): array
    {
        $styleSheets = StyleSheet::with(['formVersion.form'])->get();
        $templateStyleSheets = [];
        $otherStyleSheets = [];

        foreach ($styleSheets as $sheet) {
            if ($sheet->type === 'template') {
                $templateStyleSheets[$sheet->id] = "Template Stylesheet ({$sheet->filename})";
            } else {
                $formVersion = $sheet->formVersion;
                $form = $formVersion?->form;

                if ($formVersion && $form) {
                    $otherStyleSheets[$sheet->id] = "[{$form->form_id}] {$form->form_title} - v{$formVersion->version_number} ({$sheet->type})";
                } else {
                    $name = $sheet->filename ?? "Stylesheet #{$sheet->id}";
                    $otherStyleSheets[$sheet->id] = "[Orphan] {$name} ({$sheet->type})";
                }
            }
        }

        return [
            'Template Stylesheets' => $templateStyleSheets,
            'Form Stylesheets' => $otherStyleSheets,
        ];
    }

    /**
     * Fetch options for form scripts, categorized by type. Used to populate "Attach Scripts" dropdown with template scripts.
     * @return array{"Form Scripts": array, "Template Scripts": array|array{"Form Scripts": array, "Template Scripts": string[]}|array{"Form Scripts": string[], "Template Scripts": string[]}}
     */
    private static function fetchFormScriptOptions(): array
    {

        $formScripts = FormScript::with(['formVersion.form'])->get();

        $templateScripts = [];
        $otherScripts = [];

        foreach ($formScripts as $script) {
            if ($script->type === 'template') {
                $templateScripts[$script->id] = "Template Script ({$script->filename})";
            } else {
                $formVersion = $script->formVersion;
                $form = $formVersion?->form;

                if ($formVersion && $form) {
                    $otherScripts[$script->id] = "[{$form->form_id}] {$form->form_title} - v{$formVersion->version_number} ({$script->type})";
                } else {
                    $name = $script->filename ?? "Script #{$script->id}";
                    $otherScripts[$script->id] = "[Orphan] {$name} ({$script->type})";
                }
            }
        }

        return [
            'Template Scripts' => $templateScripts,
            'Form Scripts' => $otherScripts,
        ];
    }


    /**
     * Make the schema for the Build tab
     * @param bool $editable
     * @return Tab
     */
    private static function makeBuildTab(bool $editable): Tab
    {
        return Tab::make('Build')
            ->icon('heroicon-o-cog')
            ->live()
            ->reactive()
            ->schema([
                View::make('components.form-element-tree')
                    ->viewData(function ($livewire) use ($editable) {
                        $record = $livewire->getRecord() ?? null;
                        return [
                            'formVersionId' => $record?->id,
                            'editable' => $editable,
                        ];
                    })
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Make the schema for the Scripts tab
     * @param bool $editable
     * @param array $formScriptOptions
     * @return Tab
     */
    private static function makeScriptsTab(bool $editable, array $formScriptOptions): Tab
    {
        return Tab::make('Scripts')
            ->icon('heroicon-o-code-bracket-square')
            ->schema([
                Grid::make()
                    ->columns(6)
                    ->schema([
                        Hidden::make('selectedFormScriptName'),
                        AutocompleteBadgeList::make(self::fetchAutocompleteOptions('script'), 'script', 'Form Elements')
                            ->columnSpan(1),
                        Tabs::make('form_script_type')
                            ->contained(false)
                            ->tabs([
                                self::makeWebScriptsTab($editable, $formScriptOptions),
                                self::makePDFScriptsTab($editable, $formScriptOptions),
                            ])
                            ->columnSpan(5),
                    ]),
            ]);
    }

    /**
     * Make the schema for the Web Scripts tab, including Attach and Insert actions, and the Monaco editor
     * @param bool $editable
     * @param array $formScriptOptions
     * @return Tab
     */
    private static function makeWebScriptsTab(bool $editable, array $formScriptOptions): Tab
    {
        return Tab::make('web_form_script')
            ->label(fn($livewire) => FormScript::where('form_version_id', optional($livewire->getRecord())->id)->where('type', 'pdf')->exists() ? 'Web' : 'Scripts')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                Select::make('attached_form_script_ids')
                    ->label('Attach Scripts')
                    ->hint('Attach complete templates/constants. If you must modify one, import it in its entirety.')
                    ->hintIcon('heroicon-o-information-circle')
                    ->hintIconTooltip('These are included as-is and require no modification. To change a template or constant, use “Insert Javascript” to import the full content and edit the imported copy. If the base attached style is modified, all forms using it will be updated automatically')
                    ->options($formScriptOptions['Template Scripts'])
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->dehydrated(false)
                    ->default(function ($livewire) {
                        $record = $livewire->getRecord();
                        return $record
                            ? $record->formScripts()
                                ->where('type', 'template')
                                ->pluck('form_scripts.id')
                                ->map(fn($id) => (string) $id)
                                ->toArray()
                            : [];
                    })
                    ->afterStateHydrated(function ($state, callable $set, $livewire) {
                        if (!empty($state))
                            return;
                        $record = $livewire->getRecord();
                        $ids = $record
                            ? $record->formScripts()
                                ->where('type', 'template')
                                ->pluck('form_scripts.id')
                                ->map(fn($id) => (string) $id)
                                ->toArray()
                            : [];
                        $set('attached_form_script_ids', $ids);
                    })
                    ->afterStateUpdated(function ($state, $livewire) {
                        $record = $livewire->getRecord();
                        if ($record) {
                            $record->formScripts()->sync($state ?? []);
                        }
                    }),
                Actions::make([
                    Action::make('import_js_content_web')
                        ->label('Insert JavaScript')
                        ->icon('heroicon-o-document-arrow-down')
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->form(self::makeInsertActionForm($formScriptOptions, 'script'))
                        ->action(self::handleInsertAction('js_content_web', $formScriptOptions, 'script')),
                    Action::make('save_scripts_web')
                        ->label('Save Scripts')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->action(function (callable $get, $livewire) {
                            $record = $livewire->getRecord();
                            $jsContentWeb = $get('js_content_web') ?? '';
                            FormScript::createFormScript($record, $jsContentWeb, 'web');
                            // Fire update event for scripts
                            FormVersionUpdateEvent::dispatch(
                                $record->id,
                                $record->form_id,
                                $record->version_number,
                                ['web_scripts' => $jsContentWeb],
                                'scripts',
                                false
                            );
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Scripts Saved')
                                ->body('JavaScript form scripts have been saved successfully.')
                                ->send();
                        }),
                ])->alignment(Alignment::Center),
                CustomMonacoEditor::make('js_content_web')
                    ->label(false)
                    ->language('javascript')
                    ->theme('vs-dark')
                    ->live()
                    ->autocomplete(self::fetchAutocompleteOptions('script'))
                    ->reactive()
                    ->height('475px')
                    ->disabled(!$editable),
            ]);
    }

    /**
     * Make the schema for the PDF Scripts tab, including Attach and Insert actions, and the Monaco editor
     * @param bool $editable
     * @param array $formScriptOptions
     * @return Tab
     */
    private static function makePDFScriptsTab(bool $editable, array $formScriptOptions): Tab
    {
        return Tab::make('pdf_form_script')
            ->label('PDF')
            ->icon('heroicon-o-document-text')
            ->visible(fn($livewire) => FormScript::where('form_version_id', optional($livewire->getRecord())->id)->where('type', 'pdf')->exists())
            ->schema([
                Actions::make([
                    Action::make('import_js_content_pdf')
                        ->label('Insert JavaScript')
                        ->icon('heroicon-o-document-arrow-down')
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->form(self::makeInsertActionForm($formScriptOptions, 'script'))
                        ->action(self::handleInsertAction('js_content_pdf', $formScriptOptions, 'script')),
                    Action::make('save_scripts_pdf')
                        ->label('Save Scripts')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->action(function (callable $get, $livewire) {
                            $record = $livewire->getRecord();
                            $jsContentWeb = $get('js_content_web') ?? '';
                            $jsContentPdf = $get('js_content_pdf') ?? '';

                            FormScript::createFormScript($record, $jsContentWeb, 'web');
                            FormScript::createFormScript($record, $jsContentPdf, 'pdf');

                            // Fire update event for scripts
                            FormVersionUpdateEvent::dispatch(
                                $record->id,
                                $record->form_id,
                                $record->version_number,
                                ['web_scripts' => $jsContentWeb, 'pdf_scripts' => $jsContentPdf],
                                'scripts',
                                false
                            );

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Scripts Saved')
                                ->body('JavaScript form scripts have been saved successfully.')
                                ->send();
                        }),
                    Action::make('migrate_pdf_scripts_to_web')
                        ->label('Migrate to Web')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->action(function (callable $set, $livewire) {
                            $record = $livewire->getRecord();
                            if (!$record) {
                                \Filament\Notifications\Notification::make()->danger()->title('No Record')->body('Cannot migrate without a record.')->send();
                                return;
                            }
                            $pdf = FormScript::where('form_version_id', $record->id)->where('type', 'pdf')->first();
                            if (!$pdf) {
                                \Filament\Notifications\Notification::make()->warning()->title('Nothing to Migrate')->body('No PDF script found.')->send();
                                return;
                            }

                            $pdfContent = $pdf->getJsContent() ?? '';
                            $web = FormScript::where('form_version_id', $record->id)->where('type', 'web')->first();
                            $webContent = $web?->getJsContent() ?? '';

                            $banner = "/* Migrated from PDF on " . now()->toDateTimeString() . " */\n";
                            $newWebContent = trim(rtrim($webContent) . "\n\n" . $banner . $pdfContent);

                            if ($web) {
                                $web->saveJsContent($newWebContent);
                            } else {
                                FormScript::createFormScript($record, $newWebContent, 'web');
                            }

                            // Clear PDF file content (optional) then delete PDF record
                            $pdf->saveJsContent('');
                            try {
                                $pdf->delete();
                            } catch (\Exception $e) {
                                Log::warning('Failed to delete PDF script during migration', ['id' => $pdf->id, 'error' => $e->getMessage()]);
                            }

                            // Update form state
                            $set('js_content_web', $newWebContent);
                            $set('js_content_pdf', '');

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Scripts Migrated')
                                ->body('PDF scripts migrated to Web and PDF removed.')
                                ->send();
                        }),
                ])
                    ->alignment(Alignment::Center),

                CustomMonacoEditor::make('js_content_pdf')
                    ->label(false)
                    ->language('javascript')
                    ->theme('vs-dark')
                    ->reactive()
                    ->height('475px')
                    ->live()
                    ->autocomplete(self::fetchAutocompleteOptions('script'))
                    ->disabled(!$editable),
            ]);
    }

    /**
     * Make the schema for the Style tab
     * @param bool $editable
     * @param array $styleSheetOptions
     * @return Tab
     */
    private static function makeStyleTab(bool $editable, array $styleSheetOptions): Tab
    {
        return Tab::make('Style')
            ->icon('heroicon-o-paint-brush')
            ->schema([
                Grid::make([
                    'default' => 6,
                    'sm' => 1,
                ])
                    ->columns(6)
                    ->schema([
                        Hidden::make('selectedStyleSheetName'),
                        AutocompleteBadgeList::make(self::fetchAutocompleteOptions('style'), 'style', 'Form Elements')
                            ->columnSpan(1),
                        Tabs::make('style_sheet_type')
                            ->contained(false)
                            ->columnSpan(5)
                            ->tabs([
                                self::makeWebStyleTab($editable, $styleSheetOptions),
                                self::makePDFStyleTab($editable, $styleSheetOptions),
                            ])
                    ]),
            ]);
    }

    /**
     * Make the schema for the Web StyleSheet tab, including Attach and Insert actions, and the Monaco editor
     * @param bool $editable
     * @param array $styleSheetOptions
     * @return Tab
     */
    private static function makeWebStyleTab(bool $editable, array $styleSheetOptions): Tab
    {
        return Tab::make('web_style_sheet')
            ->label(fn($livewire) => StyleSheet::where('form_version_id', optional($livewire->getRecord())->id)->where('type', 'pdf')->exists() ? 'Web' : 'Styles')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                // Updated: ensure attached template styles show as selected on load
                Select::make('attached_style_sheet_ids')
                    ->label('Attach Styles')
                    ->hint('Attach complete templates/constants. If you must modify one, import it in its entirety.')
                    ->hintIcon('heroicon-o-information-circle')
                    ->hintIconTooltip('These are included as-is and require no modification. To change a template or constant, use “Insert CSS" to import the full content and edit the imported copy. If the base attached style is modified, all forms using it will be updated automatically.')
                    ->options($styleSheetOptions['Template Stylesheets'])
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->dehydrated(false)
                    ->default(function ($livewire) {
                        $record = $livewire->getRecord();
                        return $record
                            ? $record->styleSheets()
                                ->where('type', 'template')
                                ->pluck('style_sheets.id')
                                ->map(fn($id) => (string) $id)
                                ->toArray()
                            : [];
                    })
                    ->afterStateHydrated(function ($state, callable $set, $livewire) {
                        if (!empty($state))
                            return;
                        $record = $livewire->getRecord();
                        $ids = $record
                            ? $record->styleSheets()
                                ->where('type', 'template')
                                ->pluck('style_sheets.id')
                                ->map(fn($id) => (string) $id)
                                ->toArray()
                            : [];
                        $set('attached_style_sheet_ids', $ids);
                    })
                    ->afterStateUpdated(function ($state, $livewire) {
                        $record = $livewire->getRecord();
                        if ($record) {
                            $record->styleSheets()->sync($state ?? []);
                        }
                    }),
                Actions::make([
                    Action::make('import_css_content_web')
                        ->label('Insert CSS')
                        ->icon('heroicon-o-document-arrow-down')
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->form(self::makeInsertActionForm($styleSheetOptions, 'style'))
                        ->action(self::handleInsertAction('css_content_web', $styleSheetOptions, 'style')),
                    Action::make('save_styles_web')
                        ->label('Save Styles')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->action(function (callable $get, $livewire) {
                            $record = $livewire->getRecord();
                            $cssContentWeb = $get('css_content_web') ?? '';
                            StyleSheet::createStyleSheet($record, $cssContentWeb, 'web');
                            // Fire update event for styles
                            FormVersionUpdateEvent::dispatch(
                                $record->id,
                                $record->form_id,
                                $record->version_number,
                                ['web_styles' => $cssContentWeb],
                                'styles',
                                false
                            );
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Styles Saved')
                                ->body('CSS stylesheet has been saved successfully.')
                                ->send();
                        }),
                ])->alignment(Alignment::Center),
                CustomMonacoEditor::make('css_content_web')
                    ->label(false)
                    ->language('css')
                    ->theme('vs-dark')
                    ->live()
                    ->autocomplete(self::fetchAutocompleteOptions('style'))
                    ->reactive()
                    ->height('475px')
                    ->disabled(!$editable),
            ]);
    }

    /**
     * Make the schema for the PDF StyleSheet tab, including Attach and Insert actions, and the Monaco editor
     * @param bool $editable
     * @param array $styleSheetOptions
     * @return Tab
     */
    private static function makePDFStyleTab(bool $editable, array $styleSheetOptions): Tab
    {
        return Tab::make('pdf_style_sheet')
            ->label('PDF')
            ->icon('heroicon-o-document-text')
            ->visible(fn($livewire) => StyleSheet::where('form_version_id', optional($livewire->getRecord())->id)->where('type', 'pdf')->exists())
            ->schema([
                Actions::make([
                    Action::make('import_css_content_pdf')
                        ->label('Insert CSS')
                        ->icon('heroicon-o-document-arrow-down')
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->form(self::makeInsertActionForm($styleSheetOptions, 'style'))
                        ->action(self::handleInsertAction('css_content_pdf', $styleSheetOptions, 'style')),
                    Action::make('save_styles_pdf')
                        ->label('Save Styles')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->action(function (callable $get, $livewire) {
                            $record = $livewire->getRecord();
                            $cssContentWeb = $get('css_content_web') ?? '';
                            $cssContentPdf = $get('css_content_pdf') ?? '';
                            StyleSheet::createStyleSheet($record, $cssContentWeb, 'web');
                            StyleSheet::createStyleSheet($record, $cssContentPdf, 'pdf');
                            // Fire update event for styles
                            FormVersionUpdateEvent::dispatch(
                                $record->id,
                                $record->form_id,
                                $record->version_number,
                                ['web_styles' => $cssContentWeb, 'pdf_styles' => $cssContentPdf],
                                'styles',
                                false
                            );
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Styles Saved')
                                ->body('CSS stylesheets have been saved successfully.')
                                ->send();
                        }),
                    Action::make('migrate_pdf_styles_to_web')
                        ->label('Migrate to Web')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                        ->action(function (callable $set, $livewire) {
                            $record = $livewire->getRecord();
                            if (!$record) {
                                \Filament\Notifications\Notification::make()->danger()->title('No Record')->body('Cannot migrate without a record.')->send();
                                return;
                            }
                            $pdf = StyleSheet::where('form_version_id', $record->id)->where('type', 'pdf')->first();
                            if (!$pdf) {
                                \Filament\Notifications\Notification::make()->warning()->title('Nothing to Migrate')->body('No PDF stylesheet found.')->send();
                                return;
                            }

                            $pdfContent = $pdf->getCssContent() ?? '';
                            $web = StyleSheet::where('form_version_id', $record->id)->where('type', 'web')->first();
                            $webContent = $web?->getCssContent() ?? '';

                            $banner = "/* Migrated from PDF on " . now()->toDateTimeString() . " */\n";
                            $pdfPrintMediaQuery = "@media print {\n" . $pdfContent . "\n}\n";
                            $newWebContent = trim(
                                rtrim($webContent) . "\n\n" . $banner . $pdfPrintMediaQuery
                            );

                            if ($web) {
                                $web->saveCssContent($newWebContent);
                            } else {
                                StyleSheet::createStyleSheet($record, $newWebContent, 'web');
                            }

                            // Clear PDF file content (optional) then delete PDF record
                            $pdf->saveCssContent('');
                            try {
                                $pdf->delete();
                            } catch (\Exception $e) {
                                Log::warning('Failed to delete PDF stylesheet during migration', ['id' => $pdf->id, 'error' => $e->getMessage()]);
                            }

                            // Update form state
                            $set('css_content_web', $newWebContent);
                            $set('css_content_pdf', '');

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Styles Migrated')
                                ->body('PDF styles migrated to Web and PDF removed.')
                                ->send();
                        }),
                ])
                    ->alignment(Alignment::Center),
                CustomMonacoEditor::make('css_content_pdf')
                    ->label(false)
                    ->language('css')
                    ->theme('vs-dark')
                    ->live()
                    ->autocomplete(self::fetchAutocompleteOptions('style'))
                    ->reactive()
                    ->height('475px')
                    ->disabled(!$editable),
            ]);
    }

    /**
     * Make the schema for the Insert Style/Script Action
     * @param array $options
     * @param string $type
     * @return array<Placeholder|Repeater|Select|Textarea>
     */
    private static function makeInsertActionForm(array $options, string $type = 'script'): array
    {
        $autocompleteOptions = self::fetchAutocompleteOptions($type);
        $isScript = $type === 'script';
        $selectField = $isScript ? 'selectedFormScriptId' : 'selectedStyleSheetId';
        $previewField = $isScript ? 'js_preview' : 'css_preview';
        $label = $isScript ? 'Select a Form Script' : 'Select a Style Sheet';
        $previewLabel = $isScript ? 'JavaScript Content Preview' : 'CSS Content Preview';
        $placeholderContent = $isScript
            ? 'Select a script to preview its content.'
            : 'Select a stylesheet to preview its content.';

        $fields = [
            Select::make($selectField)
                ->label($label)
                ->options($options)
                ->required()
                ->live()
                ->searchable()
                ->reactive()
                ->afterStateUpdated(fn($state, callable $set) => self::processInsertSelection($state, $set, $options, $type)),
            // Show the form description if available
            Textarea::make('description')
                ->label('Form Description')
                ->readOnly()
                ->visible(fn($get) => !empty($get($selectField)) && !empty($get('description'))),
        ];

        // Add repeaters if autocompleteOptions is provided (for both scripts and styles)
        if ($autocompleteOptions) {
            $fields[] = Repeater::make('source_selections')
                ->label('Source Element Selections')
                ->schema([
                    Select::make('element_id')
                        ->label(fn($get) => 'Source #' . ($get('order') ?? ''))
                        ->searchable()
                        ->options(function () use ($autocompleteOptions) {
                            $livewire = \Livewire\Livewire::current();
                            $get = function ($key) use ($livewire) {
                                return $livewire->getState()[$key] ?? null;
                            };
                            $options = $autocompleteOptions($get, $livewire);
                            $selectOptions = [];
                            foreach ($options as $option) {
                                $selectOptions[$option['insertText']] = $option['label'];
                            }
                            return $selectOptions;
                        }),
                ])
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->visible(fn($get) => !empty($get($selectField)) && !empty($get('source_selections')));
            $fields[] = Repeater::make('target_selections')
                ->label('Target Element Selections')
                ->schema([
                    Select::make('element_id')
                        ->label(fn($get) => 'Target #' . ($get('order') ?? ''))
                        ->searchable()
                        ->options(function () use ($autocompleteOptions) {
                            $livewire = \Livewire\Livewire::current();
                            $get = function ($key) use ($livewire) {
                                return $livewire->getState()[$key] ?? null;
                            };
                            $options = $autocompleteOptions($get, $livewire);
                            $selectOptions = [];
                            foreach ($options as $option) {
                                $selectOptions[$option['insertText']] = $option['label'];
                            }
                            return $selectOptions;
                        }),
                ])
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->visible(fn($get) => !empty($get($selectField)) && !empty($get('target_selections')));
        }

        $fields[] = TextArea::make($previewField)
            ->label($previewLabel)
            ->rows(12)
            ->readOnly()
            ->visible(fn($get) => !empty($get($previewField)));
        $fields[] = Placeholder::make($previewField . '_placeholder')
            ->label('')
            ->content($placeholderContent)
            ->visible(fn($get) => empty($get($previewField)));

        return $fields;
    }

    /**
     * Fetch the autocomplete options for the form elements based on the current record and context.
     * @param mixed $context
     * @return Closure // The closure takes parameters: $get, $livewire
     */
    private static function fetchAutocompleteOptions($context): Closure
    {
        return function ($get, $livewire) use ($context) {
            $record = $livewire->getRecord();
            if (!$record || !$record->id) {
                return [];
            }
            return self::fetchElementTreeAutocompleteOptions($record->id, $context);
        };
    }

    /**
     * Resolve the import action for scripts or styles, replacing placeholders with selected element IDs and appending the content to the existing field.
     * @param string $contentField
     * @param array $options
     * @param string $type
     * @return Closure // The closure takes parameters: array $data, callable $get, callable $set, $livewire
     */
    private static function handleInsertAction(string $contentField, array $options, string $type = 'script'): Closure
    {
        return function (array $data, callable $get, callable $set, $livewire) use ($contentField, $options, $type) {
            // Map type to model and field names
            $modelClass = $type === 'script'
                ? FormScript::class
                : StyleSheet::class;
            $idField = $type === 'script'
                ? 'selectedFormScriptId'
                : 'selectedStyleSheetId';
            $getContentMethod = $type === 'script'
                ? 'getJsContent'
                : 'getCssContent';

            $content = '';
            $elementMappings = [];
            $sourceSelections = $data['source_selections'] ?? [];
            $targetSelections = $data['target_selections'] ?? [];

            // Process element replacements
            $processElementReplacement = function ($fullSelection, $index, $type, $prefix = '') use (&$elementMappings) {
                if ($fullSelection && $fullSelection !== '#{source_id}' && $fullSelection !== '#{target_id}') {
                    if (preg_match("/^'([^']+)'\s*\/\*\s*(.+?)\s*\*\/\s*$/", $fullSelection, $matches)) {
                        $cleanId = $matches[1];
                        $comment = $matches[2];
                        $elementMappings[] = "$type #{$index}: {$comment} (ID: {$cleanId})";
                        return $prefix . $cleanId;
                    }
                }
                return $fullSelection;
            };

            $record = $modelClass::find($data[$idField] ?? null);
            if ($record && method_exists($record, $getContentMethod)) {
                $content = $record->{$getContentMethod}();

                // Replace source placeholders
                if (!empty($sourceSelections)) {
                    $sourceIndex = 0;
                    $content = preg_replace_callback('/#{source_id}/', function ($matches) use ($sourceSelections, &$sourceIndex, $processElementReplacement, $type) {
                        $fullSelection = $sourceSelections[$sourceIndex]['element_id'] ?? '#{source_id}';
                        $sourceIndex++;
                        $prefix = ($type === 'style') ? '#' : '';
                        return $processElementReplacement($fullSelection, $sourceIndex, 'Source', $prefix);
                    }, $content);
                }

                // Replace target placeholders
                if (!empty($targetSelections)) {
                    $targetIndex = 0;
                    $patterns = ['/#{target_id}/', '/#target_id/'];

                    foreach ($patterns as $pattern) {
                        $content = preg_replace_callback($pattern, function ($matches) use ($targetSelections, &$targetIndex, $processElementReplacement, $type) {
                            if ($targetIndex >= count($targetSelections)) {
                                return $matches[0]; // Return original if no more selections
                            }
                            $fullSelection = $targetSelections[$targetIndex]['element_id'] ?? '#{target_id}';
                            $targetIndex++;
                            $prefix = ($type === 'style') ? '#' : '';
                            return $processElementReplacement($fullSelection, $targetIndex, 'Target', $prefix);
                        }, $content);
                    }
                }
            }

            $existing = $get($contentField);
            $selected = null;
            foreach ($options as $group) {
                if (isset($group[$data[$idField] ?? null])) {
                    $selected = $group[$data[$idField]];
                    break;
                }
            }

            $comment = "/* Imported from {$selected}";
            if (!empty($elementMappings)) {
                $comment .= "\n * Element Mappings:\n * " . implode("\n * ", $elementMappings);
            }
            $comment .= " */\n\n";
            $appended = rtrim($existing) . "\n\n" . $comment . $content;
            $set($contentField, $appended);
        };
    }

    /**
     * Process the selection made in the Insert JavaScript/CSS action dropdown.
     * It populates the description, source and target element selectors, and content previewer based on the selected item. 
     * @param mixed $id // The ID of the selected script or stylesheet
     * @param callable $set 
     * @param array $options // The options array used to find the display name
     * @param string $type // 'script' or 'style'
     * @return void
     */
    private static function processInsertSelection($id, callable $set, array $options, string $type): void
    {
        $isScript = $type === 'script';
        $nameField = $isScript ? 'selectedFormScriptName' : 'selectedStyleSheetName';
        $previewField = $isScript ? 'js_preview' : 'css_preview';

        // Find the display name
        $displayName = null;
        foreach ($options as $group) {
            if (isset($group[$id])) {
                $displayName = $group[$id];
                break;
            }
        }
        $set($nameField, $displayName);

        if (!$id) {
            $set($previewField, '');
            return;
        }

        // Find the record
        $record = $isScript ? FormScript::find($id) : StyleSheet::find($id);
        if (!$record) {
            $set($previewField, '');
            return;
        }

        $content = $isScript ? ($record->getJsContent() ?? '') : ($record->getCssContent() ?? '');
        $set('description', $record->description ?? '');

        // Count and replace placeholders
        $sourceCount = preg_match_all('/#{source_id}/', $content);

        $isScript
            ? $targetCount = preg_match_all('/#{target_id}/', $content)
            : $targetCount = preg_match_all('/#{target_id}/', $content) + preg_match_all('/#target_id/', $content);

        // Replace source placeholders
        $sourceIndex = 0;
        $content = preg_replace_callback('/#{source_id}/', function () use (&$sourceIndex) {
            $sourceIndex++;
            return "'#{source_id}' /* Source #{$sourceIndex} */";
        }, $content);

        // Replace target placeholders in preview
        $targetIndex = 0;
        if ($isScript) {
            $content = preg_replace_callback('/#{target_id}/', function () use (&$targetIndex) {
                $targetIndex++;
                return "'#{target_id}' /* Target #{$targetIndex} */";
            }, $content);
        } else {
            $content = preg_replace_callback('/#{target_id}|#target_id/', function () use (&$targetIndex) {
                $targetIndex++;
                return "'#{target_id}' /* Target #{$targetIndex} */";
            }, $content);
        }

        // Build selection arrays
        $set('source_selections', array_map(fn($i) => ['element_id' => null, 'order' => $i], range(1, $sourceCount)));
        $set('target_selections', array_map(fn($i) => ['element_id' => null, 'order' => $i], range(1, $targetCount)));
        $set($previewField, $content);
    }
}
