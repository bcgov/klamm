<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymizationMethodResource;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\Pages;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\RelationManagers;
use App\Filament\Fodig\RelationManagers\ActivityLogRelationManager;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\Anonymizer\AnonymizationColumnTag;
use App\Models\Anonymizer\AnonymizationMethods;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class AnonymousSiebelColumnResource extends Resource
{
    protected static ?string $model = AnonymousSiebelColumn::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Anonymizer';
    protected static ?string $navigationLabel = 'Siebel Columns';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with([
                'tags',
                'anonymizationMethods',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Placeholder::make('schema_display')
                            ->label('Schema')
                            ->content(function (Get $get, ?AnonymousSiebelColumn $record): string {
                                $table = $record?->getRelationValue('table');

                                if (! $table) {
                                    $tableId = $get('table_id');
                                    $table = $tableId
                                        ? AnonymousSiebelTable::query()->withTrashed()->with('schema')->find($tableId)
                                        : null;
                                } else {
                                    $table->loadMissing('schema');
                                }

                                return $table?->schema?->schema_name ?: '—';
                            }),
                        Forms\Components\Select::make('table_id')
                            ->label('Table')
                            ->relationship('table', 'table_name')
                            ->searchable()
                            ->live()
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
                            ->columnSpanFull()
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\Textarea::make('table_comment')
                            ->rows(2)
                            ->columnSpanFull()
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\TagsInput::make('related_columns')
                            ->label('Related columns')
                            ->columnSpanFull()
                            ->visibleOn('edit')
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
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
                                // If not found locally, try global lookup
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
                            ->hiddenOn('edit')
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                        Forms\Components\Textarea::make('related_columns_raw')
                            ->rows(3)
                            ->columnSpanFull()
                            ->label('Related columns (raw)')
                            ->disabled(fn(?AnonymousSiebelColumn $record) => (bool) $record?->exists),
                    ]),
                Forms\Components\Section::make('Anonymization Settings')
                    ->schema([
                        Forms\Components\Textarea::make('metadata_comment')
                            ->label('Metadata comment')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('anonymization_requirement_reviewed')
                            ->label('Anonymization requirement reviewed')
                            ->default(false)
                            ->afterStateHydrated(function (Forms\Components\Toggle $component, $state): void {
                                $component->state((bool) ($state ?? false));
                            })
                            ->dehydrateStateUsing(fn($state): bool => (bool) ($state ?? false)),
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

                        Forms\Components\Placeholder::make('anonymization_method_links')
                            ->label('Review methods')
                            ->content(function (?AnonymousSiebelColumn $record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('—');
                                }

                                $methods = $record->getRelationValue('anonymizationMethods')
                                    ?? $record->anonymizationMethods()->get(['anonymization_methods.id', 'anonymization_methods.name']);

                                if (! $methods || $methods->isEmpty()) {
                                    return new HtmlString('—');
                                }

                                $links = $methods
                                    ->sortBy('name')
                                    ->map(function (AnonymizationMethods $method): string {
                                        $url = AnonymizationMethodResource::getUrl('view', ['record' => $method->getKey()]);
                                        $name = e((string) $method->name);

                                        return "<a href=\"{$url}\" class=\"text-primary-600 hover:underline\">{$name}</a>";
                                    })
                                    ->values()
                                    ->all();

                                return new HtmlString(implode(', ', $links));
                            })
                            ->columnSpanFull()
                            ->visibleOn(['view', 'edit']),

                        Forms\Components\Select::make('tags')
                            ->label('Column tags')
                            ->relationship('tags', 'name', fn(Builder $query) => $query->orderBy('category')->orderBy('name'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->columnSpanFull()
                            ->getOptionLabelFromRecordUsing(fn(AnonymizationColumnTag $record): string => $record->label())
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('category')
                                    ->label('Category')
                                    ->helperText('Examples: Data type, Sensitivity, Preferred method, Domain')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
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
                    ->html()
                    ->getStateUsing(function (AnonymousSiebelColumn $record): string {
                        $methods = $record->getRelationValue('anonymizationMethods')
                            ?? $record->anonymizationMethods()->get(['anonymization_methods.id', 'anonymization_methods.name']);

                        if (! $methods || $methods->isEmpty()) {
                            return '—';
                        }

                        return $methods
                            ->sortBy('name')
                            ->map(function (AnonymizationMethods $method): string {
                                $url = AnonymizationMethodResource::getUrl('view', ['record' => $method->getKey()]);
                                $name = e((string) $method->name);

                                return "<a href=\"{$url}\" class=\"text-primary-600 hover:underline\">{$name}</a>";
                            })
                            ->implode(', ');
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tags')
                    ->label('Tags')
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(function (AnonymousSiebelColumn $record): array {
                        return $record->tags
                            ->map(fn(AnonymizationColumnTag $tag) => $tag->label())
                            ->values()
                            ->all();
                    })
                    ->toggleable(),
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
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn(): array => [
                        '__any__' => 'Any method (not null)',
                    ] + AnonymizationMethods::query()
                        ->select(['anonymization_methods.id', 'anonymization_methods.name'])
                        ->orderBy('anonymization_methods.name')
                        ->pluck('anonymization_methods.name', 'anonymization_methods.id')
                        ->all())
                    ->query(function (Builder $query, array $data) {
                        $values = $data['values'] ?? null;

                        if (! is_array($values) || $values === []) {
                            return $query;
                        }

                        $values = array_values(array_filter($values, fn($v) => $v !== null && trim((string) $v) !== ''));
                        if ($values === []) {
                            return $query;
                        }

                        $hasAny = in_array('__any__', $values, true);
                        $methodIds = array_values(array_filter($values, fn($v) => $v !== '__any__'));

                        if ($hasAny && $methodIds === []) {
                            return $query->whereHas('anonymizationMethods');
                        }

                        if ($methodIds !== []) {
                            return $query->whereHas('anonymizationMethods', function (Builder $methodQuery) use ($methodIds) {
                                $methodQuery->whereIn('anonymization_methods.id', $methodIds);
                            });
                        }

                        return $query;
                    }),
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

                Tables\Filters\SelectFilter::make('tags')
                    ->label('Tags')
                    ->relationship('tags', 'name', fn(Builder $query) => $query->orderBy('category')->orderBy('name'))
                    ->multiple()
                    ->searchable()
                    ->preload(),
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
        ];
    }
}
