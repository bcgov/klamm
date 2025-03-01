<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelAppletResource\Pages;
use App\Filament\Fodig\Resources\SiebelAppletResource\RelationManagers;
use App\Models\SiebelApplet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelAppletResource extends Resource
{
    protected static ?string $model = SiebelApplet::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Siebel Tables';

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
                Forms\Components\Textarea::make('comments')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('auto_query_mode')
                    ->columnSpanFull(),
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
                    ->relationship('project', 'name'),
                Forms\Components\Select::make('business_component_id')
                    ->relationship('businessComponent', 'name'),
                Forms\Components\Select::make('class_id')
                    ->relationship('class', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\IconColumn::make('changed')
                    ->boolean(),
                Tables\Columns\TextColumn::make('repository_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title_string_reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title_string_override')
                    ->searchable(),
                Tables\Columns\TextColumn::make('search_specification')
                    ->searchable(),
                Tables\Columns\TextColumn::make('associate_applet')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\IconColumn::make('no_delete')
                    ->boolean(),
                Tables\Columns\IconColumn::make('no_insert')
                    ->boolean(),
                Tables\Columns\IconColumn::make('no_merge')
                    ->boolean(),
                Tables\Columns\IconColumn::make('no_update')
                    ->boolean(),
                Tables\Columns\TextColumn::make('html_number_of_rows')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('scripted')
                    ->boolean(),
                Tables\Columns\IconColumn::make('inactive')
                    ->boolean(),
                Tables\Columns\TextColumn::make('background_bitmap_style')
                    ->searchable(),
                Tables\Columns\TextColumn::make('html_popup_dimension')
                    ->searchable(),
                Tables\Columns\TextColumn::make('height')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('help_identifier')
                    ->searchable(),
                Tables\Columns\TextColumn::make('insert_position')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mail_address_field')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mail_template')
                    ->searchable(),
                Tables\Columns\TextColumn::make('popup_dimension')
                    ->searchable(),
                Tables\Columns\TextColumn::make('upgrade_ancestor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('width')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('upgrade_behavior')
                    ->searchable(),
                Tables\Columns\TextColumn::make('icl_upgrade_path')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('task')
                    ->searchable(),
                Tables\Columns\TextColumn::make('default_applet_method')
                    ->searchable(),
                Tables\Columns\TextColumn::make('default_double_click_method')
                    ->searchable(),
                Tables\Columns\IconColumn::make('disable_dataloss_warning')
                    ->boolean(),
                Tables\Columns\IconColumn::make('object_locked')
                    ->boolean(),
                Tables\Columns\TextColumn::make('project.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('businessComponent.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('class.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Project')
                    ->multiple()
                    ->searchable()
                    ->attribute('project.name')
                    ->relationship('project', 'name'),
                Tables\Filters\SelectFilter::make('business_component_id')
                    ->label('Business Component')
                    ->multiple()
                    ->searchable()
                    ->attribute('businessComponent.name')
                    ->relationship('businessComponent', 'name'),
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Class')
                    ->multiple()
                    ->searchable()
                    ->attribute('class.name')
                    ->relationship('class', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ])
            ->paginated([
                10,
                25,
                50,
                100,
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
            'index' => Pages\ListSiebelApplets::route('/'),
            'create' => Pages\CreateSiebelApplet::route('/create'),
            'view' => Pages\ViewSiebelApplet::route('/{record}'),
            'edit' => Pages\EditSiebelApplet::route('/{record}/edit'),
        ];
    }
}
