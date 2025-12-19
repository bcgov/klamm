<?php

namespace App\Filament\Fodig\Resources;

use App\Enums\SeedContractMode;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\Pages;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\RelationManagers\ActivityLogRelationManager;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\RelationManagers;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymizationMethods;
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
                            ->required()
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\Select::make('data_type_id')
                            ->label('Data type')
                            ->relationship('dataType', 'data_type_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\TextInput::make('column_name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\TextInput::make('column_id')
                            ->numeric()
                            ->minValue(0)
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\Toggle::make('nullable')
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Dimensions')
                    ->schema([
                        Forms\Components\TextInput::make('data_length')
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\TextInput::make('char_length')
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\TextInput::make('data_precision')
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\TextInput::make('data_scale')
                            ->numeric()
                            ->minValue(0)
                            ->nullable()
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
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
                            ->columnSpanFull()
                            ->visibleOn('edit'),
                        Forms\Components\Placeholder::make('related_columns_display')
                            ->label('Related columns')
                            ->columnSpanFull()
                            ->content(function (?AnonymousSiebelColumn $record): string {
                                if (! $record) {
                                    return '—';
                                }
                                $raw = $record->related_columns;
                                // Normalize stored value to array of strings
                                $names = [];
                                if (is_array($raw)) {
                                    $names = array_values(array_filter(array_map(function ($v) {
                                        if (is_string($v)) return trim($v);
                                        if (is_object($v) && isset($v->name)) return trim((string)$v->name);
                                        if (is_array($v) && isset($v['name'])) return trim((string)$v['name']);
                                        return is_scalar($v) ? trim((string)$v) : null;
                                    }, $raw)));
                                } elseif (is_string($raw)) {
                                    // CSV or single string
                                    $names = collect(preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY))
                                        ->map(fn($s) => trim($s))
                                        ->filter()
                                        ->values()
                                        ->all();
                                } elseif (! $raw && $record->related_columns_raw) {
                                    // Fallback to raw text
                                    $names = collect(preg_split('/[\s,]+/', (string)$record->related_columns_raw, -1, PREG_SPLIT_NO_EMPTY))
                                        ->map(fn($s) => trim($s))
                                        ->filter()
                                        ->values()
                                        ->all();
                                }

                                if (empty($names)) {
                                    return '—';
                                }

                                // Attempt to resolve to columns in same table by name
                                $table = $record->getRelationValue('table') ?? $record->table()->withTrashed()->first();
                                $query = $table
                                    ? $table->columns()->whereIn('column_name', $names)
                                    : AnonymousSiebelColumn::query()->whereIn('column_name', $names);

                                $found = $query->get(['id', 'column_name'])->keyBy('column_name');
                                // If not found locally, try global lookup by simple name (last token after dot)
                                $simpleNames = collect($names)
                                    ->map(fn($n) => (str_contains($n, '.') ? preg_replace('/^.*\./', '', $n) : $n))
                                    ->map(fn($n) => trim($n))
                                    ->filter()
                                    ->values()
                                    ->all();
                                $globalFound = AnonymousSiebelColumn::query()
                                    ->whereIn('column_name', $simpleNames)
                                    ->get(['id', 'column_name'])
                                    ->groupBy('column_name');
                                $links = [];
                                foreach ($names as $n) {
                                    $col = $found->get($n);
                                    if ($col) {
                                        $url = static::getUrl('view', ['record' => $col->id]);
                                        $links[] = "<a href=\"{$url}\" class=\"text-primary-600 hover:underline\">" . e($col->column_name) . "</a>";
                                    } else {
                                        $key = str_contains($n, '.') ? preg_replace('/^.*\./', '', $n) : $n;
                                        $candidate = optional($globalFound->get($key))->first();
                                        if ($candidate) {
                                            $url = static::getUrl('view', ['record' => $candidate->id]);
                                            $links[] = "<a href=\"{$url}\" class=\"text-primary-600 hover:underline\">" . e($candidate->column_name) . "</a>";
                                        } else {
                                            $links[] = e($n);
                                        }
                                    }
                                }

                                return implode(', ', $links);
                            })
                            ->hiddenOn('edit'),
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
                        Forms\Components\ToggleButtons::make('anonymization_requirement_reviewed')
                            ->label('Anonymization requirement reviewed')
                            ->inline()
                            ->options([
                                '' => 'Unreviewed',
                                '1' => 'Yes',
                                '0' => 'No',
                            ])
                            ->colors([
                                '' => 'gray',
                                '1' => 'success',
                                '0' => 'danger',
                            ])
                            ->afterStateHydrated(function (Forms\Components\ToggleButtons $component, $state): void {
                                if ($state === null) {
                                    $component->state('');
                                    return;
                                }
                                $component->state($state ? '1' : '0');
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if ($state === '' || $state === null) {
                                    return null;
                                }

                                return $state === '1';
                            }),
                        Forms\Components\Toggle::make('anonymization_required')
                            ->label('Anonymization required'),
                        Forms\Components\Select::make('anonymizationMethods')
                            ->label('Anonymization methods')
                            ->relationship('anonymizationMethods', 'name', fn(Builder $query) => $query
                                ->select([
                                    'anonymization_methods.id',
                                    'anonymization_methods.name',
                                    'anonymization_methods.category',
                                ])
                                ->orderBy('anonymization_methods.name'))
                            ->multiple()
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(function (AnonymizationMethods $record): string {
                                $summary = $record->categorySummary();

                                return $summary
                                    ? ($record->name . ' — ' . $summary)
                                    : $record->name;
                            })
                            ->preload()
                            ->nullable()
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Sync metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('last_synced_at')
                            ->label('Last synced')
                            ->content(fn(?AnonymousSiebelColumn $record) => optional($record?->last_synced_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('changed_at')
                            ->label('Changed at')
                            ->content(fn(?AnonymousSiebelColumn $record) => optional($record?->changed_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('content_hash')
                            ->label('Content hash')
                            ->content(fn(?AnonymousSiebelColumn $record) => $record?->content_hash ?? '—'),
                    ])
                    ->columns(2)
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
                // Tables\Columns\TextColumn::make('seed_contract_summary')
                //     ->label('')
                //     ->visible(false),
                Tables\Columns\IconColumn::make('anonymization_requirement_reviewed')
                    ->label('Requirement reviewed')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('anonymization_required')
                    ->label('Anonymization required')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('nullable')
                    ->boolean(),
                Tables\Columns\TextColumn::make('parentColumns')
                    ->label('Parent columns')
                    ->html()
                    ->getStateUsing(function (AnonymousSiebelColumn $record): string {
                        $parents = $record->parentColumns()
                            ->select('anonymous_siebel_columns.id', 'anonymous_siebel_columns.column_name')
                            ->get();
                        if ($parents->isEmpty()) {
                            return '—';
                        }
                        return $parents->map(function ($col) {
                            $url = static::getUrl('view', ['record' => $col->id]);
                            $name = e($col->column_name);
                            return "<a href=\"{$url}\" class=\"text-primary-600 hover:underline\">{$name}</a>";
                        })->implode(', ');
                    })
                    ->toggleable()
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('parentColumns', function (Builder $q) use ($search) {
                            $q->where('column_name', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('childColumns')
                    ->label('Child columns')
                    ->html()
                    ->getStateUsing(function (AnonymousSiebelColumn $record): string {
                        $children = $record->childColumns()
                            ->select('anonymous_siebel_columns.id', 'anonymous_siebel_columns.column_name')
                            ->get();
                        if ($children->isEmpty()) {
                            return '—';
                        }
                        return $children->map(function ($col) {
                            $url = static::getUrl('view', ['record' => $col->id]);
                            $name = e($col->column_name);
                            return "<a href=\"{$url}\" class=\"text-primary-600 hover:underline\">{$name}</a>";
                        })->implode(', ');
                    })
                    ->toggleable()
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('childColumns', function (Builder $q) use ($search) {
                            $q->where('column_name', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('relatedColumns')
                    ->label('Related columns')
                    ->html()
                    ->getStateUsing(function (AnonymousSiebelColumn $record): string {
                        $raw = $record->related_columns;
                        $names = [];
                        if (is_array($raw)) {
                            $names = array_values(array_filter(array_map(function ($v) {
                                if (is_string($v)) return trim($v);
                                if (is_object($v) && isset($v->name)) return trim((string)$v->name);
                                if (is_array($v) && isset($v['name'])) return trim((string)$v['name']);
                                return is_scalar($v) ? trim((string)$v) : null;
                            }, $raw)));
                        } elseif (is_string($raw)) {
                            $names = collect(preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY))
                                ->map(fn($s) => trim($s))
                                ->filter()
                                ->values()
                                ->all();
                        } elseif (! $raw && $record->related_columns_raw) {
                            $names = collect(preg_split('/[\s,]+/', (string)$record->related_columns_raw, -1, PREG_SPLIT_NO_EMPTY))
                                ->map(fn($s) => trim($s))
                                ->filter()
                                ->values()
                                ->all();
                        }
                        if (empty($names)) {
                            return '—';
                        }
                        $table = $record->getRelationValue('table') ?? $record->table()->withTrashed()->first();
                        $query = $table
                            ? $table->columns()->whereIn('column_name', $names)
                            : AnonymousSiebelColumn::query()->whereIn('column_name', $names);
                        $found = $query->get(['id', 'column_name'])->keyBy('column_name');
                        $simpleNames = collect($names)
                            ->map(fn($n) => (str_contains($n, '.') ? preg_replace('/^.*\./', '', $n) : $n))
                            ->map(fn($n) => trim($n))
                            ->filter()
                            ->values()
                            ->all();
                        $globalFound = AnonymousSiebelColumn::query()
                            ->whereIn('column_name', $simpleNames)
                            ->get(['id', 'column_name'])
                            ->groupBy('column_name');
                        $links = [];
                        foreach ($names as $n) {
                            $col = $found->get($n);
                            if ($col) {
                                $url = static::getUrl('view', ['record' => $col->id]);
                                $links[] = "<a href=\"{$url}\" class=\"text-primary-600 hover:underline\">" . e($col->column_name) . "</a>";
                            } else {
                                $key = str_contains($n, '.') ? preg_replace('/^.*\./', '', $n) : $n;
                                $candidate = optional($globalFound->get($key))->first();
                                if ($candidate) {
                                    $url = static::getUrl('view', ['record' => $candidate->id]);
                                    $links[] = "<a href=\"{$url}\" class=\"text-primary-600 hover:underline\">" . e($candidate->column_name) . "</a>";
                                } else {
                                    $links[] = e($n);
                                }
                            }
                        }
                        return implode(', ', $links);
                    })
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
                    ->relationship('anonymizationMethods', 'name', fn(Builder $query) => $query
                        ->select([
                            'anonymization_methods.id',
                            'anonymization_methods.name',
                            'anonymization_methods.category',
                        ])
                        ->orderBy('anonymization_methods.name'))
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('anonymization_method_categories')
                    ->label('Method category')
                    ->multiple()
                    ->options(fn() => array_combine(
                        AnonymizationMethods::categoryOptionsWithExisting(),
                        AnonymizationMethods::categoryOptionsWithExisting(),
                    ))
                    ->query(function (Builder $query, array $data) {
                        $values = $data['values'] ?? null;

                        if (! is_array($values) || $values === []) {
                            return $query;
                        }

                        return $query->whereHas('anonymizationMethods', function (Builder $methodQuery) use ($values) {
                            $methodQuery->where(function (Builder $builder) use ($values) {
                                foreach ($values as $category) {
                                    if (! is_string($category) || trim($category) === '') {
                                        continue;
                                    }

                                    $category = trim($category);

                                    $builder
                                        ->orWhereJsonContains('anonymization_methods.categories', $category)
                                        ->orWhere('anonymization_methods.category', $category);
                                }
                            });
                        });
                    }),
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
            // 'bulk-assign' => Pages\BulkAssignSeedContracts::route('/bulk-assign-seed-contracts'),
        ];
    }
}
