<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldTypeResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemFileFieldTypeResource\RelationManagers;
use App\Models\BoundarySystemFileFieldType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemFileFieldTypeResource extends Resource
{
    protected static ?string $model = BoundarySystemFileFieldType::class;

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
            'index' => Pages\ListBoundarySystemFileFieldTypes::route('/'),
            'create' => Pages\CreateBoundarySystemFileFieldType::route('/create'),
            'view' => Pages\ViewBoundarySystemFileFieldType::route('/{record}'),
            'edit' => Pages\EditBoundarySystemFileFieldType::route('/{record}/edit'),
        ];
    }
}
