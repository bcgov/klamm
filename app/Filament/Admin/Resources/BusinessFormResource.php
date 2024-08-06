<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BusinessFormResource\Pages;
use App\Filament\Admin\Resources\BusinessFormResource\RelationManagers;
use App\Models\BusinessForm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BusinessFormResource extends Resource
{
    protected static ?string $model = BusinessForm::class;

    protected static ?string $navigationLabel = 'Forms';
    protected static ?string $navigationGroup = 'Forms';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('code'),
                Forms\Components\Textarea::make('short_description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('long_description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('internal_description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('ado_identifier')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
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
            RelationManagers\BusinessFormGroupRelationManager::class,
            RelationManagers\FieldGroupRelationManager::class,
            RelationManagers\FormFieldRelationManager::class,
            RelationManagers\ProgramRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinessForms::route('/'),
            'create' => Pages\CreateBusinessForm::route('/create'),
            'view' => Pages\ViewBusinessForm::route('/{record}'),
            'edit' => Pages\EditBusinessForm::route('/{record}/edit'),
        ];
    }
}
