<?php

namespace App\Filament\Components;

use App\Filament\Plugins\MonacoEditor\CustomMonacoEditor;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use App\Events\FormVersionUpdateEvent;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Alignment;
use App\Filament\Components\AutocompleteBadgeList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;

class FormVersionBuilder
{
    /**
     * Get autocomplete options for Monaco editor from form element tree.
     *
     * @param int|null $formVersionId
     * @param string $context 'style' or 'script' to adjust formatting if needed
     * @return array
     */
    public static function getElementTreeAutocompleteOptions($formVersionId, $context = 'style')
    {
        if (!$formVersionId) return [];
        $elements = \App\Models\FormBuilding\FormElement::where('form_version_id', $formVersionId)->get();
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

    public static function schema($editable = true)
    {
        $makeAutocompleteOptions = function ($context) {
            return function ($get, $livewire) use ($context) {
                $record = $livewire->getRecord();
                if (!$record || !$record->id) {
                    return [];
                }
                return \App\Filament\Components\FormVersionBuilder::getElementTreeAutocompleteOptions($record->id, $context);
            };
        };
        $autocompleteOptionsStyle = $makeAutocompleteOptions('style');
        $autocompleteOptionsScript = $makeAutocompleteOptions('script');
        $styleSheets = StyleSheet::with(['formVersion.form'])->get();

        $templateStyleSheets = [];
        $otherStyleSheets = [];

        foreach ($styleSheets as $sheet) {
            if ($sheet->type === 'template') {
                $templateStyleSheets[$sheet->id] = "Template Stylesheet ({$sheet->filename})";
            } else {
                $form = $sheet->formVersion->form;
                $version = $sheet->formVersion;
                $otherStyleSheets[$sheet->id] = "[{$form->form_id}] {$form->form_title} - v{$version->version_number} ({$sheet->type})";
            }
        }

        $styleSheetOptions = [
            'Template Stylesheets' => $templateStyleSheets,
            'Form Stylesheets' => $otherStyleSheets,
        ];

        $formScripts = FormScript::with(['formVersion.form'])->get();

        $templateScripts = [];
        $otherScripts = [];

        foreach ($formScripts as $script) {
            if ($script->type === 'template') {
                $templateScripts[$script->id] = "Template Script ({$script->filename})";
            } else if ($script->formVersion && $script->formVersion->form) {
                $form = $script->formVersion->form;
                $version = $script->formVersion;
                $otherScripts[$script->id] = "[{$form->form_id}] {$form->form_title} - v{$version->version_number} ({$script->type})";
            }
        }

        $formScriptOptions = [
            'Template Scripts' => $templateScripts,
            'Form Scripts' => $otherScripts,
        ];

        // Import action for scripts and styles
        $importContentAction = function ($contentField, $options, $type = 'script') {
            return function (array $data, callable $get, callable $set, $livewire) use ($contentField, $options, $type) {
                // Map type to model and field names
                $modelClass = $type === 'script'
                    ? \App\Models\FormBuilding\FormScript::class
                    : \App\Models\FormBuilding\StyleSheet::class;
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
        };

        // Import Form for scripts and styles
        $importContentForm = function ($options, $autocompleteOptions = null, $type = 'script') {
            $isScript = $type === 'script';
            $selectField = $isScript ? 'selectedFormScriptId' : 'selectedStyleSheetId';
            $nameField = $isScript ? 'selectedFormScriptName' : 'selectedStyleSheetName';
            $previewField = $isScript ? 'js_preview' : 'css_preview';
            $label = $isScript ? 'Select a Form Script' : 'Select a Style Sheet';
            $previewLabel = $isScript ? 'JavaScript Content Preview' : 'CSS Content Preview';
            $placeholderContent = $isScript
                ? 'Select a script to preview its content.'
                : 'Select a stylesheet to preview its content.';

            $afterStateUpdated = function ($state, callable $set, callable $get = null) use (
                $options,
                $autocompleteOptions,
                $isScript,
                $nameField,
                $previewField,
            ) {
                $displayName = null;
                foreach ($options as $group) {
                    if (isset($group[$state])) {
                        $displayName = $group[$state];
                        break;
                    }
                }
                $set($nameField, $displayName);
                $description = '';
                $content = '';
                if ($state) {
                    if ($isScript) {
                        $script = \App\Models\FormBuilding\FormScript::find($state);
                        if ($script) {
                            $content = $script->getJsContent() ?? '';
                            $description = $script->description ?? '';
                            $sourceCount = preg_match_all('/#{source_id}/', $content);
                            $targetCount = preg_match_all('/#{target_id}/', $content);
                            $content = preg_replace_callback('/#{source_id}/', function ($matches) use ($sourceCount) {
                                static $sourceIndex = 0;
                                $sourceIndex++;
                                return "'#{source_id}' /* Source #{$sourceIndex} */";
                            }, $content);
                            $content = preg_replace_callback('/#{target_id}/', function ($matches) use ($targetCount) {
                                static $targetIndex = 0;
                                $targetIndex++;
                                return "'#{target_id}' /* Target #{$targetIndex} */";
                            }, $content);
                            $sourceSelections = [];
                            for ($i = 0; $i < $sourceCount; $i++) {
                                $sourceSelections[] = ['element_id' => null, 'order' => $i + 1];
                            }
                            $set('source_selections', $sourceSelections);
                            $targetSelections = [];
                            for ($i = 0; $i < $targetCount; $i++) {
                                $targetSelections[] = ['element_id' => null, 'order' => $i + 1];
                            }
                            $set('description', $description);
                            $set('target_selections', $targetSelections);
                        }
                    } else {
                        $sheet = \App\Models\FormBuilding\StyleSheet::find($state);
                        if ($sheet) {
                            $description = $sheet->description ?? '';
                            $content = $sheet->getCssContent() ?? '';
                            if ($autocompleteOptions) {
                                $sourceCount = preg_match_all('/#{source_id}/', $content);
                                $targetCountCurly = preg_match_all('/#{target_id}/', $content);
                                $targetCountPlain = preg_match_all('/#target_id/', $content);
                                $targetCount = $targetCountCurly + $targetCountPlain;

                                $content = preg_replace_callback('/#{source_id}/', function ($matches) use ($sourceCount) {
                                    static $sourceIndex = 0;
                                    $sourceIndex++;
                                    return "'#{source_id}' /* Source #{$sourceIndex} */";
                                }, $content);

                                // Replace patterns in preview
                                $content = preg_replace_callback('/#{target_id}/', function ($matches) use ($targetCount) {
                                    static $targetIndex = 0;
                                    $targetIndex++;
                                    return "'#{target_id}' /* Target #{$targetIndex} */";
                                }, $content);
                                $content = preg_replace_callback('/#target_id/', function ($matches) use ($targetCount) {
                                    static $targetIndex = 0;
                                    $targetIndex++;
                                    return "'#target_id' /* Target #{$targetIndex} */";
                                }, $content);

                                $sourceSelections = [];
                                for ($i = 0; $i < $sourceCount; $i++) {
                                    $sourceSelections[] = ['element_id' => null, 'order' => $i + 1];
                                }
                                $set('source_selections', $sourceSelections);
                                $targetSelections = [];
                                for ($i = 0; $i < $targetCount; $i++) {
                                    $targetSelections[] = ['element_id' => null, 'order' => $i + 1];
                                }
                                $set('description', $description);
                                $set('target_selections', $targetSelections);
                            }
                        }
                    }
                }
                $set($previewField, $content);
            };

            $fields = [
                Select::make($selectField)
                    ->label($label)
                    ->options($options)
                    ->required()
                    ->live()
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated($afterStateUpdated),
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
        };

        return Tabs::make()
            ->columnSpanFull()
            ->persistTab()
            ->id('build-tabs')
            ->tabs([
                Tab::make('Build')
                    ->icon('heroicon-o-cog')
                    ->live()
                    ->reactive()
                    ->schema([
                        \Filament\Forms\Components\View::make('components.form-element-tree')
                            ->viewData(function ($livewire) use ($editable) {
                                $record = $livewire->getRecord() ?? null;
                                return [
                                    'formVersionId' => $record?->id,
                                    'editable' => $editable,
                                ];
                            })
                            ->columnSpanFull(),
                    ]),
                Tab::make('Style')
                    ->icon('heroicon-o-paint-brush')
                    ->schema([
                        Grid::make([
                            'default' => 6,
                            'sm' => 1,
                        ])
                            ->columns(6)
                            ->schema([
                                Hidden::make('selectedStyleSheetName'),
                                AutocompleteBadgeList::make($autocompleteOptionsStyle, 'style', 'Form Elements')
                                    ->columnSpan(1),
                                Tabs::make('style_sheet_type')
                                    ->contained(false)
                                    ->columnSpan(5)
                                    ->tabs([
                                        Tab::make('web_style_sheet')
                                            ->label('Web')
                                            ->icon('heroicon-o-globe-alt')
                                            ->schema([
                                                Actions::make([
                                                    Action::make('import_css_content_web')
                                                        ->label('Insert CSS')
                                                        ->icon('heroicon-o-document-arrow-down')
                                                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                                                        ->form($importContentForm($styleSheetOptions, $autocompleteOptionsStyle, 'style'))
                                                        ->action($importContentAction('css_content_web', $styleSheetOptions, 'style')),
                                                    Action::make('save_styles_web')
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
                                                ])
                                                    ->alignment(Alignment::Center),
                                                CustomMonacoEditor::make('css_content_web')
                                                    ->label(false)
                                                    ->language('css')
                                                    ->theme('vs-dark')
                                                    ->live()
                                                    ->autocomplete($autocompleteOptionsStyle)
                                                    ->reactive()
                                                    ->height('475px')
                                                    ->disabled(!$editable),
                                            ]),
                                        Tab::make('pdf_style_sheet')
                                            ->label('PDF')
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                Actions::make([
                                                    Action::make('import_css_content_pdf')
                                                        ->label('Insert CSS')
                                                        ->icon('heroicon-o-document-arrow-down')
                                                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                                                        ->form($importContentForm($styleSheetOptions, $autocompleteOptionsStyle, 'style'))
                                                        ->action($importContentAction('css_content_pdf', $styleSheetOptions, 'style')),
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
                                                ])
                                                    ->alignment(Alignment::Center),
                                                CustomMonacoEditor::make('css_content_pdf')
                                                    ->label(false)
                                                    ->language('css')
                                                    ->theme('vs-dark')
                                                    ->live()
                                                    ->autocomplete($autocompleteOptionsStyle)
                                                    ->reactive()
                                                    ->height('475px')
                                                    ->disabled(!$editable),
                                            ]),
                                    ])
                            ]),
                    ]),
                Tab::make('Scripts')
                    ->icon('heroicon-o-code-bracket-square')
                    ->schema([
                        Grid::make()
                            ->columns(6)
                            ->schema([
                                Hidden::make('selectedFormScriptName'),
                                AutocompleteBadgeList::make($autocompleteOptionsScript, 'script', 'Form Elements')
                                    ->columnSpan(1),
                                Tabs::make('form_script_type')
                                    ->contained(false)
                                    ->tabs([
                                        Tab::make('web_form_script')
                                            ->label('Web')
                                            ->icon('heroicon-o-globe-alt')
                                            ->schema([
                                                Actions::make([
                                                    Action::make('import_js_content_web')
                                                        ->label('Insert JavaScript')
                                                        ->icon('heroicon-o-document-arrow-down')
                                                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                                                        ->form($importContentForm($formScriptOptions, $autocompleteOptionsScript, 'script'))
                                                        ->action($importContentAction('js_content_web', $formScriptOptions, 'script')),
                                                    Action::make('save_scripts_web')
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
                                                ])
                                                    ->alignment(Alignment::Center),
                                                CustomMonacoEditor::make('js_content_web')
                                                    ->label(false)
                                                    ->language('javascript')
                                                    ->theme('vs-dark')
                                                    ->live()
                                                    ->autocomplete($autocompleteOptionsScript)
                                                    ->reactive()
                                                    ->height('475px')
                                                    ->disabled(!$editable),
                                            ]),
                                        Tab::make('pdf_form_script')
                                            ->label('PDF')
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                Actions::make([
                                                    Action::make('import_js_content_pdf')
                                                        ->label('Insert JavaScript')
                                                        ->icon('heroicon-o-document-arrow-down')
                                                        ->disabled(fn($livewire) => !$editable || ($livewire instanceof ViewRecord))
                                                        ->form($importContentForm($formScriptOptions, $autocompleteOptionsScript))
                                                        ->action($importContentAction('js_content_pdf', $formScriptOptions)),
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
                                                ])
                                                    ->alignment(Alignment::Center),

                                                CustomMonacoEditor::make('js_content_pdf')
                                                    ->label(false)
                                                    ->language('javascript')
                                                    ->theme('vs-dark')
                                                    ->reactive()
                                                    ->height('475px')
                                                    ->live()
                                                    ->autocomplete($autocompleteOptionsScript)
                                                    ->disabled(!$editable),
                                            ]),
                                    ])
                                    ->columnSpan(5)
                            ]),
                    ]),
            ]);
    }
}
