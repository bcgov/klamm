<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelProjectResource\Pages;
use App\Filament\Fodig\Resources\SiebelProjectResource\RelationManagers;
use App\Models\SiebelProject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelProjectResource extends Resource
{
    protected static ?string $model = SiebelProject::class;

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
                Forms\Components\TextInput::make('parent_repository')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Toggle::make('inactive')
                    ->required(),
                Forms\Components\Toggle::make('locked')
                    ->required(),
                Forms\Components\TextInput::make('locked_by_name')
                    ->maxLength(50),
                Forms\Components\DateTimePicker::make('locked_date'),
                Forms\Components\TextInput::make('language_locked')
                    ->maxLength(10),
                Forms\Components\Toggle::make('ui_freeze'),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(500),
                Forms\Components\Toggle::make('allow_object_locking'),
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
                Tables\Columns\TextColumn::make('parent_repository')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('locked_by_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('locked_date')
                    ->sortable()
                    ->dateTime(),
                Tables\Columns\TextColumn::make('language_locked')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('ui_freeze')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('allow_object_locking')
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
                //
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
            'index' => Pages\ListSiebelProjects::route('/'),
            'create' => Pages\CreateSiebelProject::route('/create'),
            'view' => Pages\ViewSiebelProject::route('/{record}'),
            'edit' => Pages\EditSiebelProject::route('/{record}/edit'),
        ];
    }
}
