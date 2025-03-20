<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemFileSeparatorResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemFileSeparatorResource\RelationManagers;
use App\Models\BoundarySystemFileSeparator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemFileSeparatorResource extends Resource
{
    protected static ?string $model = BoundarySystemFileSeparator::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Data Gateway';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('separator')
                    ->required(),
                Forms\Components\TextInput::make('description')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('separator')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->paginated([10, 25, 50, 100]);
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
            'index' => Pages\ListBoundarySystemFileSeparators::route('/'),
            'create' => Pages\CreateBoundarySystemFileSeparator::route('/create'),
            'view' => Pages\ViewBoundarySystemFileSeparator::route('/{record}'),
            'edit' => Pages\EditBoundarySystemFileSeparator::route('/{record}/edit'),
        ];
    }
}
