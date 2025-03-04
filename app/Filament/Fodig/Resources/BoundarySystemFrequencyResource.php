<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemFrequencyResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemFrequencyResource\RelationManagers;
use App\Models\BoundarySystemFrequency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemFrequencyResource extends Resource
{
    protected static ?string $model = BoundarySystemFrequency::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Data Gateway';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
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
            'index' => Pages\ListBoundarySystemFrequencies::route('/'),
            'create' => Pages\CreateBoundarySystemFrequency::route('/create'),
            'view' => Pages\ViewBoundarySystemFrequency::route('/{record}'),
            'edit' => Pages\EditBoundarySystemFrequency::route('/{record}/edit'),
        ];
    }
}
