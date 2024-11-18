<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelValueResource\Pages;
use App\Filament\Fodig\Resources\SiebelValueResource\RelationManagers;
use App\Models\SiebelValue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelValueResource extends Resource
{
    protected static ?string $model = SiebelValue::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Siebel Tables';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('inactive')
                    ->required(),
                Forms\Components\TextInput::make('type')
                    ->maxLength(50),
                Forms\Components\Textarea::make('display_value'),
                Forms\Components\Toggle::make('changed'),
                Forms\Components\Toggle::make('translate'),
                Forms\Components\Toggle::make('multilingual'),
                Forms\Components\TextInput::make('language_independent_code')
                    ->maxLength(50),
                Forms\Components\TextInput::make('parent_lic')
                    ->maxLength(50),
                Forms\Components\TextInput::make('high')
                    ->maxLength(300),
                Forms\Components\TextInput::make('low')
                    ->maxLength(300),
                Forms\Components\TextInput::make('order')
                    ->numeric(),
                Forms\Components\Toggle::make('active'),
                Forms\Components\TextInput::make('language_name')
                    ->maxLength(200),
                Forms\Components\TextInput::make('replication_level')
                    ->maxLength(25),
                Forms\Components\TextInput::make('target_low')
                    ->numeric(),
                Forms\Components\TextInput::make('target_high')
                    ->numeric(),
                Forms\Components\TextInput::make('weighting_factor')
                    ->numeric(),
                Forms\Components\Textarea::make('description')
                    ->maxLength(500),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_value')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('changed')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('translate')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('multilingual')
                    ->sortable(),
                Tables\Columns\TextColumn::make('language_independent_code')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent_lic')
                    ->sortable(),
                Tables\Columns\TextColumn::make('high')
                    ->sortable(),
                Tables\Columns\TextColumn::make('low')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('language_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('replication_level')
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_low')
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_high')
                    ->sortable(),
                Tables\Columns\TextColumn::make('weighting_factor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
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
            'index' => Pages\ListSiebelValues::route('/'),
            'create' => Pages\CreateSiebelValue::route('/create'),
            'view' => Pages\ViewSiebelValue::route('/{record}'),
            'edit' => Pages\EditSiebelValue::route('/{record}/edit'),
        ];
    }
}
