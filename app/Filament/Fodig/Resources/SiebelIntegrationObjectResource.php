<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelIntegrationObjectResource\Pages;
use App\Filament\Fodig\Resources\SiebelIntegrationObjectResource\RelationManagers;
use App\Models\SiebelIntegrationObject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelIntegrationObjectResource extends Resource
{
    protected static ?string $model = SiebelIntegrationObject::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Toggle::make('changed')
                    ->required(),
                Forms\Components\TextInput::make('repository_name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\TextInput::make('title')
                    ->maxLength(250),
                Forms\Components\TextInput::make('title_string_reference')
                    ->maxLength(250),
                Forms\Components\TextInput::make('title_string_override')
                    ->maxLength(250),
                Forms\Components\TextInput::make('search_specification')
                    ->maxLength(400),
                Forms\Components\TextInput::make('associate_applet')
                    ->maxLength(250),
                Forms\Components\TextInput::make('type')
                    ->maxLength(25),
                Forms\Components\Toggle::make('no_delete'),
                Forms\Components\Toggle::make('no_insert'),
                Forms\Components\Toggle::make('no_merge'),
                Forms\Components\Toggle::make('no_update'),
                Forms\Components\TextInput::make('html_number_of_rows')
                    ->numeric(),
                Forms\Components\Toggle::make('scripted'),
                Forms\Components\Toggle::make('inactive'),
                Forms\Components\Textarea::make('comments'),
                Forms\Components\Textarea::make('auto_query_mode'),
                Forms\Components\TextInput::make('background_bitmap_style')
                    ->maxLength(50),
                Forms\Components\TextInput::make('html_popup_dimension')
                    ->maxLength(50),
                Forms\Components\TextInput::make('height')
                    ->numeric(),
                Forms\Components\TextInput::make('help_identifier')
                    ->maxLength(150),
                Forms\Components\TextInput::make('insert_position')
                    ->maxLength(50),
                Forms\Components\TextInput::make('mail_address_field')
                    ->maxLength(50),
                Forms\Components\TextInput::make('mail_template')
                    ->maxLength(50),
                Forms\Components\TextInput::make('popup_dimension')
                    ->maxLength(50),
                Forms\Components\TextInput::make('upgrade_ancestor')
                    ->maxLength(50),
                Forms\Components\TextInput::make('width')
                    ->numeric(),
                Forms\Components\TextInput::make('upgrade_behavior')
                    ->maxLength(25),
                Forms\Components\TextInput::make('icl_upgrade_path')
                    ->numeric(),
                Forms\Components\TextInput::make('task')
                    ->maxLength(50),
                Forms\Components\TextInput::make('default_applet_method')
                    ->maxLength(50),
                Forms\Components\TextInput::make('default_double_click_method')
                    ->maxLength(50),
                Forms\Components\Toggle::make('disable_dataloss_warning'),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('business_component_id')
                    ->relationship('businessComponent', 'name')
                    ->nullable(),
                Forms\Components\Select::make('class_id')
                    ->relationship('class', 'name')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('changed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('repository_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title_string_reference')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title_string_override')
                    ->sortable(),
                Tables\Columns\TextColumn::make('search_specification')
                    ->sortable(),
                Tables\Columns\TextColumn::make('associate_applet')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_delete')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_insert')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_merge')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_update')
                    ->sortable(),
                Tables\Columns\TextColumn::make('html_number_of_rows')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('scripted')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('auto_query_mode')
                    ->sortable(),
                Tables\Columns\TextColumn::make('background_bitmap_style')
                    ->sortable(),
                Tables\Columns\TextColumn::make('html_popup_dimension')
                    ->sortable(),
                Tables\Columns\TextColumn::make('height')
                    ->sortable(),
                Tables\Columns\TextColumn::make('help_identifier')
                    ->sortable(),
                Tables\Columns\TextColumn::make('insert_position')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mail_address_field')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mail_template')
                    ->sortable(),
                Tables\Columns\TextColumn::make('popup_dimension')
                    ->sortable(),
                Tables\Columns\TextColumn::make('upgrade_ancestor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('width')
                    ->sortable(),
                Tables\Columns\TextColumn::make('upgrade_behavior')
                    ->sortable(),
                Tables\Columns\TextColumn::make('icl_upgrade_path')
                    ->sortable(),
                Tables\Columns\TextColumn::make('task')
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_applet_method')
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_double_click_method')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('disable_dataloss_warning')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('object_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('businessComponent.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('class.name')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ])
            ->paginated([
                10, 25, 50, 100,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiebelIntegrationObjects::route('/'),
            'create' => Pages\CreateSiebelIntegrationObject::route('/create'),
            'view' => Pages\ViewSiebelIntegrationObject::route('/{record}'),
            'edit' => Pages\EditSiebelIntegrationObject::route('/{record}/edit'),
        ];
    }
}
