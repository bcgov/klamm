<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\Pages;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\RelationManagers\ActivityLogRelationManager;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnonymousSiebelColumnResource extends Resource
{
    protected static ?string $model = AnonymousSiebelColumn::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Anonymizer';
    protected static ?string $navigationLabel = 'Siebel Columns';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Select::make('table_id')
                            ->label('Table')
                            ->relationship('table', 'table_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('data_type_id')
                            ->label('Data type')
                            ->relationship('dataType', 'data_type_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('column_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('column_id')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Toggle::make('nullable'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Dimensions')
                    ->schema([
                        Forms\Components\TextInput::make('data_length')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
                        Forms\Components\TextInput::make('char_length')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
                        Forms\Components\TextInput::make('data_precision')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
                        Forms\Components\TextInput::make('data_scale')
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),
                    ])
                    ->columns(4),
                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\Textarea::make('column_comment')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('table_comment')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\TagsInput::make('related_columns')
                            ->label('Related columns')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('related_columns_raw')
                            ->rows(3)
                            ->columnSpanFull()
                            ->label('Related columns (raw)'),
                    ]),
                Forms\Components\Section::make('Anonymization Settings')
                    ->schema([
                        Forms\Components\Textarea::make('metadata_comment')
                            ->label('Metadata comment')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('anonymization_required')
                            ->label('Anonymization required'),
                        Forms\Components\Select::make('anonymizationMethods')
                            ->label('Anonymization methods')
                            ->relationship('anonymizationMethods', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Sync metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('content_hash')
                            ->label('Content hash')
                            ->content(fn(?AnonymousSiebelColumn $record) => $record?->content_hash ?? '—'),
                        Forms\Components\Placeholder::make('last_synced_at')
                            ->label('Last synced')
                            ->content(fn(?AnonymousSiebelColumn $record) => optional($record?->last_synced_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('changed_at')
                            ->label('Changed at')
                            ->content(fn(?AnonymousSiebelColumn $record) => optional($record?->changed_at)?->toDayDateTimeString() ?? '—'),
                    ])
                    ->columns(3)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('column_name')
                    ->label('Column')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('table.table_name')
                    ->label('Table')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('dataType.data_type_name')
                    ->label('Data type')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('anonymizationMethods.name')
                    ->label('Anonymization methods')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('anonymization_required')
                    ->label('Anonymization required')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('nullable')
                    ->boolean(),
                Tables\Columns\TextColumn::make('data_length')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('changed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('table_id')
                    ->label('Table')
                    ->relationship('table', 'table_name'),
                Tables\Filters\SelectFilter::make('data_type_id')
                    ->label('Data type')
                    ->relationship('dataType', 'data_type_name'),
                Tables\Filters\SelectFilter::make('anonymizationMethods')
                    ->label('Anonymization method')
                    ->relationship('anonymizationMethods', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('anonymization_required')
                    ->label('Anonymization required')
                    ->nullable(),
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
            ->defaultSort('column_name');
    }

    public static function getRelations(): array
    {
        return [
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnonymousSiebelColumns::route('/'),
            'create' => Pages\CreateAnonymousSiebelColumn::route('/create'),
            'view' => Pages\ViewAnonymousSiebelColumn::route('/{record}'),
            'edit' => Pages\EditAnonymousSiebelColumn::route('/{record}/edit'),
        ];
    }
}
