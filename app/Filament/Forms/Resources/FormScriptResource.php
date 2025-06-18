<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormScriptResource\Pages;
use App\Filament\Forms\Resources\FormScriptResource\RelationManagers;
use App\Models\FormScript;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormScriptResource extends Resource
{
    protected static ?string $model = FormScript::class;

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
            'index' => Pages\ListFormScripts::route('/'),
            'create' => Pages\CreateFormScript::route('/create'),
            'view' => Pages\ViewFormScript::route('/{record}'),
            'edit' => Pages\EditFormScript::route('/{record}/edit'),
        ];
    }
}
