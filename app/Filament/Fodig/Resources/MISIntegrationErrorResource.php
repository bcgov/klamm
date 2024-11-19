<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\MISIntegrationErrorResource\Pages;
use App\Filament\Fodig\Resources\MISIntegrationErrorResource\RelationManagers;
use App\Models\MISIntegrationError;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MISIntegrationErrorResource extends Resource
{
    protected static ?string $model = MISIntegrationError::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Successor System';

    protected static ?string $label = "MIS Integration Error";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('data_group_id')
                    ->relationship('dataGroup', 'name')
                    ->nullable()
                    ->preload()
                    ->required(),
                Forms\Components\Textarea::make('view')->nullable(),
                Forms\Components\Textarea::make('message_copy')->nullable(),
                Forms\Components\Textarea::make('fix')->nullable(),
                Forms\Components\Textarea::make('explanation')->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('dataGroup.name')->label('Data Group'),
                Tables\Columns\TextColumn::make('message_copy')->limit(50),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])->paginated([
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
            'index' => Pages\ListMISIntegrationErrors::route('/'),
            'create' => Pages\CreateMISIntegrationError::route('/create'),
            'edit' => Pages\EditMISIntegrationError::route('/{record}/edit'),
        ];
    }
}
