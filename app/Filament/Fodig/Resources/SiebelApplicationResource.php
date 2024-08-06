<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelApplicationResource\Pages;
use App\Filament\Fodig\Resources\SiebelApplicationResource\RelationManagers;
use App\Models\SiebelApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelApplicationResource extends Resource
{
    protected static ?string $model = SiebelApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Toggle::make('changed')
                    ->required(),
                Forms\Components\TextInput::make('repository_name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\TextInput::make('menu')
                    ->maxLength(50),
                Forms\Components\Toggle::make('scripted')
                    ->required(),
                Forms\Components\TextInput::make('acknowledgment_web_page')
                    ->maxLength(100),
                Forms\Components\TextInput::make('container_web_page')
                    ->maxLength(250),
                Forms\Components\TextInput::make('error_web_page')
                    ->maxLength(50),
                Forms\Components\TextInput::make('login_web_page')
                    ->maxLength(100),
                Forms\Components\TextInput::make('logoff_acknowledgment_web_page')
                    ->maxLength(250),
                Forms\Components\TextInput::make('acknowledgment_web_view')
                    ->maxLength(250),
                Forms\Components\TextInput::make('default_find')
                    ->maxLength(30),
                Forms\Components\Toggle::make('inactive')
                    ->required(),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(400),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('task_screen_id')
                    ->relationship('taskScreen', 'name')
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
                Tables\Columns\TextColumn::make('menu')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('scripted')
                    ->sortable(),
                Tables\Columns\TextColumn::make('acknowledgment_web_page')
                    ->sortable(),
                Tables\Columns\TextColumn::make('container_web_page')
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_web_page')
                    ->sortable(),
                Tables\Columns\TextColumn::make('login_web_page')
                    ->sortable(),
                Tables\Columns\TextColumn::make('logoff_acknowledgment_web_page')
                    ->sortable(),
                Tables\Columns\TextColumn::make('acknowledgment_web_view')
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_find')
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
                Tables\Columns\TextColumn::make('taskScreen.name')
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
            'index' => Pages\ListSiebelApplications::route('/'),
            'create' => Pages\CreateSiebelApplication::route('/create'),
            'view' => Pages\ViewSiebelApplication::route('/{record}'),
            'edit' => Pages\EditSiebelApplication::route('/{record}/edit'),
        ];
    }
}
