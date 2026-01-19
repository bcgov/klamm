<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Concerns\HasMonacoSql;
use App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;
use App\Filament\Fodig\Resources\AnonymizationMethodResource\RelationManagers\ColumnsRelationManager;
use App\Filament\Fodig\Resources\AnonymizationMethodResource\RelationManagers\JobsRelationManager;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymizationMethods;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Checkbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class AnonymizationMethodResource extends Resource
{
    use HasMonacoSql;

    protected static ?string $model = AnonymizationMethods::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Methods';

    protected static ?int $navigationSort = 60;

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
                Forms\Components\Section::make('Method Details')
                    ->description('Name and classify the reusable masking technique so other users can quickly discover it in the catalog.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Short, unique, and easy to scan.'),
                        Forms\Components\TagsInput::make('categories')
                            ->label('Categories')
                            ->suggestions(fn() => self::categoryOptions())
                            ->placeholder('Add one or more categories')
                            ->helperText('Used for grouping and filtering in the method library.'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Optional summary and caveats.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Implementation Notes')
                    ->description('Capture the plain-language intent, supporting documentation, and the executable SQL snippet.')
                    ->schema([
                        Forms\Components\MarkdownEditor::make('what_it_does')
                            ->label('What it does')
                            ->columnSpanFull()
                            ->helperText('Outcome and usage notes.'),
                        Forms\Components\MarkdownEditor::make('how_it_works')
                            ->label('How it works')
                            ->columnSpanFull()
                            ->helperText('Implementation detail, assumptions, and constraints.'),
                        self::sqlEditor(
                            field: 'sql_block',
                            label: 'SQL block',
                            height: '350px',
                            helperText: 'Supports placeholders like {{TABLE}}, {{COLUMN}}, {{ALIAS}} for job generation.'
                        ),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Seed Contract')
                    ->description('Declare how this method participates in the deterministic seed graph so dependent foreign keys stay aligned.')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('emits_seed')
                                    ->label('Emits seed')
                                    ->helperText('Method outputs the canonical deterministic value other columns will reuse.')
                                    ->default(false),
                                Forms\Components\Toggle::make('requires_seed')
                                    ->label('Requires seed')
                                    ->helperText('Method expects a parent-provided seed when executed.')
                                    ->default(false),
                                Forms\Components\Toggle::make('supports_composite_seed')
                                    ->label('Composite-ready')
                                    ->helperText('Method can consume or emit bundled seeds composed of multiple columns.')
                                    ->default(false),
                            ]),
                        Forms\Components\Textarea::make('seed_notes')
                            ->label('Seed notes')
                            ->rows(3)
                            ->helperText('Optional guidance for how this method should be wired into FK contracts (e.g. expected parent columns, seed bundle format).'),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Package Dependencies')
                    ->description('Attach reusable SQL packages (e.g. PL/SQL libraries) that must be included before this method runs.')
                    ->schema([
                        Forms\Components\Select::make('packages')
                            ->label('Packages')
                            ->relationship('packages', 'name', fn($query) => $query->orderBy('name'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Any package selected here will be bundled automatically when jobs use this method.'),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Preview & Guidance')
                    ->schema([
                        Forms\Components\Placeholder::make('usage_hint')
                            ->label('Column usage')
                            ->content(function (?AnonymizationMethods $record) {
                                if (! $record) {
                                    return 'Save the method to see how many columns reference it.';
                                }

                                $count = (int) $record->usage_count;

                                return $count > 0
                                    ? number_format($count) . ' column' . ($count === 1 ? '' : 's') . ' currently reference this method.'
                                    : 'No columns reference this method yet.';
                            }),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Record Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn(?AnonymizationMethods $record) => optional($record?->created_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Updated')
                            ->content(fn(?AnonymizationMethods $record) => optional($record?->updated_at)?->toDayDateTimeString() ?? '—'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->with(['packages:id,name'])
                    ->addSelect('anonymization_methods.*')
                    ->selectSub(
                        DB::table('anonymization_job_columns')
                            ->selectRaw('COUNT(DISTINCT job_id)')
                            ->whereColumn('anonymization_job_columns.anonymization_method_id', 'anonymization_methods.id')
                            ->whereNotNull('anonymization_job_columns.anonymization_method_id'),
                        'jobs_distinct_count'
                    );
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('version')
                    ->label('Ver')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('categories')
                    ->label('Categories')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Summary')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('emits_seed')
                    ->label('Emits')
                    ->boolean()
                    ->tooltip('Emits deterministic seed for dependents'),
                Tables\Columns\IconColumn::make('requires_seed')
                    ->label('Needs')
                    ->boolean()
                    ->tooltip('Requires upstream seed'),
                Tables\Columns\IconColumn::make('supports_composite_seed')
                    ->label('Composite')
                    ->boolean()
                    ->tooltip('Supports composite seed bundles')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('seed_capability_summary')
                    ->label('Seed contract')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Columns')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jobs_distinct_count')
                    ->label('Jobs')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('packages_count')
                    ->label('Packages')
                    ->counts('packages')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('packages.name')
                    ->label('Package list')
                    ->badge()
                    ->separator(' • ')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('has_sql')
                    ->label('SQL')
                    ->boolean()
                    ->tooltip(fn(AnonymizationMethods $record) => filled($record->sql_block) ? 'SQL block ready' : 'Missing SQL block')
                    ->state(fn(AnonymizationMethods $record) => filled($record->sql_block)),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('packages')
                    ->label('Package')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->relationship('packages', 'name'),
                Tables\Filters\SelectFilter::make('jobs')
                    ->label('Job')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->options(fn() => AnonymizationJobs::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data) {
                        $values = $data['values'] ?? null;

                        if (! is_array($values) || $values === []) {
                            return $query;
                        }

                        $jobIds = array_values(array_filter(array_map(
                            fn($value) => is_numeric($value) ? (int) $value : null,
                            $values
                        )));

                        if ($jobIds === []) {
                            return $query;
                        }

                        return $query->whereHas('jobs', fn(Builder $builder) => $builder->whereIn('anonymization_jobs.id', $jobIds));
                    }),
                Tables\Filters\SelectFilter::make('categories')
                    ->label('Category')
                    ->multiple()
                    ->options(fn() => self::categoryOptionsForSelect())
                    ->query(function ($query, array $data) {
                        $values = $data['values'] ?? null;

                        if (! is_array($values) || $values === []) {
                            return $query;
                        }

                        $values = array_values(array_filter(array_map(
                            fn($value) => is_string($value) ? trim($value) : null,
                            $values,
                        )));

                        if ($values === []) {
                            return $query;
                        }

                        return $query->where(function ($builder) use ($values) {
                            foreach ($values as $category) {
                                $builder
                                    ->orWhereJsonContains('categories', $category)
                                    ->orWhere('category', $category);
                            }
                        });
                    }),
                Tables\Filters\TernaryFilter::make('emits_seed')
                    ->label('Emits seed')
                    ->nullable(),
                Tables\Filters\TernaryFilter::make('requires_seed')
                    ->label('Requires seed')
                    ->nullable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('new_version')
                    ->label('New version')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->modalHeading('Create a new method version?')
                    ->modalDescription('This will duplicate the method settings into a new record (leaving existing job/column links on the current version).')
                    ->modalSubmitActionLabel('Create version')
                    ->action(function (AnonymizationMethods $record) {
                        $new = $record->createNewVersion();

                        return redirect(self::getUrl('edit', ['record' => $new]));
                    }),
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->requiresConfirmation(fn(AnonymizationMethods $record) => $record->isInUse())
                    ->modalHeading(fn(AnonymizationMethods $record) => 'Edit method currently in use?')
                    ->modalDescription(fn(AnonymizationMethods $record) => self::methodUsageWarning($record))
                    ->modalSubmitActionLabel('Continue to edit')
                    ->form(fn(AnonymizationMethods $record) => $record->isInUse()
                        ? [
                            Checkbox::make('acknowledge')
                                ->label('I understand that editing this method can change SQL generation for existing jobs/columns.')
                                ->accepted()
                                ->required(),
                        ]
                        : [])
                    ->action(fn(AnonymizationMethods $record) => redirect(self::getUrl('edit', ['record' => $record]))),
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn(AnonymizationMethods $record) => $record->isInUse() ? 'Delete method currently in use?' : 'Delete method?')
                    ->modalDescription(fn(AnonymizationMethods $record) => $record->isInUse()
                        ? (self::methodUsageWarning($record) . ' Deleting will remove this method from future selections, but existing associations may still affect previously generated exports.')
                        : 'This will soft-delete the anonymization method.')
                    ->modalSubmitActionLabel('Delete method')
                    ->form(fn(AnonymizationMethods $record) => $record->isInUse()
                        ? [
                            Checkbox::make('acknowledge')
                                ->label('I understand and want to delete this method anyway.')
                                ->accepted()
                                ->required(),
                        ]
                        : [])
                    ->action(fn(AnonymizationMethods $record) => $record->delete()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected methods?')
                        ->modalDescription(function ($records) {
                            $total = is_iterable($records) ? count($records) : 0;

                            $inUseCount = 0;
                            foreach ($records as $record) {
                                if ($record instanceof AnonymizationMethods && $record->isInUse()) {
                                    $inUseCount++;
                                }
                            }

                            if ($inUseCount > 0) {
                                return "{$inUseCount} of {$total} selected methods are currently attached to jobs/columns. This operation requires explicit acknowledgement.";
                            }

                            return 'This will soft-delete the selected anonymization methods.';
                        })
                        ->form(function ($records) {
                            $anyInUse = false;
                            foreach ($records as $record) {
                                if ($record instanceof AnonymizationMethods && $record->isInUse()) {
                                    $anyInUse = true;
                                    break;
                                }
                            }

                            return $anyInUse
                                ? [
                                    Checkbox::make('acknowledge')
                                        ->label('I understand and want to delete methods that are currently in use.')
                                        ->accepted()
                                        ->required(),
                                ]
                                : [];
                        })
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record instanceof AnonymizationMethods) {
                                    $record->delete();
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Summary')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Method')
                                    ->weight('bold'),
                                TextEntry::make('categories')
                                    ->label('Categories')
                                    ->formatStateUsing(fn($state, ?AnonymizationMethods $record) => self::formatCategorySummary($state, $record))
                                    ->placeholder('—'),
                            ])->columns(2),
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided.'),
                    ]),
                Section::make('How It Works')
                    ->schema([
                        TextEntry::make('what_it_does')
                            ->label('What it does')
                            ->columnSpanFull()
                            ->markdown()
                            ->placeholder('—'),
                        TextEntry::make('how_it_works')
                            ->label('How it works')
                            ->columnSpanFull()
                            ->markdown()
                            ->placeholder('—'),
                    ])
                    ->collapsed()
                    ->collapsible(),
                Section::make('SQL Reference')
                    ->schema([
                        self::sqlViewer(
                            field: 'sql_block',
                            label: 'SQL block',
                            height: '350px',
                            helperText: 'Use placeholders like {{TABLE}} and {{COLUMN}} in reusable snippets.'
                        ),
                    ])
                    ->hidden(fn($record) => blank($record?->sql_block)),
                Section::make('Usage Metrics')
                    ->schema([
                        TextEntry::make('seed_capability_summary')
                            ->label('Seed contract')
                            ->columnSpanFull(),
                        TextEntry::make('seed_notes')
                            ->label('Seed notes')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        TextEntry::make('usage_count')
                            ->label('Columns using this method'),
                        TextEntry::make('packages')
                            ->label('Packages included')
                            ->getStateUsing(fn(AnonymizationMethods $record) => $record->packages
                                ? $record->packages->pluck('name')->implode(', ')
                                : '—')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->label('Updated'),
                    ])
                    ->columns(3),
                Section::make('Columns in Scope')
                    ->schema([
                        TextEntry::make('columns_preview')
                            ->label('Examples')
                            ->getStateUsing(fn(AnonymizationMethods $record) => self::columnsPreview($record))
                            ->columnSpanFull()
                            ->placeholder('No columns reference this method yet.'),
                    ])
                    ->visible(fn(AnonymizationMethods $record) => $record->usage_count > 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnonymizationMethods::route('/'),
            'create' => Pages\CreateAnonymizationMethod::route('/create'),
            'view' => Pages\ViewAnonymizationMethod::route('/{record}'),
            'edit' => Pages\EditAnonymizationMethod::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ColumnsRelationManager::class,
            JobsRelationManager::class,
            \App\Filament\Fodig\RelationManagers\ActivityLogRelationManager::class,
        ];
    }

    protected static function categoryOptions(): array
    {
        return AnonymizationMethods::categoryOptionsWithExisting();
    }

    protected static function categoryOptionsForSelect(): array
    {
        $options = self::categoryOptions();

        return $options === []
            ? []
            : array_combine($options, $options);
    }

    protected static function formatCategorySummary(mixed $state, ?AnonymizationMethods $record): string
    {
        if ($record) {
            return $record->categorySummary() ?? '—';
        }

        if (! is_array($state) || $state === []) {
            return '—';
        }

        $labels = array_values(array_filter(array_map(
            fn($value) => is_string($value) ? trim($value) : null,
            $state,
        )));

        return $labels !== [] ? implode(' • ', $labels) : '—';
    }


    protected static function columnsPreview(AnonymizationMethods $record): string
    {
        $columns = $record->columns()
            ->with(['table.schema:id,schema_name', 'table:id,table_name,schema_id'])
            ->orderBy('column_name')
            ->limit(8)
            ->get();

        if ($columns->isEmpty()) {
            return 'No columns reference this method yet.';
        }

        $labels = $columns->map(function ($column) {
            $schema = $column->table?->schema?->schema_name;
            $table = $column->table?->table_name;
            $parts = array_filter([$schema, $table, $column->column_name]);

            return $parts !== [] ? implode('.', $parts) : $column->column_name;
        })->all();

        $preview = implode(', ', $labels);
        $remaining = max(0, (int) $record->usage_count - count($labels));

        if ($remaining > 0) {
            $preview .= ' +' . $remaining . ' more';
        }

        return $preview;
    }

    protected static function methodUsageWarning(AnonymizationMethods $record): string
    {
        $columnCount = (int) $record->usage_count;
        $jobCount = $record->distinctJobUsageCount();

        $parts = [];

        if ($columnCount > 0) {
            $parts[] = "Referenced by {$columnCount} column" . ($columnCount === 1 ? '' : 's');
        }

        if ($jobCount > 0) {
            $parts[] = "Included in {$jobCount} job" . ($jobCount === 1 ? '' : 's');
        }

        if ($parts === []) {
            return 'This method is not currently associated with any jobs or columns.';
        }

        return implode(' • ', $parts) . '. Changes here can impact generated anonymization SQL. Consider using “New version” to preserve existing behavior.';
    }
}
