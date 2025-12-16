<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource\RelationManagers;

use App\Filament\Fodig\Resources\AnonymousSiebelTableResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TablesRelationManager extends RelationManager
{
    protected static string $relationship = 'tables';

    protected static ?string $title = 'Tables';

    protected static ?string $recordTitleAttribute = 'table_name';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tables')
            ->columns([
                Tables\Columns\TextColumn::make('table_name')
                    ->label('Table')
                    ->url(fn($record) => AnonymousSiebelTableResource::getUrl('view', ['record' => $record->id]))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('object_type')
                    ->label('Type')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('columns_count')
                    ->counts('columns')
                    ->label('Columns')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('changed_at')
                    ->label('Changed at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn($record) => AnonymousSiebelTableResource::getUrl('view', ['record' => $record->id])),
            ])
            ->defaultSort('table_name');
    }
}
