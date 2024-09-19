<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\DataTypeResource\Pages;
use App\Filament\Bre\Resources\DataTypeResource\RelationManagers;
use App\Models\BREDataType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DataTypeResource extends Resource
{
    protected static ?string $model = BREDataType::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-2-stack';
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationGroup = 'Rule Building';

    protected static ?string $navigationLabel = 'Data Value Types';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('short_description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('long_description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('value_type_id')
                    ->relationship('breValueType', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('short_description')
                    ->label('Label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bre_value_type_name')
                    ->label('Value Type'),
                Tables\Columns\TextColumn::make('long_description')
                    ->label('Description')
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
            ->defaultSort('name')
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
            ])
            ->paginated([
                10,
                25,
                50,
                100,
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
            'index' => Pages\ListDataTypes::route('/'),
            'create' => Pages\CreateDataType::route('/create'),
            'view' => Pages\ViewDataType::route('/{record}'),
            'edit' => Pages\EditDataType::route('/{record}/edit'),
        ];
    }
}
