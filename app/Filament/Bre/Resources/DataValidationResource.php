<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\DataValidationResource\Pages;
use App\Filament\Bre\Resources\DataValidationResource\RelationManagers;
use App\Models\BREDataValidation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\BREValidationType;

class DataValidationResource extends Resource
{
    protected static ?string $model = BREDataValidation::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Rule Building';

    protected static ?string $navigationLabel = 'Field Data Validation';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('validation_type_id')
                    ->relationship('breValidationType', 'name'),
                Forms\Components\Textarea::make('validation_criteria')
                    ->columnSpanFull(),
                //

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
                Tables\Columns\TextColumn::make('bre_validation_type')
                    ->label('Value Type'),
                Tables\Columns\TextColumn::make('validation_criteria')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListDataValidations::route('/'),
            'create' => Pages\CreateDataValidation::route('/create'),
            'view' => Pages\ViewDataValidation::route('/{record}'),
            'edit' => Pages\EditDataValidation::route('/{record}/edit'),
        ];
    }
}
