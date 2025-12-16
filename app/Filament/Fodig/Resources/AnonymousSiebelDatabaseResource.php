<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource\Pages;
use App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource\RelationManagers;
use App\Models\Anonymizer\AnonymousSiebelDatabase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnonymousSiebelDatabaseResource extends Resource
{
    protected static ?string $model = AnonymousSiebelDatabase::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Anonymizer';
    protected static ?string $navigationLabel = 'Siebel Databases';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('database_name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn(?AnonymousSiebelDatabase $record) => (bool) $record?->exists),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn(?AnonymousSiebelDatabase $record) => (bool) $record?->exists),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Sync metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('last_synced_at')
                            ->label('Last synced')
                            ->content(fn(?AnonymousSiebelDatabase $record) => optional($record?->last_synced_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('changed_at')
                            ->label('Changed at')
                            ->content(fn(?AnonymousSiebelDatabase $record) => optional($record?->changed_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('content_hash')
                            ->label('Content hash')
                            ->content(fn(?AnonymousSiebelDatabase $record) => $record?->content_hash ?? '—'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('database_name')
                    ->label('Database')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('schemas_count')
                    ->counts('schemas')
                    ->label('Schemas')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('changed_at')
                    ->dateTime()
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
            ->defaultSort('database_name');
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
            'index' => Pages\ListAnonymousSiebelDatabases::route('/'),
            'create' => Pages\CreateAnonymousSiebelDatabase::route('/create'),
            'edit' => Pages\EditAnonymousSiebelDatabase::route('/{record}/edit'),
        ];
    }
}
