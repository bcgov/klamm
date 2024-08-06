<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelWebTemplateResource\Pages;
use App\Filament\Fodig\Resources\SiebelWebTemplateResource\RelationManagers;
use App\Models\SiebelWebTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelWebTemplateResource extends Resource
{
    protected static ?string $model = SiebelWebTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('definition')
                    ->nullable(),
                Forms\Components\TextInput::make('name')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\Toggle::make('changed')
                    ->nullable(),
                Forms\Components\TextInput::make('type')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\Toggle::make('inactive')
                    ->nullable(),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(500)
                    ->nullable(),
                Forms\Components\Toggle::make('object_locked')
                    ->nullable(),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10)
                    ->nullable(),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
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
                Tables\Columns\TextColumn::make('type')
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
            'index' => Pages\ListSiebelWebTemplates::route('/'),
            'create' => Pages\CreateSiebelWebTemplate::route('/create'),
            'view' => Pages\ViewSiebelWebTemplate::route('/{record}'),
            'edit' => Pages\EditSiebelWebTemplate::route('/{record}/edit'),
        ];
    }
}
