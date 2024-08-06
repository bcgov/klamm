<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelWebPageResource\Pages;
use App\Filament\Fodig\Resources\SiebelWebPageResource\RelationManagers;
use App\Models\SiebelWebPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelWebPageResource extends Resource
{
    protected static ?string $model = SiebelWebPage::class;

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
                Forms\Components\Toggle::make('do_not_use_container'),
                Forms\Components\TextInput::make('title')
                    ->maxLength(400),
                Forms\Components\TextInput::make('title_string_reference')
                    ->maxLength(400),
                Forms\Components\TextInput::make('web_template')
                    ->maxLength(200),
                Forms\Components\Toggle::make('inactive'),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(500),
                Forms\Components\Toggle::make('object_locked'),
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
                Tables\Columns\TextColumn::make('repository_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('do_not_use_container')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title_string_reference')
                    ->sortable(),
                Tables\Columns\TextColumn::make('web_template')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('object_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
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
            'index' => Pages\ListSiebelWebPages::route('/'),
            'create' => Pages\CreateSiebelWebPage::route('/create'),
            'view' => Pages\ViewSiebelWebPage::route('/{record}'),
            'edit' => Pages\EditSiebelWebPage::route('/{record}/edit'),
        ];
    }
}
