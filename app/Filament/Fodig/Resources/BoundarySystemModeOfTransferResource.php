<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemModeOfTransferResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemModeOfTransferResource\RelationManagers;
use App\Models\BoundarySystemModeOfTransfer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemModeOfTransferResource extends Resource
{
    protected static ?string $model = BoundarySystemModeOfTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Data Gateway';

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
            'index' => Pages\ListBoundarySystemModeOfTransfers::route('/'),
            'create' => Pages\CreateBoundarySystemModeOfTransfer::route('/create'),
            'view' => Pages\ViewBoundarySystemModeOfTransfer::route('/{record}'),
            'edit' => Pages\EditBoundarySystemModeOfTransfer::route('/{record}/edit'),
        ];
    }
}
