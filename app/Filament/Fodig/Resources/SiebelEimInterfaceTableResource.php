<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelEimInterfaceTableResource\Pages;
use App\Filament\Fodig\Resources\SiebelEimInterfaceTableResource\RelationManagers;
use App\Models\SiebelEimInterfaceTable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelEimInterfaceTableResource extends Resource
{
    protected static ?string $model = SiebelEimInterfaceTable::class;

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
                Forms\Components\TextInput::make('user_name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\TextInput::make('type')
                    ->required()
                    ->maxLength(30),
                Forms\Components\Toggle::make('file')
                    ->required(),
                Forms\Components\TextInput::make('eim_delete_proc_column')
                    ->maxLength(30),
                Forms\Components\TextInput::make('eim_export_proc_column')
                    ->maxLength(30),
                Forms\Components\TextInput::make('eim_merge_proc_column')
                    ->maxLength(30),
                Forms\Components\Toggle::make('inactive')
                    ->required(),
                Forms\Components\Textarea::make('comments'),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('target_table_id')
                    ->relationship('targetTable', 'name')
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
                Tables\Columns\TextColumn::make('user_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('file')
                    ->sortable(),
                Tables\Columns\TextColumn::make('eim_delete_proc_column')
                    ->sortable(),
                Tables\Columns\TextColumn::make('eim_export_proc_column')
                    ->sortable(),
                Tables\Columns\TextColumn::make('eim_merge_proc_column')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('object_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('object_language_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('targetTable.name')
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
            'index' => Pages\ListSiebelEimInterfaceTables::route('/'),
            'create' => Pages\CreateSiebelEimInterfaceTable::route('/create'),
            'view' => Pages\ViewSiebelEimInterfaceTable::route('/{record}'),
            'edit' => Pages\EditSiebelEimInterfaceTable::route('/{record}/edit'),
        ];
    }
}
