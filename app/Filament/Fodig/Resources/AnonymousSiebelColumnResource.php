<?php

namespace App\Filament\Fodig\Resources;

use App\Enums\SeedContractMode;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\Pages;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\RelationManagers\ActivityLogRelationManager;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\RelationManagers;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
                        Forms\Components\Fieldset::make('Seed contract')
                            ->schema([
                                Forms\Components\Select::make('seed_contract_mode')
                                    ->label('Seed role')
                                    ->placeholder('Select seed role')
                                    ->options(SeedContractMode::options())
                                    ->searchable()
                                    ->native(false)
                                    ->helperText('Label how this column participates in deterministic seed propagation.')
                                    ->dehydrateStateUsing(fn($state) => $state ?: null),
                                Forms\Components\Placeholder::make('seed_contract_mode_description')
                                    ->label('Guidance')
                                    ->content(function (Get $get): string {
                                        $mode = $get('seed_contract_mode');

                                        if (! $mode) {
                                            return 'Select a seed role to see expected behavior.';
                                        }

                                        $enum = SeedContractMode::tryFrom($mode);

                                        return $enum?->description() ?? 'Unknown seed role.';
                                    })
                                    ->columnSpan(2),
                                Forms\Components\Textarea::make('seed_contract_expression')
                                    ->label('Seed expression / bundle definition')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->helperText('Document the SQL expression or ordered bundle that should be reused by dependent columns.'),
                                Forms\Components\Textarea::make('seed_contract_notes')
                                    ->label('Seed notes')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->helperText('Capture edge cases, migrations, or verification steps tied to this seed contract.'),
                            ])
                            ->columns(2),
                        Forms\Components\Select::make('anonymizationMethods')
                            ->label('Anonymization methods')
                            ->relationship('anonymizationMethods', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('seed_contract_summary_display')
                            ->label('Current seed contract summary')
                            ->content(fn(?AnonymousSiebelColumn $record) => $record?->seed_contract_summary ?? 'Not declared')
                            ->columnSpanFull()
                            ->visibleOn('edit'),
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
                Tables\Columns\TextColumn::make('table.schema.schema_name')
                    ->label('Schema')
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
                Tables\Columns\TextColumn::make('seed_contract_summary')
                    ->label('Seed contract')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('anonymization_required')
                    ->label('Anonymization required')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('nullable')
                    ->boolean(),
                Tables\Columns\TextColumn::make('parentColumns_count')
                    ->label('Parent columns')
                    ->counts('parentColumns')
                    ->sortable()
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('childColumns_count')
                    ->label('Child columns')
                    ->counts('childColumns')
                    ->sortable()
                    ->badge()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('database_id')
                    ->label('Database')
                    ->searchable()
                    ->preload()
                    ->relationship('table.schema.database', 'database_name'),
                Tables\Filters\SelectFilter::make('schema_id')
                    ->label('Schema')
                    ->searchable()
                    ->preload()
                    ->relationship('table.schema', 'schema_name'),
                Tables\Filters\SelectFilter::make('table_id')
                    ->label('Table')
                    ->searchable()
                    ->relationship('table', 'table_name'),
                Tables\Filters\SelectFilter::make('data_type_id')
                    ->label('Data type')
                    ->relationship('dataType', 'data_type_name'),
                Tables\Filters\SelectFilter::make('anonymizationMethods')
                    ->label('Anonymization method')
                    ->relationship('anonymizationMethods', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('seed_contract_mode')
                    ->label('Seed role')
                    ->options(SeedContractMode::options()),
                Tables\Filters\SelectFilter::make('parentColumns')
                    ->label('Depends on column')
                    ->relationship('parentColumns', 'column_name')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('childColumns')
                    ->label('Used by column')
                    ->relationship('childColumns', 'column_name')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('has_parents')
                    ->label('Has parent columns')
                    ->nullable()
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('parentColumns'),
                        false: fn(Builder $query) => $query->whereDoesntHave('parentColumns'),
                    ),
                Tables\Filters\TernaryFilter::make('has_children')
                    ->label('Has child columns')
                    ->nullable()
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('childColumns'),
                        false: fn(Builder $query) => $query->whereDoesntHave('childColumns'),
                    ),
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
            RelationManagers\ParentColumnsRelationManager::class,
            RelationManagers\ChildColumnsRelationManager::class,
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
            'bulk-assign' => Pages\BulkAssignSeedContracts::route('/bulk-assign-seed-contracts'),
        ];
    }
}
