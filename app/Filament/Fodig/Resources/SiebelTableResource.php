<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelTableResource\Pages;
use App\Filament\Fodig\Resources\SiebelTableResource\RelationManagers;
use App\Models\SiebelTable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelTableResource extends Resource
{
    protected static ?string $model = SiebelTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Siebel Tables';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_locked_by_name')
                    ->maxLength(50),
                Forms\Components\DateTimePicker::make('object_locked_date'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Toggle::make('changed')
                    ->required(),
                Forms\Components\TextInput::make('repository_name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\TextInput::make('user_name')
                    ->maxLength(200),
                Forms\Components\TextInput::make('alias')
                    ->maxLength(200),
                Forms\Components\TextInput::make('type')
                    ->maxLength(50),
                Forms\Components\Toggle::make('file'),
                Forms\Components\TextInput::make('abbreviation_1')
                    ->maxLength(50),
                Forms\Components\TextInput::make('abbreviation_2')
                    ->maxLength(50),
                Forms\Components\TextInput::make('abbreviation_3')
                    ->maxLength(50),
                Forms\Components\Toggle::make('append_data'),
                Forms\Components\TextInput::make('dflt_mapping_col_name_prefix')
                    ->maxLength(25),
                Forms\Components\Textarea::make('seed_filter'),
                Forms\Components\Textarea::make('seed_locale_filter'),
                Forms\Components\TextInput::make('seed_usage')
                    ->maxLength(30),
                Forms\Components\TextInput::make('group')
                    ->maxLength(25),
                Forms\Components\TextInput::make('owner_organization_specifier')
                    ->maxLength(30),
                Forms\Components\TextInput::make('status')
                    ->maxLength(25),
                Forms\Components\Toggle::make('volatile'),
                Forms\Components\Toggle::make('inactive'),
                Forms\Components\TextInput::make('node_type')
                    ->maxLength(10),
                Forms\Components\Toggle::make('partition_indicator'),
                Forms\Components\Textarea::make('comments'),
                Forms\Components\Toggle::make('external_api_write'),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('base_table_id')
                    ->relationship('baseTable', 'name')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('object_language_locked')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('object_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('object_locked_by_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('object_locked_date')
                    ->sortable()
                    ->dateTime(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('changed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('repository_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('alias')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('file')
                    ->sortable(),
                Tables\Columns\TextColumn::make('abbreviation_1')
                    ->sortable(),
                Tables\Columns\TextColumn::make('abbreviation_2')
                    ->sortable(),
                Tables\Columns\TextColumn::make('abbreviation_3')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('append_data')
                    ->sortable(),
                Tables\Columns\TextColumn::make('dflt_mapping_col_name_prefix')
                    ->sortable(),
                Tables\Columns\TextColumn::make('seed_usage')
                    ->sortable(),
                Tables\Columns\TextColumn::make('group')
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner_organization_specifier')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('volatile')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\TextColumn::make('node_type')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('partition_indicator')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('external_api_write')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('baseTable.name')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListSiebelTables::route('/'),
            'create' => Pages\CreateSiebelTable::route('/create'),
            'view' => Pages\ViewSiebelTable::route('/{record}'),
            'edit' => Pages\EditSiebelTable::route('/{record}/edit'),
        ];
    }
}
