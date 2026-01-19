<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelTableResource\RelationManagers;

use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ColumnsRelationManager extends RelationManager
{
    protected static string $relationship = 'columns';

    protected static ?string $title = 'Columns';

    protected static ?string $recordTitleAttribute = 'column_name';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Columns')
            ->modifyQueryUsing(fn(Builder $query) => $query->withCount(['childColumns', 'parentColumns']))
            ->columns([
                Tables\Columns\TextColumn::make('column_name')
                    ->label('Column')
                    ->url(fn($record) => AnonymousSiebelColumnResource::getUrl('view', ['record' => $record->id]))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('child_columns_count')
                    ->label('Has children')
                    ->boolean()
                    ->state(fn($record): bool => (int) ($record->child_columns_count ?? 0) > 0)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('parent_columns_count')
                    ->label('Has parents')
                    ->boolean()
                    ->state(fn($record): bool => (int) ($record->parent_columns_count ?? 0) > 0)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('dataType.data_type_name')
                    ->label('Data type')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('anonymization_required')
                    ->label('Anonymization required')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('nullable')
                    ->label('Nullable')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('changed_at')
                    ->label('Changed at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('has_child_columns')
                    ->label('Has child columns')
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('childColumns'),
                        false: fn(Builder $query) => $query->whereDoesntHave('childColumns'),
                    ),
                Tables\Filters\TernaryFilter::make('has_parent_columns')
                    ->label('Has parent columns')
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('parentColumns'),
                        false: fn(Builder $query) => $query->whereDoesntHave('parentColumns'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn($record) => AnonymousSiebelColumnResource::getUrl('view', ['record' => $record->id])),
            ])
            ->defaultSort('column_name');
    }
}
