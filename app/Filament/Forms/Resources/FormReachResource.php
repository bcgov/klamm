<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormReachResource\Pages;
use App\Filament\Forms\Resources\FormReachResource\RelationManagers;
use App\Models\FormMetadata\FormReach;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormReachResource extends Resource
{
    protected static ?string $model = FormReach::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Form Metadata';

    protected static ?string $navigationLabel = 'Audience Size';
    protected static ?int $navigationSort = 4;


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
            'index' => Pages\ListFormReaches::route('/'),
            'create' => Pages\CreateFormReach::route('/create'),
            'edit' => Pages\EditFormReach::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Audience Size';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Audience Sizes';
    }
}
