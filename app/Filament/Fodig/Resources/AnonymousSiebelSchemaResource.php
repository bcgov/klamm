<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource\Pages;
use App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource\RelationManagers;
use App\Models\Anonymizer\AnonymousSiebelSchema;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnonymousSiebelSchemaResource extends Resource
{
    protected static ?string $model = AnonymousSiebelSchema::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Anonymizer';
    protected static ?string $navigationLabel = 'Siebel Schemas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Select::make('database_id')
                            ->label('Database')
                            ->relationship('database', 'database_name')
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('schema_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('type')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Sync metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('content_hash')
                            ->label('Content hash')
                            ->content(fn(?AnonymousSiebelSchema $record) => $record?->content_hash ?? '—'),
                        Forms\Components\Placeholder::make('last_synced_at')
                            ->label('Last synced')
                            ->content(fn(?AnonymousSiebelSchema $record) => optional($record?->last_synced_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('changed_at')
                            ->label('Changed at')
                            ->content(fn(?AnonymousSiebelSchema $record) => optional($record?->changed_at)?->toDayDateTimeString() ?? '—'),
                    ])
                    ->columns(3)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('schema_name')
                    ->label('Schema')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('database.database_name')
                    ->label('Database')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tables_count')
                    ->counts('tables')
                    ->label('Tables')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('changed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('database_id')
                    ->label('Database')
                    ->relationship('database', 'database_name'),
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
            ->defaultSort('schema_name');
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
            'index' => Pages\ListAnonymousSiebelSchemas::route('/'),
            'create' => Pages\CreateAnonymousSiebelSchema::route('/create'),
            'edit' => Pages\EditAnonymousSiebelSchema::route('/{record}/edit'),
        ];
    }
}
