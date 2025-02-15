<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemResource\Pages;
use App\Filament\Fodig\Resources\BoundarySystemResource\RelationManagers;
use App\Models\BoundarySystem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BoundarySystemResource extends Resource
{
    protected static ?string $model = BoundarySystem::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Data Gateway';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('ministry_id')
                    ->relationship('ministry', 'name'),
                Forms\Components\TextInput::make('interface_name'),
                Forms\Components\Toggle::make('active'),
                Forms\Components\Textarea::make('comments'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ministry.short_name'),
                Tables\Columns\TextColumn::make('interface_name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
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
            'index' => Pages\ListBoundarySystems::route('/'),
            'create' => Pages\CreateBoundarySystem::route('/create'),
            'view' => Pages\ViewBoundarySystem::route('/{record}'),
            'edit' => Pages\EditBoundarySystem::route('/{record}/edit'),
        ];
    }
}
