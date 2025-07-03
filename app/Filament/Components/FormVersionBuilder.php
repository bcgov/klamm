<?php

namespace App\Filament\Components;

use App\Filament\Plugins\MonacoEditor\CustomMonacoEditor;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use App\Events\FormVersionUpdateEvent;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Log;

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
            $type = $element->elementable_type ?? 'Element';
            $labelBase = $element->label ?? $element->name ?? 'Element';
            $label = $labelBase . ' (' . $type . ')';
            $uuid = $element->uuid;
            return [
                'label' => $label,
                // Insert selector and label/type as a comment for inline context
                'insertText' => $context == 'style' ? '[id="' . $uuid . '"] /* ' . addslashes($label) . ' */ ' : '"' . $uuid . '" /* ' . addslashes($label) . ' */ ',
                'detail' => "Selector: #$uuid\nLabel: $label\nName: {$element->name}\nType: $type",
                'documentation' => "**Selector:** `#$uuid`  \n**Label:** $label  \n**Name:** {$element->name}  \n**Type:** $type  \n**UUID:** $uuid",
            ];
        })->values()->toArray();
    }

    public static function schema()
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
        $styleSheetOptions = StyleSheet::with(['formVersion.form'])
            ->get()
            ->mapWithKeys(function ($sheet) {
                $form = $sheet->formVersion->form;
                $version = $sheet->formVersion;
                $label = "[{$form->form_id}] {$form->form_title} - v{$version->version_number} ({$sheet->type})";
                return [$sheet->id => $label];
            })->toArray();

        $formScriptOptions = FormScript::with(['formVersion.form'])
            ->get()
            ->mapWithKeys(function ($script) {
                $form = $script->formVersion->form;
                $version = $script->formVersion;
                $label = "[{$form->form_id}] {$form->form_title} - v{$version->version_number} ({$script->type})";
                return [$script->id => $label];
            })->toArray();

        return Tabs::make()
            ->columnSpanFull()
            ->tabs([
                Tab::make('Build')
                    ->icon('heroicon-o-cog')
                    ->schema([
                        \Filament\Forms\Components\View::make('components.form-element-tree')
                            ->viewData(function ($livewire) {
                                $record = $livewire->getRecord() ?? null;
                                return [
                                    'formVersionId' => $record?->id,
                                ];
                            })
                            ->columnSpanFull(),
                    ]),
                Tab::make('Style')
                    ->icon('heroicon-o-paint-brush')
                    ->schema([
                        Hidden::make('selectedStyleSheetName'),
                        Tabs::make('style_sheet_type')
                            ->contained(false)
                            ->tabs([
                                Tab::make('web_style_sheet')
                                    ->label('Web')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema([
                                        Actions::make([
                                            Action::make('import_css_content_web')
                                                ->label('Insert CSS')
                                                ->icon('heroicon-o-document-arrow-down')
                                                ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
                                                ->form([
                                                    Select::make('selectedStyleSheetId')
                                                        ->label('Select a Style Sheet')
                                                        ->options($styleSheetOptions)
                                                        ->required()
                                                        ->live()
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, callable $set) use ($styleSheetOptions) {
                                                            $displayName = $styleSheetOptions[$state];
                                                            $set('selectedStyleSheetName', $displayName);
                                                        }),
                                                ])
                                                ->action(function (array $data, callable $get, callable $set, $livewire) use ($styleSheetOptions) {
                                                    $content = StyleSheet::find($data['selectedStyleSheetId'])->getCssContent();
                                                    $existing = $get('css_content_web');
                                                    $selectedStyleSheet = $styleSheetOptions[$data['selectedStyleSheetId']];
                                                    $comment = "/* Imported from {$selectedStyleSheet} */" . "\n\n";
                                                    $appended = rtrim($existing) . "\n\n" . $comment . $content;

                                                    $set('css_content_web', $appended);
                                                }),
                                            Action::make('save_styles_web')
                                                ->label('Save Styles')
                                                ->icon('heroicon-o-check')
                                                ->color('success')
                                                ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
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
                                            ->height('400px')
                                            ->columnSpanFull()
                                            ->live()
                                            ->autocomplete($autocompleteOptionsStyle)
                                    ]),
                                Tab::make('pdf_style_sheet')
                                    ->label('PDF')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        Actions::make([
                                            Action::make('import_css_content_pdf')
                                                ->label('Insert CSS')
                                                ->icon('heroicon-o-document-arrow-down')
                                                ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
                                                ->form([
                                                    Select::make('selectedStyleSheetId')
                                                        ->label('Select a Style Sheet')
                                                        ->options($styleSheetOptions)
                                                        ->required()
                                                        ->live()
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, callable $set) use ($styleSheetOptions) {
                                                            $displayName = $styleSheetOptions[$state];
                                                            $set('selectedStyleSheetName', $displayName);
                                                        }),
                                                ])
                                                ->action(function (array $data, callable $get, callable $set, $livewire) use ($styleSheetOptions) {
                                                    $content = StyleSheet::find($data['selectedStyleSheetId'])->getCssContent();
                                                    $existing = $get('css_content_pdf');
                                                    $selectedStyleSheet = $styleSheetOptions[$data['selectedStyleSheetId']];
                                                    $comment = "/* Imported from {$selectedStyleSheet} */" . "\n\n";
                                                    $appended = rtrim($existing) . "\n\n" . $comment . $content;

                                                    $set('css_content_pdf', $appended);
                                                }),
                                            Action::make('save_styles_pdf')
                                                ->label('Save Styles')
                                                ->icon('heroicon-o-check')
                                                ->color('success')
                                                ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
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
                                            ->height('400px')
                                            ->columnSpanFull()
                                            ->live()
                                            ->autocomplete($autocompleteOptionsStyle),
                                    ]),
                            ])
                    ]),
                Tab::make('Scripts')
                    ->icon('heroicon-o-code-bracket-square')
                    ->schema([
                        Hidden::make('selectedFormScriptName'),
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
                                                ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
                                                ->form([
                                                    Select::make('selectedFormScriptId')
                                                        ->label('Select a Form Script')
                                                        ->options($formScriptOptions)
                                                        ->required()
                                                        ->live()
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, callable $set) use ($formScriptOptions) {
                                                            $displayName = $formScriptOptions[$state];
                                                            $set('selectedFormScriptName', $displayName);
                                                        }),
                                                ])
                                                ->action(function (array $data, callable $get, callable $set, $livewire) use ($formScriptOptions) {
                                                    $content = FormScript::find($data['selectedFormScriptId'])->getJsContent();
                                                    $existing = $get('js_content_web');
                                                    $selectedFormScript = $formScriptOptions[$data['selectedFormScriptId']];
                                                    $comment = "/* Imported from {$selectedFormScript} */" . "\n\n";
                                                    $appended = rtrim($existing) . "\n\n" . $comment . $content;

                                                    $set('js_content_web', $appended);
                                                }),
                                            Action::make('save_scripts_web')
                                                ->label('Save Scripts')
                                                ->icon('heroicon-o-check')
                                                ->color('success')
                                                ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
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
                                            ->height('400px')
                                            ->columnSpanFull()
                                            ->live()
                                            ->autocomplete($autocompleteOptionsScript),
                                    ]),
                                Tab::make('pdf_form_script')
                                    ->label('PDF')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        Actions::make([
                                            Action::make('import_js_content_pdf')
                                                ->label('Insert JavaScript')
                                                ->icon('heroicon-o-document-arrow-down')
                                                ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
                                                ->form([
                                                    Select::make('selectedFormScriptId')
                                                        ->label('Select a Form Script')
                                                        ->options($formScriptOptions)
                                                        ->required()
                                                        ->live()
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, callable $set) use ($formScriptOptions) {
                                                            $displayName = $formScriptOptions[$state];
                                                            $set('selectedFormScriptName', $displayName);
                                                        }),
                                                ])
                                                ->action(function (array $data, callable $get, callable $set, $livewire) use ($formScriptOptions) {
                                                    $content = FormScript::find($data['selectedFormScriptId'])->getJsContent();
                                                    $existing = $get('js_content_pdf');
                                                    $selectedFormScript = $formScriptOptions[$data['selectedFormScriptId']];
                                                    $comment = "/* Imported from {$selectedFormScript} */" . "\n\n";
                                                    $appended = rtrim($existing) . "\n\n" . $comment . $content;

                                                    $set('js_content_pdf', $appended);
                                                }),
                                            Action::make('save_scripts_pdf')
                                                ->label('Save Scripts')
                                                ->icon('heroicon-o-check')
                                                ->color('success')
                                                ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
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
                                            ->height('400px')
                                            ->columnSpanFull()
                                            ->live()
                                            ->autocomplete($autocompleteOptionsScript),
                                    ]),
                            ])
                    ]),
            ]);
    }
}
