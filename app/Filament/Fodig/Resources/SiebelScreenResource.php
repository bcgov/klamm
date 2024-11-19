<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelScreenResource\Pages;
use App\Filament\Fodig\Resources\SiebelScreenResource\RelationManagers;
use App\Models\SiebelScreen;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelScreenResource extends Resource
{
    protected static ?string $model = SiebelScreen::class;

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
                Forms\Components\TextInput::make('bitmap_category')
                    ->maxLength(50),
                Forms\Components\TextInput::make('viewbar_text')
                    ->maxLength(400),
                Forms\Components\TextInput::make('viewbar_text_string_reference')
                    ->maxLength(400),
                Forms\Components\TextInput::make('viewbar_text_string_override')
                    ->maxLength(100),
                Forms\Components\Toggle::make('unrestricted_viewbar'),
                Forms\Components\TextInput::make('help_identifier')
                    ->maxLength(100),
                Forms\Components\Toggle::make('inactive'),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(500),
                Forms\Components\TextInput::make('upgrade_behavior')
                    ->maxLength(30),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('default_view_id')
                    ->relationship('defaultView', 'name')
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
                Tables\Columns\TextColumn::make('bitmap_category')
                    ->sortable(),
                Tables\Columns\TextColumn::make('viewbar_text')
                    ->sortable(),
                Tables\Columns\TextColumn::make('viewbar_text_string_reference')
                    ->sortable(),
                Tables\Columns\TextColumn::make('viewbar_text_string_override')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('unrestricted_viewbar')
                    ->sortable(),
                Tables\Columns\TextColumn::make('help_identifier')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('upgrade_behavior')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('object_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('object_language_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('defaultView.name')
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
            'index' => Pages\ListSiebelScreens::route('/'),
            'create' => Pages\CreateSiebelScreen::route('/create'),
            'view' => Pages\ViewSiebelScreen::route('/{record}'),
            'edit' => Pages\EditSiebelScreen::route('/{record}/edit'),
        ];
    }
}
