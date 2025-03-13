<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemFileFieldMapResource\Pages;
use App\Models\BoundarySystemFileFieldMap;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class BoundarySystemFileFieldMapResource extends Resource
{
    protected static ?string $model = BoundarySystemFileFieldMap::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Data Gateway';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('boundary_system_file_id')
                    ->relationship('boundarySystemFile', 'file_name')
                    ->label('File Name'),
                Forms\Components\Select::make('boundary_system_file_field_id')
                    ->relationship('boundarySystemFileField', 'field_name')
                    ->label('Field Name'),
                Forms\Components\Select::make('boundary_system_file_field_map_sections_id')
                    ->relationship('boundarySystemFileFieldMapSection', 'name')
                    ->label('File Section'),
                Forms\Components\Textarea::make('file_structure')->label('File Structure'),
                Forms\Components\Toggle::make('mandatory')->label('Mandatory'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('boundarySystemFile.file_name')
                    ->searchable()
                    ->sortable()
                    ->label('File Name'),
                Tables\Columns\TextColumn::make('boundarySystemFileField.field_name')
                    ->searchable()
                    ->sortable()
                    ->label('Field Name'),
                Tables\Columns\TextColumn::make('boundarySystemFileFieldMapSection.name')
                    ->searchable()
                    ->sortable()
                    ->label('File Section'),
                Tables\Columns\TextColumn::make('file_structure')
                    ->searchable()
                    ->sortable()
                    ->label('File Structure'),
                Tables\Columns\BooleanColumn::make('mandatory')
                    ->sortable()
                    ->label('Mandatory'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoundarySystemFileFieldMaps::route('/'),
            'create' => Pages\CreateBoundarySystemFileFieldMap::route('/create'),
            'view' => Pages\ViewBoundarySystemFileFieldMap::route('/{record}'),
            'edit' => Pages\EditBoundarySystemFileFieldMap::route('/{record}/edit'),
        ];
    }
}
