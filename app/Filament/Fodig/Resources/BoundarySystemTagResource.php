<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemTagResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemTagResource\RelationManagers;
use App\Models\BoundarySystemTag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemTagResource extends Resource
{
    protected static ?string $model = BoundarySystemTag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Data Gateway';

    protected static ?string $navigationLabel = 'Tags';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
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
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);;
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
            'index' => Pages\ListBoundarySystemTags::route('/'),
            'create' => Pages\CreateBoundarySystemTag::route('/create'),
            'view' => Pages\ViewBoundarySystemTag::route('/{record}'),
            'edit' => Pages\EditBoundarySystemTag::route('/{record}/edit'),
        ];
    }
}
