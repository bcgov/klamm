<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\ValueTypeResource\Pages;
use App\Filament\Bre\Resources\ValueTypeResource\RelationManagers;
use App\Models\BREValueType;
use App\Models\ValueType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ValueTypeResource extends Resource
{
    protected static ?string $model = BREValueType::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-2-stack';
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationGroup = 'Rule Building';

    protected static ?string $navigationLabel = 'Field Value Types';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description'),
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
            'index' => Pages\ListValueTypes::route('/'),
            'create' => Pages\CreateValueType::route('/create'),
            'view' => Pages\ViewValueType::route('/{record}'),
            'edit' => Pages\EditValueType::route('/{record}/edit'),
        ];
    }
}
