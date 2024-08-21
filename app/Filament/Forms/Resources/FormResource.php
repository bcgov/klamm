<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormResource\Pages;
use App\Filament\Forms\Resources\FormResource\RelationManagers;
use App\Models\Form;
use Filament\Forms;
use Filament\Forms\Form as FilamentForm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Enums\ActionsPosition;

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
                Forms\Components\Textarea::make('short_description'),
                Forms\Components\Textarea::make('long_description'),
                Forms\Components\Select::make('fill_type_id')
                    ->relationship('fillType', 'name'),
                Forms\Components\Toggle::make('decommissioned'),
                Forms\Components\Select::make('form_frequency_id')
                    ->relationship('formFrequency', 'name'),
                Forms\Components\Select::make('form_reach_id')
                    ->relationship('formReach', 'name'),
                Forms\Components\Select::make('business_areas')
                    ->multiple()
                    ->relationship('businessAreas', 'name'),
                Forms\Components\Select::make('form_tags')
                    ->multiple()
                    ->relationship('formTags', 'name'),
                Forms\Components\Select::make('form_locations')
                    ->multiple()
                    ->relationship('formLocations', 'name'),
                Forms\Components\Select::make('form_repositories')
                    ->multiple()
                    ->relationship('formRepositories', 'name'),
                Forms\Components\Select::make('form_software_sources')
                    ->multiple()
                    ->relationship('formSoftwareSources', 'name'),
                Forms\Components\Select::make('user_types')
                    ->multiple()
                    ->relationship('userTypes', 'name'),
                Forms\Components\Select::make('related_forms')
                    ->multiple()
                    ->relationship('relatedForms', 'form_title')
                    ->preload(),
                Forms\Components\Repeater::make('links')
                    ->relationship('links')
                    ->schema([
                        Forms\Components\TextInput::make('link')
                            ->required(),
                    ])
                    ->columns(1)
                    ->createItemButtonLabel('Add Link'),
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
                Tables\Columns\TagsColumn::make('formTags.name'),
                Tables\Columns\TextColumn::make('fillType.name'),
                Tables\Columns\BooleanColumn::make('decommissioned'),
                Tables\Columns\TextColumn::make('formFrequency.name'),
                Tables\Columns\TextColumn::make('formReach.name'),
                Tables\Columns\TagsColumn::make('formLocations.name'),
                Tables\Columns\TagsColumn::make('formRepositories.name'),
                Tables\Columns\TagsColumn::make('formSoftwareSources.name'),
                Tables\Columns\TagsColumn::make('userTypes.name'),
                Tables\Columns\TagsColumn::make('relatedForms.form_id'),
                Tables\Columns\TextColumn::make('short_description')->searchable(),
                Tables\Columns\TextColumn::make('long_description')->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ministry_id')
                    ->relationship('ministry', 'name')
                    ->label('Ministry'),
                Tables\Filters\SelectFilter::make('business_areas')
                    ->relationship('businessAreas', 'name')
                    ->label('Business Area'),
                Tables\Filters\SelectFilter::make('fill_type_id')
                    ->relationship('fillType', 'name')
                    ->label('Fill Type'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
