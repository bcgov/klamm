<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelClassResource\Pages;
use App\Filament\Fodig\Resources\SiebelClassResource\RelationManagers;
use App\Models\SiebelClass;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelClassResource extends Resource
{
    protected static ?string $model = SiebelClass::class;

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
                Forms\Components\TextInput::make('dll')
                    ->maxLength(100),
                Forms\Components\TextInput::make('object_type')
                    ->maxLength(30),
                Forms\Components\Toggle::make('thin_client')
                    ->required(),
                Forms\Components\Toggle::make('java_thin_client')
                    ->required(),
                Forms\Components\Toggle::make('handheld_client')
                    ->required(),
                Forms\Components\TextInput::make('unix_support')
                    ->maxLength(10),
                Forms\Components\TextInput::make('high_interactivity_enabled')
                    ->maxLength(10),
                Forms\Components\Toggle::make('inactive')
                    ->required(),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(500),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('super_class_id')
                    ->relationship('superClass', 'name')
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
                Tables\Columns\TextColumn::make('dll')
                    ->sortable(),
                Tables\Columns\TextColumn::make('object_type')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('thin_client')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('java_thin_client')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('handheld_client')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unix_support')
                    ->sortable(),
                Tables\Columns\TextColumn::make('high_interactivity_enabled')
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
                Tables\Columns\TextColumn::make('superClass.name')
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
            'index' => Pages\ListSiebelClasses::route('/'),
            'create' => Pages\CreateSiebelClass::route('/create'),
            'view' => Pages\ViewSiebelClass::route('/{record}'),
            'edit' => Pages\EditSiebelClass::route('/{record}/edit'),
        ];
    }
}
