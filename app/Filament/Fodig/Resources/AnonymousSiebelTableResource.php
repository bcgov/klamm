<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymousSiebelTableResource\Pages;
use App\Models\Anonymizer\AnonymousSiebelTable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnonymousSiebelTableResource extends Resource
{
    protected static ?string $model = AnonymousSiebelTable::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Anonymizer';
    protected static ?string $navigationLabel = 'Siebel Tables';

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
                        Forms\Components\Select::make('schema_id')
                            ->label('Schema')
                            ->relationship('schema', 'schema_name')
                            ->searchable()
                            ->required()
                            ->disabled(fn(?AnonymousSiebelTable $record) => (bool) $record?->exists),
                        Forms\Components\TextInput::make('table_name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn(?AnonymousSiebelTable $record) => (bool) $record?->exists),
                        Forms\Components\TextInput::make('object_type')
                            ->maxLength(255)
                            ->disabled(fn(?AnonymousSiebelTable $record) => (bool) $record?->exists),
                        Forms\Components\Textarea::make('table_comment')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn(?AnonymousSiebelTable $record) => (bool) $record?->exists),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Sync metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('last_synced_at')
                            ->label('Last synced')
                            ->content(fn(?AnonymousSiebelTable $record) => optional($record?->last_synced_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('changed_at')
                            ->label('Changed at')
                            ->content(fn(?AnonymousSiebelTable $record) => optional($record?->changed_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('content_hash')
                            ->label('Content hash')
                            ->content(fn(?AnonymousSiebelTable $record) => $record?->content_hash ?? '—'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('table_name')
                    ->label('Table')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('schema.schema_name')
                    ->label('Schema')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('object_type')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('columns_count')
                    ->counts('columns')
                    ->label('Columns')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('changed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('schema_id')
                    ->label('Schema')
                    ->relationship('schema', 'schema_name'),
                Tables\Filters\TrashedFilter::make(),
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
            ->defaultSort('table_name');
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
            'index' => Pages\ListAnonymousSiebelTables::route('/'),
            'create' => Pages\CreateAnonymousSiebelTable::route('/create'),
            'view' => Pages\ViewAnonymousSiebelTable::route('/{record}'),
            'edit' => Pages\EditAnonymousSiebelTable::route('/{record}/edit'),
        ];
    }
}
