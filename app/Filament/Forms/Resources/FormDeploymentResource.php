<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormDeploymentResource\Pages;
use App\Filament\Forms\Resources\FormDeploymentResource\RelationManagers;
use App\Models\FormDeployment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormDeploymentResource extends Resource
{
    protected static ?string $model = FormDeployment::class;

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
            'index' => Pages\ListFormDeployments::route('/'),
            'create' => Pages\CreateFormDeployment::route('/create'),
            'view' => Pages\ViewFormDeployment::route('/{record}'),
            'edit' => Pages\EditFormDeployment::route('/{record}/edit'),
        ];
    }
}
