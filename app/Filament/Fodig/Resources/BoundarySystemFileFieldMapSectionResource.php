<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapSectionResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapSectionResource\RelationManagers;
use App\Models\BoundarySystemFileFieldMapSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemFileFieldMapSectionResource extends Resource
{
    protected static ?string $model = BoundarySystemFileFieldMapSection::class;

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
            'index' => Pages\ListBoundarySystemFileFieldMapSections::route('/'),
            'create' => Pages\CreateBoundarySystemFileFieldMapSection::route('/create'),
            'view' => Pages\ViewBoundarySystemFileFieldMapSection::route('/{record}'),
            'edit' => Pages\EditBoundarySystemFileFieldMapSection::route('/{record}/edit'),
        ];
    }
}
