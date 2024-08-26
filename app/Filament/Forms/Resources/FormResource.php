<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormResource\Pages;
use App\Models\Form;
use Filament\Forms;
use Filament\Forms\Form as FilamentForm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\ActionGroup;

class FormResource extends Resource
{
    protected static ?string $model = Form::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(FilamentForm $form): FilamentForm
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('form_id')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('form_title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('ministry_id')
                    ->relationship('ministry', 'name'),
                Forms\Components\Textarea::make('form_purpose'),
                Forms\Components\Textarea::make('notes'),
                Forms\Components\Select::make('fill_type_id')
                    ->relationship('fillType', 'name'),
                Forms\Components\Toggle::make('decommissioned'),
                Forms\Components\Select::make('form_frequency_id')
                    ->relationship('formFrequency', 'name'),
                Forms\Components\Select::make('form_reach_id')
                    ->relationship('formReach', 'name'),
                Forms\Components\TextInput::make('print_reason')
                    ->label('Print Reason')
                    ->nullable()
                    ->maxLength(255),
                Forms\Components\TextInput::make('retention_needs')
                    ->label('Retention Needs (years)')
                    ->numeric()
                    ->nullable(),
                Forms\Components\Toggle::make('icm_non_interactive')
                    ->label('ICM Non-Interactive')
                    ->nullable(),
                Forms\Components\Toggle::make('icm_generated')
                    ->label('ICM Generated')
                    ->nullable(),
                Forms\Components\TextInput::make('footer_fragment_path')
                    ->label('Footer Fragment Path')
                    ->nullable()
                    ->maxLength(255),
                Forms\Components\TextInput::make('dcv_material_number')
                    ->label('DCV Material Number')
                    ->nullable()
                    ->minLength(10)
                    ->maxLength(10),
                Forms\Components\Textarea::make('orbeon_functions')
                    ->label('Orbeon Functions')
                    ->nullable(),
                Forms\Components\Select::make('business_areas')
                    ->multiple()
                    ->preload()
                    ->relationship('businessAreas', 'name'),
                Forms\Components\Select::make('form_tags')
                    ->multiple()
                    ->preload()
                    ->relationship('formTags', 'name'),
                Forms\Components\Select::make('form_locations')
                    ->multiple()
                    ->preload()
                    ->relationship('formLocations', 'name'),
                Forms\Components\Select::make('form_repositories')
                    ->multiple()
                    ->preload()
                    ->relationship('formRepositories', 'name'),
                Forms\Components\Select::make('form_software_sources')
                    ->multiple()
                    ->preload()
                    ->relationship('formSoftwareSources', 'name'),
                Forms\Components\Select::make('user_types')
                    ->multiple()
                    ->preload()
                    ->relationship('userTypes', 'name'),
                Forms\Components\Select::make('related_forms')
                    ->multiple()
                    ->preload()
                    ->relationship('relatedForms', 'form_title'),
                Forms\Components\Repeater::make('links')
                    ->relationship('links')
                    ->schema([
                        Forms\Components\TextInput::make('link')
                            ->required(),
                    ])
                    ->columns(1)
                    ->defaultItems(0)
                    ->createItemButtonLabel('Add Link'),
                Forms\Components\Repeater::make('workbench_paths')
                    ->relationship('workbenchPaths')
                    ->schema([
                        Forms\Components\TextInput::make('workbench_path')
                            ->required(),
                    ])
                    ->columns(1)
                    ->defaultItems(0)
                    ->createItemButtonLabel('Add Workbench Path'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('form_title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('ministry.name'),
                Tables\Columns\TagsColumn::make('businessAreas.name'),
                Tables\Columns\TextColumn::make('form_purpose')->searchable(['notes', 'form_purpose']),
                Tables\Columns\TagsColumn::make('formLocations.name'),
                Tables\Columns\TagsColumn::make('formSoftwareSources.name'),
                Tables\Columns\BooleanColumn::make('decommissioned'),
            ])
            ->filters([
                Tables\Filters\Filter::make('decommissioned')
                    ->form([
                        Forms\Components\Checkbox::make('decommissioned')
                            ->label('Decommissioned')
                            ->default(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['decommissioned'])) {
                            return $query->where('decommissioned', $data['decommissioned']);
                        }
                        return $query;
                    }),
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
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
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
