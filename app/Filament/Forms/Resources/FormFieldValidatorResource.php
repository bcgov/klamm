<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormFieldValidatorResource\Pages;
use App\Filament\Forms\Resources\FormFieldValidatorResource\RelationManagers;
use App\Models\FormFieldValidator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormFieldValidatorResource extends Resource
{
    protected static ?string $model = FormFieldValidator::class;

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
            'index' => Pages\ListFormFieldValidators::route('/'),
            'create' => Pages\CreateFormFieldValidator::route('/create'),
            'view' => Pages\ViewFormFieldValidator::route('/{record}'),
            'edit' => Pages\EditFormFieldValidator::route('/{record}/edit'),
        ];
    }
}
