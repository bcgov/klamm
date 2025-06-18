<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\SelectOptionFormElementResource\Pages;
use App\Filament\Forms\Resources\SelectOptionFormElementResource\RelationManagers;
use App\Models\SelectOptionFormElement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SelectOptionFormElementResource extends Resource
{
    protected static ?string $model = SelectOptionFormElement::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Form Building';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            'index' => Pages\ListSelectOptionFormElements::route('/'),
            'create' => Pages\CreateSelectOptionFormElement::route('/create'),
            'view' => Pages\ViewSelectOptionFormElement::route('/{record}'),
            'edit' => Pages\EditSelectOptionFormElement::route('/{record}/edit'),
        ];
    }
}
