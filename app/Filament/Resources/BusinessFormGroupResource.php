<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessFormGroupResource\Pages;
use App\Filament\Resources\BusinessFormGroupResource\RelationManagers;
use App\Filament\Resources\BusinessFormResource\RelationManagers\BusinessFormGroupRelationManager;
use App\Models\BusinessFormGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BusinessFormGroupResource extends Resource
{
    protected static ?string $model = BusinessFormGroup::class;
    protected static ?string $navigationGroup = 'Forms';
    protected static ?string $navigationLabel = 'Form Groups';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
            RelationManagers\BusinessFormRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinessFormGroups::route('/'),
            'create' => Pages\CreateBusinessFormGroup::route('/create'),
            'view' => Pages\ViewBusinessFormGroup::route('/{record}'),
            'edit' => Pages\EditBusinessFormGroup::route('/{record}/edit'),
        ];
    }
}
