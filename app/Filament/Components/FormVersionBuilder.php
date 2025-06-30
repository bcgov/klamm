<?php

namespace App\Filament\Components;

use WeStacks\FilamentMonacoEditor\MonacoEditor;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Alignment;

class FormVersionBuilder
{
    public static function schema()
    {
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
                        Section::make('Form Builder')
                            ->schema([
                                //
                            ]),
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
                                        ])
                                            ->alignment(Alignment::Center),
                                        MonacoEditor::make('css_content_web')
                                            ->label(false)
                                            ->language('css')
                                            ->theme('vs-dark')
                                            ->height('400px')
                                            ->columnSpanFull()
                                            ->live(),
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
                                        ])
                                            ->alignment(Alignment::Center),
                                        MonacoEditor::make('css_content_pdf')
                                            ->label(false)
                                            ->language('css')
                                            ->theme('vs-dark')
                                            ->height('400px')
                                            ->columnSpanFull()
                                            ->live(),
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
                                        ])
                                            ->alignment(Alignment::Center),
                                        MonacoEditor::make('js_content_web')
                                            ->label(false)
                                            ->language('javascript')
                                            ->theme('vs-dark')
                                            ->height('400px')
                                            ->columnSpanFull()
                                            ->live(),
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
                                        ])
                                            ->alignment(Alignment::Center),
                                        MonacoEditor::make('js_content_pdf')
                                            ->label(false)
                                            ->language('javascript')
                                            ->theme('vs-dark')
                                            ->height('400px')
                                            ->columnSpanFull()
                                            ->live(),
                                    ]),
                            ])
                    ]),
            ]);
    }
}
