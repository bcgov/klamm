<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymousSiebelDataTypeResource\Pages;
use App\Models\Anonymizer\AnonymousSiebelDataType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnonymousSiebelDataTypeResource extends Resource
{
    protected static ?string $model = AnonymousSiebelDataType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Anonymizer';
    protected static ?string $navigationLabel = 'Data Types';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('data_type_name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn(?AnonymousSiebelDataType $record) => (bool) $record?->exists),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn(?AnonymousSiebelDataType $record) => (bool) $record?->exists),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('data_type_name')
                    ->label('Data type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(60),
                Tables\Columns\TextColumn::make('columns_count')
                    ->counts('columns')
                    ->label('Columns')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_type_name');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Fodig\RelationManagers\ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnonymousSiebelDataTypes::route('/'),
            'create' => Pages\CreateAnonymousSiebelDataType::route('/create'),
            'edit' => Pages\EditAnonymousSiebelDataType::route('/{record}/edit'),
        ];
    }
}
