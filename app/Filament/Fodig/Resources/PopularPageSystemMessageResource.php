<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\PopularPageSystemMessageResource\Pages;
use App\Filament\Fodig\Resources\PopularPageSystemMessageResource\RelationManagers;
use App\Models\PopularPageSystemMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PopularPageSystemMessageResource extends Resource
{
    protected static ?string $model = PopularPageSystemMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Error Lookup Tool';

    protected static ?string $navigationLabel = 'Popular Pages';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('display_text')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('path')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_text')
                    ->searchable(),
                Tables\Columns\TextColumn::make('path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListPopularPageSystemMessages::route('/'),
            'create' => Pages\CreatePopularPageSystemMessage::route('/create'),
            'view' => Pages\ViewPopularPageSystemMessage::route('/{record}'),
            'edit' => Pages\EditPopularPageSystemMessage::route('/{record}/edit'),
        ];
    }
}
