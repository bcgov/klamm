<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormResource\Pages;
use App\Models\Form;
use Filament\Forms;
use Filament\Forms\Form as FilamentForm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\ActionGroup;
use App\Filament\Exports\FormExporter;
use Filament\Tables\Actions\ExportAction;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid as InfolistGrid;
use Illuminate\Support\Facades\Gate;
use App\Models\Ministry;
use App\Models\FormReach;
use App\Models\FormFrequency;
use App\Models\FormRepository;
use App\Models\UserType;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;


class FormResource extends Resource
{
    protected static ?string $model = Form::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static function formatLabel(string $text): string
    {
        return '<span class="block text-lg font-bold">' . $text . '</span>';
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->schema([
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('form_id')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Form ID'))),
                                TextEntry::make('form_title')
                                    ->columnSpanFull()
                                    ->label(new HtmlString(self::formatLabel('Form Title'))),
                                TextEntry::make('decommissioned')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Inactive' : 'Active')
                                    ->badge()
                                    ->columnSpanFull()
                                    ->color(fn(bool $state): string => $state ? 'danger' : 'success')
                                    ->label(new HtmlString(self::formatLabel('Status'))),
                                TextEntry::make('ministry.name')
                                    ->columnSpanFull()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Ministry'))),
                                TextEntry::make('businessAreas.name')
                                    ->columnSpanFull()
                                    ->badge()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->listWithLineBreaks()
                                    ->label(new HtmlString(self::formatLabel('Business Areas or Program'))),
                                TextEntry::make('form_purpose')
                                    ->columnSpanFull()
                                    ->markdown()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Purpose'))),
                                RepeatableEntry::make('links')
                                    ->columnSpanFull()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Access Links')))
                                    ->schema([
                                        TextEntry::make('link')
                                            ->html()
                                            ->formatStateUsing(fn($state): string => "<a href='{$state}' target='_blank' rel='noopener noreferrer'>{$state}</a>")
                                            ->label('')
                                            ->columnSpanFull(),
                                    ]),
                                TextEntry::make('notes')
                                    ->columnSpanFull()
                                    ->markdown()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Notes'))),

                            ]),
                    ]),

                Section::make()
                    ->hidden(
                        fn($record): bool =>
                        empty($record->formFrequency?->name) &&
                            empty($record->userTypes?->pluck('name')->filter()->toArray()) &&
                            empty($record->formReach?->name)
                    )
                    ->schema([
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('formFrequency.name')
                                    ->badge()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Usage Frequency'))),
                                TextEntry::make('userTypes.name')
                                    ->listWithLineBreaks()
                                    ->badge()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Audience'))),

                                TextEntry::make('formReach.name')
                                    ->badge()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Audience Size'))),

                            ]),
                    ]),

                Section::make()
                    ->schema([
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('icm_generated')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->columnSpanFull()
                                    ->color(fn($state) => $state ? 'success' : 'danger')
                                    ->label(new HtmlString(self::formatLabel('ICM Generated'))),
                                TextEntry::make('formSoftwareSources.name')
                                    ->listWithLineBreaks()
                                    ->badge()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Software Sources'))),

                                TextEntry::make('formLocations.name')
                                    ->listWithLineBreaks()
                                    ->badge()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Published Locations'))),
                                TextEntry::make('formTags.name')
                                    ->listWithLineBreaks()
                                    ->badge()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Tags'))),
                                TextEntry::make('dcv_material_number')
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('DCV Material Number'))),
                            ]),
                    ]),

                Section::make('Development')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('formRepositories.name')
                                    ->listWithLineBreaks()
                                    ->badge()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Repositories'))),
                                TextEntry::make('orbeon_functions')
                                    ->markdown()
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Orbeon Functions'))),
                                TextEntry::make('footer_fragment_path')
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Footer Fragment Path'))),
                                RepeatableEntry::make('workbenchPaths')
                                    ->hidden(fn($state): bool => empty($state))
                                    ->label(new HtmlString(self::formatLabel('Workbench Paths')))
                                    ->schema([
                                        TextEntry::make('workbench_path')
                                            ->label(''),
                                    ]),
                                TextEntry::make('css_file_status')
                                    ->label(new HtmlString(self::formatLabel('CSS File')))
                                    ->badge()
                                    ->formatStateUsing(fn($record): string => $record->hasCssFile() ? 'Exists' : 'Not Found')
                                    ->color(fn($record): string => $record->hasCssFile() ? 'success' : 'gray'),

                            ]),
                    ])
                    ->hidden(fn() => !Gate::allows('admin') && !Gate::allows('form-developer')),

                Section::make('Additional Details')
                    ->schema([
                        InfolistGrid::make(1)
                            ->schema([
                                TextEntry::make('relatedForms.form_id')
                                    ->listWithLineBreaks()
                                    ->badge()
                                    ->label(new HtmlString(self::formatLabel('Related Forms'))),
                                IconEntry::make('icm_non_interactive')
                                    ->boolean()
                                    ->label(new HtmlString(self::formatLabel('ICM Non-Interactive'))),
                                TextEntry::make('fillType.name')
                                    ->badge()
                                    ->label(new HtmlString(self::formatLabel('Fill Type'))),
                                TextEntry::make('print_reason')
                                    ->label(new HtmlString(self::formatLabel('Print Reason'))),
                                TextEntry::make('retention_needs')
                                    ->label(new HtmlString(self::formatLabel('Retention Needs (years)'))),
                            ]),
                    ])
                    ->hidden()
            ]);
    }

    public static function form(FilamentForm $form): FilamentForm
    {
        $ministryOptions = Ministry::pluck('name', 'id')->toArray();
        $formReachOptions = FormReach::pluck('name', 'id')->toArray();
        $formFrequencyOptions = FormFrequency::pluck('name', 'id')->toArray();
        $formRepositoryOptions = FormRepository::pluck('name', 'id')->toArray();
        $userTypeOptions = UserType::pluck('name', 'id')->toArray();

        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('form_id')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->label('Form ID'),
                        Forms\Components\TextInput::make('form_title')
                            ->required()
                            ->maxLength(255)
                            ->label('Form Title'),
                        Forms\Components\Radio::make('decommissioned')
                            ->label('Status')
                            ->options([
                                false => 'Active',
                                true => 'Inactive',
                            ])
                            ->default(false),
                        Forms\Components\Radio::make('ministry_id')
                            ->label('Ministry')
                            ->options($ministryOptions)
                            ->default(1),
                        Forms\Components\Select::make('business_areas')
                            ->multiple()
                            ->label('Business Areas or Program')
                            ->preload()
                            ->relationship('businessAreas', 'name'),
                        Forms\Components\Textarea::make('form_purpose')
                            ->label('Purpose'),
                        Forms\Components\Repeater::make('links')
                            ->defaultItems(1)
                            ->simple(
                                Forms\Components\TextInput::make('link')
                                    ->required(),
                            )
                            ->relationship('links')
                            ->addActionLabel('Add Link')
                            ->label('Access Links'),
                        Forms\Components\Textarea::make('notes'),
                    ]),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Radio::make('form_frequency_id')
                            ->label('Usage Frequency')
                            ->options($formFrequencyOptions),
                        Forms\Components\Radio::make('user_types')
                            ->label('Audience')
                            ->options($userTypeOptions),
                        Forms\Components\Radio::make('form_reach_id')
                            ->label('Audience Size')
                            ->options($formReachOptions),
                    ]),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Radio::make('icm_generated')
                            ->label('Is form ICM generated?')
                            ->options([
                                false => 'No',
                                true => 'Yes',
                            ])
                            ->default(false),
                        Forms\Components\Select::make('form_software_sources')
                            ->multiple()
                            ->preload()
                            ->relationship('formSoftwareSources', 'name')
                            ->label('Software Sources'),
                        Forms\Components\Select::make('form_locations')
                            ->multiple()
                            ->preload()
                            ->relationship('formLocations', 'name')
                            ->label('Published Locations'),
                        Forms\Components\Select::make('form_tags')
                            ->multiple()
                            ->preload()
                            ->relationship('formTags', 'name')
                            ->label('Tags'),
                        Forms\Components\TextInput::make('dcv_material_number')
                            ->label('DCV Material Number')
                            ->nullable()
                            ->minLength(10)
                            ->maxLength(10),

                    ]),

                Forms\Components\Section::make('Development')
                    ->collapsed()
                    ->schema([
                        Forms\Components\CheckboxList::make('form_repositories')
                            ->label('Repositories')
                            ->options($formRepositoryOptions)
                            ->columns(2)
                            ->relationship('formRepositories', 'name'),
                        Forms\Components\Textarea::make('orbeon_functions')
                            ->label('Orbeon Functions')
                            ->nullable(),
                        Forms\Components\TextInput::make('footer_fragment_path')
                            ->label('Footer Fragment Path')
                            ->nullable()
                            ->maxLength(255),
                        Forms\Components\Repeater::make('workbench_paths')
                            ->defaultItems(1)
                            ->simple(
                                Forms\Components\TextInput::make('workbench_path')
                                    ->required(),
                            )
                            ->relationship('workbenchPaths')
                            ->addActionLabel('Add Workbench Path')
                            ->label('Workbench Paths'),
                        Forms\Components\Textarea::make('css_content')
                            ->label('Custom CSS')
                            ->placeholder('Enter custom CSS for this form...')
                            ->rows(10)
                            ->columnSpanFull()
                            ->helperText('CSS will be saved as {id}.css in the stylesheets directory')
                            ->dehydrated(true)
                            ->afterStateUpdated(function ($state, $record) {
                                if ($record) {
                                    Log::info('CSS content updated for form ID: ' . $record->id);
                                }
                            }),

                    ]),

                Forms\Components\Section::make('Additional Details')
                    ->hidden()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Toggle::make('icm_non_interactive')
                            ->label('ICM Non-Interactive')
                            ->nullable(),
                        Forms\Components\Select::make('fill_type_id')
                            ->relationship('fillType', 'name')
                            ->label('Fill Type'),
                        Forms\Components\TextInput::make('print_reason')
                            ->label('Print Reason')
                            ->nullable()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('retention_needs')
                            ->label('Retention Needs (years)')
                            ->numeric()
                            ->nullable(),
                        Forms\Components\Select::make('related_forms')
                            ->relationship('relatedForms', 'form_id')
                            ->searchable()
                            ->multiple()
                            ->preload()
                            ->label('Related Forms')
                            ->getSearchResultsUsing(function (string $searchQuery) {
                                return Form::query()
                                    ->where('form_id', 'like', "%{$searchQuery}%")
                                    ->orWhere('form_title', 'like', "%{$searchQuery}%")
                                    ->limit(50)
                                    ->pluck('form_id', 'id');
                            })
                            ->getOptionLabelsUsing(function ($values) {
                                return Form::whereIn('id', $values)->pluck('form_id', 'id');
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form_id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('form_title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('decommissioned')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(bool $state): string => $state ? 'Inactive' : 'Active')
                    ->color(fn(bool $state): string => $state ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('ministry.short_name')
                    ->searchable()
                    ->sortable()
                    ->label('Ministry'),
                Tables\Columns\TextColumn::make('businessAreas.name')
                    ->badge()
                    ->label('Business Areas'),
                Tables\Columns\TextColumn::make('form_purpose')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function ($record): ?string {
                        $form_purpose = optional($record)->form_purpose ?? '';
                        return Str::length($form_purpose) > 30
                            ? $form_purpose
                            : null;
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(true)
                    ->limit(30)
                    ->tooltip(function ($record): ?string {
                        $notes = optional($record)->notes ?? '';
                        return Str::length($notes) > 30
                            ? $notes
                            : null;
                    }),
                Tables\Columns\TextColumn::make('formFrequency.name')
                    ->label('Usage Frequency'),
                Tables\Columns\TextColumn::make('formReach.name')
                    ->label('Audience Size'),
                Tables\Columns\TextColumn::make('icm_generated')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                    ->color(fn($state) => $state ? 'success' : 'danger')
                    ->label('ICM Generated'),
                Tables\Columns\TextColumn::make('formSoftwareSources.name')
                    ->badge()
                    ->label('Software Sources'),
                Tables\Columns\TextColumn::make('formLocations.name')
                    ->badge()
                    ->label('Published Locations'),
                Tables\Columns\TextColumn::make('formTags.name')
                    ->badge()
                    ->label('Tags'),
                Tables\Columns\TextColumn::make('dcv_material_number')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(true)
                    ->label('DCV Material Number'),

            ])
            ->searchable()
            ->persistSearchInSession()
            ->searchDebounce(500)
            ->filters([
                Tables\Filters\SelectFilter::make('decommissioned')
                    ->label('Status')
                    ->default(false)
                    ->options([
                        false => 'Active',
                        true => 'Inactive',
                    ]),
                Tables\Filters\SelectFilter::make('ministry_id')
                    ->multiple()
                    ->relationship('ministry', 'name')
                    ->preload()
                    ->label('Ministry'),
                Tables\Filters\SelectFilter::make('business_areas')
                    ->multiple()
                    ->relationship('businessAreas', 'name')
                    ->preload()
                    ->label('Business Area'),
                Tables\Filters\SelectFilter::make('icm_generated')
                    ->label('ICM Generated')
                    ->options([
                        false => 'No',
                        true => 'Yes',
                    ]),
                Tables\Filters\SelectFilter::make('formFrequency.name')
                    ->multiple()
                    ->relationship('formFrequency', 'name')
                    ->preload()
                    ->label('Usage Frequency'),
                Tables\Filters\SelectFilter::make('formReach.name')
                    ->multiple()
                    ->relationship('formReach', 'name')
                    ->preload()
                    ->label('Audience Size'),
                Tables\Filters\SelectFilter::make('formTags.name')
                    ->multiple()
                    ->relationship('formTags', 'name')
                    ->preload()
                    ->label('Tags'),
                Tables\Filters\SelectFilter::make('formLocations')
                    ->multiple()
                    ->relationship('formLocations', 'name')
                    ->preload()
                    ->label('Published Locations'),
                Tables\Filters\SelectFilter::make('formSoftwareSources')
                    ->multiple()
                    ->relationship('formSoftwareSources', 'name')
                    ->preload()
                    ->label('Software Sources'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(FormExporter::class)
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Tables\Actions\Action::make('Version Control')
                        ->icon('heroicon-o-inbox-stack')
                        ->url(fn(Form $record) => route('filament.forms.resources.form-versions.index', ['form_id' => $record->id])),
                    DeleteAction::make(),
                ])->icon('heroicon-m-ellipsis-vertical')
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'view' => Pages\ViewForm::route('/{record}'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
        ];
    }
}
