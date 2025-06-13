<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormLocationResource\Pages;
use App\Filament\Forms\Resources\FormLocationResource\RelationManagers;
use App\Models\FormLocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormLocationResource extends Resource
{
    protected static ?string $model = FormLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Form Metadata';

    protected static ?string $navigationLabel = 'Published Location';
    protected static ?int $navigationSort = 6;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            'index' => Pages\ListFormLocations::route('/'),
            'create' => Pages\CreateFormLocation::route('/create'),
            'edit' => Pages\EditFormLocation::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Published Location';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Published Locations';
    }
}
