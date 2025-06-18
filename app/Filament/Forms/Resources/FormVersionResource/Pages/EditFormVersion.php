<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Models\FormElement;
use App\Models\FormStylesheet;
use App\Models\FormScript;
use App\Models\ContainerFormElement;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Gate;
use SolutionForest\FilamentTree\Actions\Action;
use SolutionForest\FilamentTree\Actions\ActionGroup;
use SolutionForest\FilamentTree\Actions\DeleteAction;
use SolutionForest\FilamentTree\Actions\EditAction;
use SolutionForest\FilamentTree\Actions\ViewAction;
use SolutionForest\FilamentTree\Widgets\Tree;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function canEdit($record): bool
    {
        return Gate::allows('form-developer') && in_array($record->status, ['draft', 'testing']);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('FormVersionTabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\Select::make('form_id')
                                    ->relationship('form', 'form_id_title')
                                    ->required()
                                    ->reactive()
                                    ->preload()
                                    ->searchable()
                                    ->default(request()->query('form_id')),
                                Forms\Components\Select::make('status')
                                    ->options(function () {
                                        return \App\Models\FormVersion::getStatusOptions();
                                    })
                                    ->default('draft')
                                    ->disabled()
                                    ->required(),
                                Forms\Components\Section::make('Form Properties')
                                    ->collapsible()
                                    ->collapsed()
                                    ->columns(2)
                                    ->compact()
                                    ->schema([
                                        Forms\Components\Select::make('form_developer_id')
                                            ->label('Form Developer')
                                            ->relationship(
                                                'formDeveloper',
                                                'name',
                                                fn($query) => $query->whereHas('roles', fn($q) => $q->where('name', 'form-developer'))
                                            )
                                            ->default(\Illuminate\Support\Facades\Auth::id())
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(1),
                                        Forms\Components\TextInput::make('footer')
                                            ->columnSpan(2),
                                        Forms\Components\Textarea::make('comments')
                                            ->columnSpanFull()
                                            ->maxLength(500),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Build')
                            ->schema([
                                Forms\Components\Section::make('Form Structure')
                                    ->description('Arrange form elements in a hierarchical structure. Only containers can contain other elements.')
                                    ->schema([
                                        Forms\Components\ViewField::make('form_tree')
                                            ->view('filament.form-elements-tree')
                                            ->viewData([
                                                'formVersionId' => $this->record->id ?? null,
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Styles')
                            ->schema([
                                Forms\Components\Section::make('CSS Styles')
                                    ->description('Add custom CSS styles for this form version.')
                                    ->schema([
                                        Forms\Components\Textarea::make('web_styles')
                                            ->label('Web Styles (CSS)')
                                            ->placeholder('/* Add your CSS styles here */')
                                            ->rows(15)
                                            ->columnSpanFull()
                                            ->afterStateHydrated(function (Forms\Components\Textarea $component, $state, $record) {
                                                if ($record) {
                                                    $stylesheet = $record->stylesheets()->where('type', 'web')->first();
                                                    $component->state($stylesheet?->content ?? '');
                                                }
                                            })
                                            ->dehydrated(false),
                                        Forms\Components\Textarea::make('pdf_styles')
                                            ->label('PDF Styles (CSS)')
                                            ->placeholder('/* Add your PDF-specific CSS styles here */')
                                            ->rows(15)
                                            ->columnSpanFull()
                                            ->afterStateHydrated(function (Forms\Components\Textarea $component, $state, $record) {
                                                if ($record) {
                                                    $stylesheet = $record->stylesheets()->where('type', 'pdf')->first();
                                                    $component->state($stylesheet?->content ?? '');
                                                }
                                            })
                                            ->dehydrated(false),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Scripts')
                            ->schema([
                                Forms\Components\Section::make('JavaScript')
                                    ->description('Add custom JavaScript for this form version.')
                                    ->schema([
                                        Forms\Components\Textarea::make('web_scripts')
                                            ->label('Web Scripts (JavaScript)')
                                            ->placeholder('// Add your JavaScript code here')
                                            ->rows(15)
                                            ->columnSpanFull()
                                            ->afterStateHydrated(function (Forms\Components\Textarea $component, $state, $record) {
                                                if ($record) {
                                                    $script = $record->scripts()->where('type', 'web')->first();
                                                    $component->state($script?->content ?? '');
                                                }
                                            })
                                            ->dehydrated(false),
                                        Forms\Components\Textarea::make('pdf_scripts')
                                            ->label('PDF Scripts (JavaScript)')
                                            ->placeholder('// Add your PDF-specific JavaScript code here')
                                            ->rows(15)
                                            ->columnSpanFull()
                                            ->afterStateHydrated(function (Forms\Components\Textarea $component, $state, $record) {
                                                if ($record) {
                                                    $script = $record->scripts()->where('type', 'pdf')->first();
                                                    $component->state($script?->content ?? '');
                                                }
                                            })
                                            ->dehydrated(false),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle styles and scripts separately since they're not direct attributes
        $this->handleStylesAndScripts($data);

        // Remove the custom fields from the main data array
        unset($data['web_styles'], $data['pdf_styles'], $data['web_scripts'], $data['pdf_scripts']);

        return $data;
    }

    protected function handleStylesAndScripts(array $data): void
    {
        // Handle Web Styles
        if (isset($data['web_styles'])) {
            $this->record->stylesheets()->updateOrCreate(
                ['type' => 'web'],
                [
                    'name' => 'Web Styles',
                    'content' => $data['web_styles'],
                    'description' => 'Web CSS styles for form version',
                ]
            );
        }

        // Handle PDF Styles
        if (isset($data['pdf_styles'])) {
            $this->record->stylesheets()->updateOrCreate(
                ['type' => 'pdf'],
                [
                    'name' => 'PDF Styles',
                    'content' => $data['pdf_styles'],
                    'description' => 'PDF CSS styles for form version',
                ]
            );
        }

        // Handle Web Scripts
        if (isset($data['web_scripts'])) {
            $this->record->scripts()->updateOrCreate(
                ['type' => 'web'],
                [
                    'name' => 'Web Scripts',
                    'content' => $data['web_scripts'],
                    'description' => 'Web JavaScript for form version',
                ]
            );
        }

        // Handle PDF Scripts
        if (isset($data['pdf_scripts'])) {
            $this->record->scripts()->updateOrCreate(
                ['type' => 'pdf'],
                [
                    'name' => 'PDF Scripts',
                    'content' => $data['pdf_scripts'],
                    'description' => 'PDF JavaScript for form version',
                ]
            );
        }
    }
}
