<?php

namespace App\Filament\Bre\Resources;

use App\Filament\Bre\Resources\FieldGroupResource\Pages;
use App\Filament\Bre\Resources\FieldGroupResource\RelationManagers;
use App\Models\FieldGroup;
use App\Models\BREFieldGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FieldGroupResource extends Resource
{
    protected static ?string $model = BREFieldGroup::class;
    protected static ?string $navigationLabel = 'BRE Rule Field Groups';
    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Rule Building';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('label'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('internal_description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('field_group_id')
                    ->multiple()
                    ->preload()
                    ->relationship('breFields', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
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
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFieldGroups::route('/'),
            'create' => Pages\CreateFieldGroup::route('/create'),
            'view' => Pages\ViewFieldGroup::route('/{record}'),
            'edit' => Pages\EditFieldGroup::route('/{record}/edit'),
        ];
    }
}
