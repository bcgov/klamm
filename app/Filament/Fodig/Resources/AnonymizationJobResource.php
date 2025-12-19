<?php

namespace App\Filament\Fodig\Resources;

use App\Enums\SeedContractMode;
use App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;
use App\Jobs\GenerateAnonymizationJobSql;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymizationPackage;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Grid as InfolistGrid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnonymizationJobResource extends Resource
{
    protected static ?string $model = AnonymizationJobs::class;

    protected const PACKAGE_DEPENDENCY_RELATION = '__packageDependencies';

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?int $navigationSort = 70;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FormSection::make('Job Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('job_type')
                            ->label('Job type')
                            ->required()
                            ->options(self::jobTypeOptions())
                            ->default(AnonymizationJobs::TYPE_FULL),
                        Select::make('output_format')
                            ->label('Output format')
                            ->required()
                            ->options(self::outputFormatOptions())
                            ->default(AnonymizationJobs::OUTPUT_SQL),
                        Select::make('status')
                            ->required()
                            ->options(self::statusOptions())
                            ->default(AnonymizationJobs::STATUS_DRAFT),
                    ])
                    ->columns(2),
                FormSection::make('Execution Options')
                    ->schema([
                        Select::make('seed_store_mode')
                            ->label('Seed map persistence')
                            ->options([
                                'temporary' => 'Temporary (drop/recreate every run)',
                                'persistent' => 'Persistent (reusable across runs)',
                            ])
                            ->default('temporary')
                            ->required()
                            ->live()
                            ->helperText('Persistent seed maps enable deterministic masking that is repeatable across runs. Store them in a secured schema and drop before distributing masked datasets.'),
                        Forms\Components\TextInput::make('seed_store_schema')
                            ->label('Seed store schema')
                            ->maxLength(64)
                            ->placeholder('e.g. SBLSEED')
                            ->visible(fn(Get $get) => $get('seed_store_mode') === 'persistent')
                            ->helperText('Optional. Defaults to the job target schema if blank.'),
                        Forms\Components\TextInput::make('seed_store_prefix')
                            ->label('Seed store prefix')
                            ->maxLength(64)
                            ->placeholder('e.g. KLAMM')
                            ->visible(fn(Get $get) => $get('seed_store_mode') === 'persistent')
                            ->helperText('Optional. Defaults to the job table prefix derived from the job name.'),
                        Select::make('seed_map_hygiene_mode')
                            ->label('Seed map hygiene')
                            ->options([
                                'none' => 'Do not emit cleanup SQL',
                                'commented' => 'Emit commented DROP statements (recommended)',
                                'execute' => 'Emit executable DROP statements',
                            ])
                            ->default('commented')
                            ->visible(fn(Get $get) => $get('seed_store_mode') === 'persistent')
                            ->helperText('Oracle-style hygiene: seed/mapping tables can contain sensitive old→new value mappings. Drop before exporting/cloning to less-secure environments.'),
                        Forms\Components\TextInput::make('job_seed')
                            ->label('Job seed')
                            ->maxLength(255)
                            ->password()
                            ->revealable()
                            ->helperText('Optional. Use in SQL blocks via {{JOB_SEED_LITERAL}} for stable deterministic hashing.'),
                        Forms\Components\Textarea::make('pre_mask_sql')
                            ->label('Pre-mask SQL')
                            ->rows(6)
                            ->placeholder('-- Optional SQL/PLSQL to run after target tables are cloned, before masking updates')
                            ->helperText('Inserted into the generated script after target table clones are created.'),
                        Forms\Components\Textarea::make('post_mask_sql')
                            ->label('Post-mask SQL')
                            ->rows(6)
                            ->placeholder('-- Optional SQL/PLSQL to run after masking updates complete')
                            ->helperText('Inserted into the generated script at the end.'),
                    ])
                    ->columns(2),
                FormSection::make('Scope')
                    ->schema([
                        Select::make('databases')
                            ->relationship(
                                name: 'databases',
                                titleAttribute: 'database_name',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->select('anonymous_siebel_databases.id', 'anonymous_siebel_databases.database_name')
                                    ->orderBy('anonymous_siebel_databases.database_name')
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn($state, Set $set, Get $get) => self::refreshColumnsFromScope($set, $get))
                            ->placeholder('Pick one or more databases as the outer boundary for this job.'),
                        Select::make('schemas')
                            ->relationship(
                                name: 'schemas',
                                titleAttribute: 'schema_name',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->select('anonymous_siebel_schemas.id', 'anonymous_siebel_schemas.schema_name')
                                    ->orderBy('anonymous_siebel_schemas.schema_name')
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn($state, Set $set, Get $get) => self::refreshColumnsFromScope($set, $get))
                            ->placeholder('Optionally narrow the scope to individual schemas.'),
                        Select::make('tables')
                            ->relationship(
                                name: 'tables',
                                titleAttribute: 'table_name',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->select('anonymous_siebel_tables.id', 'anonymous_siebel_tables.table_name')
                                    ->orderBy('anonymous_siebel_tables.table_name')
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn($state, Set $set, Get $get) => self::refreshColumnsFromScope($set, $get))
                            ->placeholder('Optionally focus on specific tables that require anonymization tweaks.'),
                        Fieldset::make('Column builder')
                            ->schema([
                                ToggleButtons::make('column_builder_mode')
                                    ->label('Column selection mode')
                                    ->options(self::columnBuilderModeOptions())
                                    ->inline()
                                    ->default('custom')
                                    ->live()
                                    ->dehydrated(false)
                                    ->helperText('Use helper presets to auto-populate the column list based on catalog metadata. Switch back to Manual to take full control.')
                                    ->afterStateUpdated(fn(?string $state, Set $set, $get) => self::handleColumnBuilderModeChange($state, $set, $get)),
                                Forms\Components\Placeholder::make('column_selection_summary')
                                    ->label('Selection summary')
                                    ->content(fn(Get $get) => self::columnSelectionSummary($get('columns'), $get('column_builder_mode')))
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('package_selection_summary')
                                    ->label('Package dependencies')
                                    ->content(fn(Get $get) => self::packageDependenciesSummary($get('columns'), $get('column_builder_mode')))
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->columnSpanFull(),
                        Select::make('columns')
                            ->label('Columns')
                            ->relationship(
                                name: 'columns',
                                titleAttribute: 'column_name',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->select('anonymous_siebel_columns.id', 'anonymous_siebel_columns.column_name')
                                    ->orderBy('anonymous_siebel_columns.column_name')
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->extraAttributes(fn(Get $get) => self::isEntireScopeMode($get('column_builder_mode'))
                                ? ['style' => 'pointer-events: none; opacity: .6;']
                                : [])
                            ->saveRelationshipsUsing(function ($record, $state, Get $get): void {
                                if (! $record instanceof AnonymizationJobs) {
                                    return;
                                }

                                if (! self::isEntireScopeMode($get('column_builder_mode'))) {
                                    $record->columns()->sync(Arr::wrap($state));
                                    GenerateAnonymizationJobSql::dispatch($record->getKey());
                                    return;
                                }

                                // Entire scope: populate pivot server-side (avoid Livewire state explosion).
                                $context = self::scopeContextFromForm($get);

                                // wipe previous explicit selections
                                $record->columns()->detach();

                                if ($context['databases'] === [] && $context['schemas'] === [] && $context['tables'] === []) {
                                    return;
                                }

                                // Entire scope: attach columns + best-effort method id (MIN(method_id)) in chunks.
                                self::scopedJobColumnSelectionQuery($context)
                                    ->chunkById(
                                        5_000,
                                        function (Collection $rows) use ($record) {
                                            $payload = [];

                                            foreach ($rows as $row) {
                                                $payload[(int) $row->id] = [
                                                    'anonymization_method_id' => $row->anonymization_method_id !== null
                                                        ? (int) $row->anonymization_method_id
                                                        : null,
                                                ];
                                            }

                                            if ($payload !== []) {
                                                $record->columns()->attach($payload);
                                            }
                                        },
                                        'anonymous_siebel_columns.id',
                                        'id'
                                    );

                                GenerateAnonymizationJobSql::dispatch($record->getKey());
                            })
                            ->helperText('Fine-tune the generated list by searching or removing specific columns. The SQL preview updates automatically.')
                            ->getOptionLabelFromRecordUsing(fn(AnonymousSiebelColumn $record) => self::formatColumnLabel($record))
                            ->afterStateHydrated(function ($state, callable $set, Get $get, $livewire) {
                                if (self::isEntireScopeMode($get('column_builder_mode'))) {
                                    self::applyEntireScopePreview($set);
                                    return;
                                }

                                $columnIds = is_array($state) ? $state : ($state ? [$state] : []);

                                if ($columnIds === []) {
                                    $existingScript = optional($livewire->getRecord())->sql_script ?? '';
                                    $preview = $existingScript !== ''
                                        ? $existingScript
                                        : '-- Select at least one column to generate anonymization SQL.';
                                    $set('sql_script', $existingScript);
                                    $set('sql_script_preview', $preview);
                                    return;
                                }

                                $script = self::buildSqlPreview($columnIds);
                                $preview = $script !== ''
                                    ? $script
                                    : '-- No anonymization SQL blocks found for the selected columns.';

                                $set('sql_script', $script);
                                $set('sql_script_preview', $preview);
                            })
                            ->afterStateUpdated(function (?array $state, callable $set, Get $get) {
                                if (self::isEntireScopeMode($get('column_builder_mode'))) {
                                    self::applyEntireScopePreview($set);
                                    return;
                                }

                                $script = self::buildSqlPreview($state ?? []);
                                $preview = $script !== ''
                                    ? $script
                                    : (($state ?? []) === []
                                        ? '-- Select at least one column to generate anonymization SQL.'
                                        : '-- No anonymization SQL blocks found for the selected columns.');

                                $set('sql_script', $script);
                                $set('sql_script_preview', $preview);
                            })
                            ->placeholder('Optionally scope to columns'),
                    ])
                    ->columns(2),
                FormSection::make('Run Tracking')
                    ->schema([
                        Forms\Components\DateTimePicker::make('last_run_at')
                            ->label('Last run at')
                            ->seconds(false),
                        Forms\Components\TextInput::make('duration_seconds')
                            ->label('Duration (seconds)')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('e.g. 3600'),
                    ])
                    ->columns(2),
                FormSection::make('Generated Script')
                    ->schema([
                        Forms\Components\Hidden::make('sql_script')
                            ->default(fn(?AnonymizationJobs $record) => $record?->sql_script),
                        Forms\Components\Textarea::make('sql_script_preview')
                            ->label('Generated SQL')
                            ->rows(15)
                            ->columnSpanFull()
                            ->default(fn(?AnonymizationJobs $record) => $record?->sql_script)
                            ->placeholder('Select columns to generate anonymization SQL.')
                            ->hint('SQL is generated from anonymization methods linked to the selected columns.')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->wrap(),
                TextColumn::make('job_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => self::jobTypeOptions()[$state] ?? Str::headline($state))
                    ->color(fn(string $state) => $state === AnonymizationJobs::TYPE_FULL ? 'primary' : 'info'),
                TextColumn::make('output_format')
                    ->label('Output')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => self::outputFormatOptions()[$state] ?? Str::upper($state))
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Str::headline($state))
                    ->color(fn(string $state) => self::statusColor($state)),
                TextColumn::make('last_run_at')
                    ->label('Last run')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('duration_human')
                    ->label('Duration')
                    ->placeholder('—'),
                TextColumn::make('databases_count')
                    ->label('Databases')
                    ->counts('databases')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('schemas_count')
                    ->label('Schemas')
                    ->counts('schemas')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tables_count')
                    ->label('Tables')
                    ->counts('tables')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('columns_count')
                    ->label('Columns')
                    ->counts('columns')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('job_type')
                    ->label('Job type')
                    ->options(self::jobTypeOptions()),
                Tables\Filters\SelectFilter::make('output_format')
                    ->label('Output format')
                    ->options(self::outputFormatOptions()),
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
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Job Summary')
                    ->schema([
                        TextEntry::make('name')
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'text-lg font-semibold text-slate-900']),
                        InfolistGrid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                            ->schema([
                                TextEntry::make('job_type')
                                    ->label('Job type')
                                    ->formatStateUsing(fn(string $state) => self::jobTypeOptions()[$state] ?? Str::headline($state))
                                    ->extraAttributes(['class' => 'inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700 w-fit']),
                                TextEntry::make('output_format')
                                    ->label('Output format')
                                    ->formatStateUsing(fn(string $state) => self::outputFormatOptions()[$state] ?? Str::upper($state))
                                    ->extraAttributes(['class' => 'inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700 w-fit']),
                                TextEntry::make('status')
                                    ->formatStateUsing(fn(string $state) => Str::headline($state))
                                    ->extraAttributes(fn(AnonymizationJobs $record) => [
                                        'class' => implode(' ', [
                                            'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium w-fit',
                                            self::statusBadgeClasses($record->status),
                                        ]),
                                    ]),
                                TextEntry::make('duration_human')
                                    ->label('Last duration')
                                    ->placeholder('—'),
                            ]),
                        InfolistGrid::make(2)
                            ->schema([
                                TextEntry::make('last_run_at')
                                    ->label('Last run')
                                    ->dateTime()
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Last updated')
                                    ->dateTime()
                                    ->placeholder('—'),
                            ]),
                    ]),
                InfolistSection::make('Selected Data')
                    ->schema([
                        InfolistGrid::make([
                            'default' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextEntry::make('databases_summary')
                                    ->label('Databases')
                                    ->getStateUsing(fn(AnonymizationJobs $record) => $record->databases()->count())
                                    ->suffix(' selected'),
                                TextEntry::make('schemas_summary')
                                    ->label('Schemas')
                                    ->getStateUsing(fn(AnonymizationJobs $record) => $record->schemas()->count())
                                    ->suffix(' selected'),
                                TextEntry::make('tables_summary')
                                    ->label('Tables')
                                    ->getStateUsing(fn(AnonymizationJobs $record) => $record->tables()->count())
                                    ->suffix(' selected'),
                                TextEntry::make('columns_summary')
                                    ->label('Columns')
                                    ->getStateUsing(fn(AnonymizationJobs $record) => $record->columns()->count())
                                    ->suffix(' selected'),
                            ]),
                    ]),
                InfolistSection::make('Methods in Use')
                    ->schema([
                        RepeatableEntry::make('methods')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Method')
                                    ->extraAttributes(['class' => 'font-medium text-slate-900']),
                                TextEntry::make('categories')
                                    ->label('Category')
                                    ->formatStateUsing(fn($state, $record) => method_exists($record, 'categorySummary')
                                        ? ($record->categorySummary() ?? '—')
                                        : '—')
                                    ->placeholder('—'),
                            ])
                            ->columns(2)
                            ->visible(fn(AnonymizationJobs $record) => $record->methods->isNotEmpty()),
                    ])
                    ->visible(fn(AnonymizationJobs $record) => $record->methods->isNotEmpty()),
                InfolistSection::make('Package Dependencies')
                    ->schema([
                        RepeatableEntry::make('packages')
                            ->label('Packages')
                            ->getStateUsing(fn(AnonymizationJobs $record) => self::packagesForJob($record)
                                ->map(fn(AnonymizationPackage $package) => [
                                    'name' => $package->name,
                                    'platform' => $package->database_platform,
                                    'summary' => $package->summary,
                                ])
                                ->all())
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Package')
                                    ->extraAttributes(['class' => 'font-medium text-slate-900']),
                                TextEntry::make('platform')
                                    ->label('Platform')
                                    ->formatStateUsing(fn(?string $state) => $state ? Str::headline($state) : '—'),
                                TextEntry::make('summary')
                                    ->label('Summary')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->visible(fn(array $state) => ! empty($state)),
                    ])
                    ->visible(fn(AnonymizationJobs $record) => self::packagesForJob($record)->isNotEmpty()),
                InfolistSection::make('Generated SQL Script')
                    ->schema([
                        TextEntry::make('sql_script')
                            ->label('SQL script')
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono whitespace-pre-wrap text-sm text-slate-900 bg-slate-950/5 rounded-lg p-4'])
                            ->placeholder('No script captured yet.'),
                    ])
                    ->visible(fn(AnonymizationJobs $record) => filled($record->sql_script)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnonymizationJobs::route('/'),
            'create' => Pages\CreateAnonymizationJob::route('/create'),
            'view' => Pages\ViewAnonymizationJob::route('/{record}'),
            'edit' => Pages\EditAnonymizationJob::route('/{record}/edit'),
            'selection' => Pages\ViewAnonymizationJobSelection::route('/{record}/selection'),
        ];
    }

    public static function jobTypeOptions(): array
    {
        return [
            AnonymizationJobs::TYPE_FULL => 'Full',
            AnonymizationJobs::TYPE_PARTIAL => 'Partial',
        ];
    }

    public static function outputFormatOptions(): array
    {
        return [
            AnonymizationJobs::OUTPUT_SQL => 'SQL',
            AnonymizationJobs::OUTPUT_PARQUET => 'Parquet',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            AnonymizationJobs::STATUS_DRAFT => 'Draft',
            AnonymizationJobs::STATUS_SCHEDULED => 'Scheduled',
            AnonymizationJobs::STATUS_RUNNING => 'Running',
            AnonymizationJobs::STATUS_COMPLETED => 'Completed',
            AnonymizationJobs::STATUS_FAILED => 'Failed',
        ];
    }

    protected static function statusColor(string $status): string
    {
        return match ($status) {
            AnonymizationJobs::STATUS_COMPLETED => 'success',
            AnonymizationJobs::STATUS_RUNNING => 'primary',
            AnonymizationJobs::STATUS_FAILED => 'danger',
            AnonymizationJobs::STATUS_SCHEDULED => 'warning',
            default => 'gray',
        };
    }

    protected static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            AnonymizationJobs::STATUS_COMPLETED => 'bg-emerald-100 text-emerald-800',
            AnonymizationJobs::STATUS_RUNNING => 'bg-indigo-100 text-indigo-800',
            AnonymizationJobs::STATUS_FAILED => 'bg-rose-100 text-rose-800',
            AnonymizationJobs::STATUS_SCHEDULED => 'bg-amber-100 text-amber-800',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public static function buildSqlPreview(array $columnIds): string
    {
        $columnIds = array_filter(array_map('intval', $columnIds));

        if ($columnIds === []) {
            return '';
        }

        self::ensureSeedContractModesForColumns($columnIds);

        return app(AnonymizationJobScriptService::class)->buildForColumnIds($columnIds);
    }

    protected static function ensureSeedContractModesForColumns(array $columnIds): void
    {
        $columnIds = array_values(array_filter(array_map('intval', $columnIds)));

        if ($columnIds === []) {
            return;
        }

        // IMPORTANT: if any selected columns have legacy/invalid strings (e.g. "seed_source"),
        // they will crash enum casting. Null them out before generating SQL.
        self::normalizeSeedContractModesForColumns($columnIds);

        // Only fill missing seed_contract_mode; do not overwrite explicit user configuration.
        $rows = DB::table('anonymization_method_column as amc')
            ->join('anonymization_methods as m', 'm.id', '=', 'amc.method_id')
            ->whereIn('amc.column_id', $columnIds)
            ->groupBy('amc.column_id')
            ->select([
                'amc.column_id',
                DB::raw('MAX(CASE WHEN m.emits_seed = true THEN 1 ELSE 0 END) as emits_seed'),
                DB::raw('MAX(CASE WHEN m.requires_seed = true THEN 1 ELSE 0 END) as requires_seed'),
            ])
            ->get();

        foreach ($rows as $row) {
            $emits = (int) ($row->emits_seed ?? 0) === 1;
            $requires = (int) ($row->requires_seed ?? 0) === 1;

            $semantic = match (true) {
                $emits && $requires => 'composite',
                $emits => 'seed_source',
                $requires => 'consumer',
                default => null,
            };

            $enumValue = self::seedContractEnumBackingValue($semantic);

            if ($enumValue === null) {
                continue;
            }

            DB::table('anonymous_siebel_columns')
                ->where('id', (int) $row->column_id)
                ->whereNull('seed_contract_mode')
                ->update([
                    'seed_contract_mode' => $enumValue,
                ]);
        }
    }

    protected static function normalizeSeedContractModesForColumns(array $columnIds): void
    {
        $allowed = array_map(static fn(SeedContractMode $case) => $case->value, SeedContractMode::cases());

        DB::table('anonymous_siebel_columns')
            ->whereIn('id', $columnIds)
            ->whereNotNull('seed_contract_mode')
            ->whereNotIn('seed_contract_mode', $allowed)
            ->update([
                'seed_contract_mode' => null,
            ]);
    }

    /**
     * Map our semantic labels to the actual enum backing values used by App\Enums\SeedContractMode.
     * This avoids hard-coding values that may differ from your enum (and would crash SeedContractMode::from()).
     */
    protected static function seedContractEnumBackingValue(?string $semantic): ?string
    {
        $semantic = $semantic !== null ? trim($semantic) : null;

        if ($semantic === null || $semantic === '') {
            return null;
        }

        $target = strtolower(preg_replace('/[^a-z]/', '', $semantic));
        $cases = SeedContractMode::cases();

        foreach ($cases as $case) {
            $nameNorm = strtolower(preg_replace('/[^a-z]/', '', $case->name));
            if ($nameNorm === $target) {
                return $case->value;
            }
        }

        foreach ($cases as $case) {
            $valueNorm = strtolower(preg_replace('/[^a-z]/', '', (string) $case->value));
            if ($valueNorm === $target) {
                return $case->value;
            }
        }

        // Handle common synonyms if your enum uses different wording.
        $synonyms = [
            'seedsource' => ['provider', 'source'],
            'consumer' => ['dependent'],
            'composite' => ['both'],
        ];

        foreach (($synonyms[$target] ?? []) as $alt) {
            $altNorm = strtolower(preg_replace('/[^a-z]/', '', $alt));
            foreach ($cases as $case) {
                $nameNorm = strtolower(preg_replace('/[^a-z]/', '', $case->name));
                $valueNorm = strtolower(preg_replace('/[^a-z]/', '', (string) $case->value));
                if ($nameNorm === $altNorm || $valueNorm === $altNorm) {
                    return $case->value;
                }
            }
        }

        return null;
    }

    protected static function columnBuilderModeOptions(): array
    {
        return [
            'custom' => 'Manual',
            'flagged' => 'Flagged columns',
            'with_methods' => 'Has methods',
            'missing' => 'Missing method',
            'all' => 'Entire scope',
        ];
    }

    protected static function syncColumnsFromMode(?string $mode, Set $set, Get $get): void
    {
        if ($mode === null || $mode === 'custom' || $mode === 'all') {
            return;
        }

        $set('columns', self::autoColumnsForMode($mode, self::scopeContextFromForm($get)));
    }

    protected static function refreshColumnsFromScope(Set $set, Get $get): void
    {
        $mode = $get('column_builder_mode') ?? 'custom';

        // If user only picks a scope (db/schema/table) and doesn't manually pick columns,
        // default to "Entire scope" to avoid 200k+ IDs in Livewire state.
        if ($mode === 'custom') {
            $context = self::scopeContextFromForm($get);
            $hasScope = ($context['databases'] !== []) || ($context['schemas'] !== []) || ($context['tables'] !== []);
            $hasManualColumns = self::sanitizeIds($get('columns')) !== [];

            if ($hasScope && ! $hasManualColumns) {
                $set('column_builder_mode', 'all');
                $set('columns', []);
                self::applyEntireScopePreview($set);
            }

            return;
        }

        if (self::isEntireScopeMode($mode)) {
            $set('columns', []);
            self::applyEntireScopePreview($set);
            return;
        }

        $set('columns', self::autoColumnsForMode($mode, self::scopeContextFromForm($get)));
    }

    protected static function autoColumnsForMode(string $mode, array $context): array
    {
        $query = self::scopedColumnsQuery($context)
            ->orderBy('schemas.schema_name')
            ->orderBy('tables.table_name')
            ->orderBy('anonymous_siebel_columns.column_name');

        $query = match ($mode) {
            'flagged' => $query->where('anonymous_siebel_columns.anonymization_required', true),
            'missing' => $query->whereDoesntHave('anonymizationMethods'),
            'with_methods' => $query->whereHas('anonymizationMethods'),
            default => $query,
        };

        return $query
            ->pluck('anonymous_siebel_columns.id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    protected static function scopedColumnsQuery(array $context): Builder
    {
        $query = AnonymousSiebelColumn::query()
            ->select([
                'anonymous_siebel_columns.id',
                'schemas.schema_name',
                'tables.table_name',
                'anonymous_siebel_columns.column_name',
            ])
            ->join('anonymous_siebel_tables as tables', 'tables.id', '=', 'anonymous_siebel_columns.table_id')
            ->join('anonymous_siebel_schemas as schemas', 'schemas.id', '=', 'tables.schema_id')
            ->join('anonymous_siebel_databases as databases', 'databases.id', '=', 'schemas.database_id');

        if ($context['tables'] !== []) {
            $query->whereIn('tables.id', $context['tables']);
        } elseif ($context['schemas'] !== []) {
            $query->whereIn('schemas.id', $context['schemas']);
        } elseif ($context['databases'] !== []) {
            $query->whereIn('databases.id', $context['databases']);
        }

        return $query->distinct();
    }

    protected static function scopeContextFromForm(Get $get): array
    {
        return [
            'databases' => self::sanitizeIds($get('databases')),
            'schemas' => self::sanitizeIds($get('schemas')),
            'tables' => self::sanitizeIds($get('tables')),
        ];
    }

    protected static function sanitizeIds($value): array
    {
        $ids = array_map('intval', Arr::wrap($value));

        return array_values(array_filter($ids, fn(int $id) => $id > 0));
    }

    protected static function columnSelectionSummary($columnIds, ?string $mode = null): string
    {
        if (self::isEntireScopeMode($mode)) {
            return 'Entire scope selected — SQL will be generated for every in-scope column after save.';
        }

        // If a scope exists but no explicit columns, treat it as implicit full-scope behavior.
        // (UI may briefly show this before refreshColumnsFromScope flips the mode to "all".)
        $ids = self::sanitizeIds($columnIds);
        if ($ids === []) {
            return 'No columns selected yet.';
        }

        /** @var Collection<int, AnonymousSiebelColumn> $columns */
        $columns = AnonymousSiebelColumn::query()
            ->select('id', 'anonymization_required')
            ->withCount('anonymizationMethods')
            ->whereIn('id', $ids)
            ->get();

        if ($columns->isEmpty()) {
            return 'No columns selected yet.';
        }

        $total = $columns->count();
        $withMethod = $columns->where('anonymization_methods_count', '>', 0)->count();
        $withoutMethod = $total - $withMethod;
        $flagged = $columns->where('anonymization_required', true)->count();

        return sprintf(
            '%s %s selected — %s with methods, %s without, %s flagged as required.',
            number_format($total),
            Str::plural('column', $total),
            number_format($withMethod),
            number_format($withoutMethod),
            number_format($flagged)
        );
    }

    protected static function packageDependenciesSummary($columnIds, ?string $mode = null): string
    {
        if (self::isEntireScopeMode($mode)) {
            return 'Entire scope selected — package requirements will be calculated from the scope on save.';
        }

        $ids = self::sanitizeIds($columnIds);

        if ($ids === []) {
            return 'No package dependencies detected yet.';
        }

        $packages = AnonymizationPackage::query()
            ->select('anonymization_packages.id', 'anonymization_packages.name', 'anonymization_packages.database_platform')
            ->join('anonymization_method_package as amp', 'amp.anonymization_package_id', '=', 'anonymization_packages.id')
            ->join('anonymization_method_column as amc', 'amc.method_id', '=', 'amp.anonymization_method_id')
            ->whereIn('amc.column_id', $ids)
            ->distinct()
            ->orderBy('anonymization_packages.name')
            ->get();

        if ($packages->isEmpty()) {
            return 'No package dependencies detected yet.';
        }

        $labels = $packages->map(function (AnonymizationPackage $package) {
            $platform = $package->database_platform ? strtoupper($package->database_platform) : null;
            return $platform
                ? $package->name . ' (' . $platform . ')'
                : $package->name;
        })->all();

        return sprintf(
            '%s %s required: %s',
            number_format($packages->count()),
            Str::plural('package', $packages->count()),
            implode(', ', $labels)
        );
    }

    protected static function packagesForJob(AnonymizationJobs $job)
    {
        if ($job->relationLoaded(self::PACKAGE_DEPENDENCY_RELATION)) {
            return $job->getRelation(self::PACKAGE_DEPENDENCY_RELATION);
        }

        $packages = AnonymizationPackage::query()
            ->select('anonymization_packages.*')
            ->join('anonymization_method_package as amp', 'amp.anonymization_package_id', '=', 'anonymization_packages.id')
            ->join('anonymization_job_columns as ajc', 'ajc.anonymization_method_id', '=', 'amp.anonymization_method_id')
            ->where('ajc.job_id', $job->getKey())
            ->whereNotNull('ajc.anonymization_method_id')
            ->distinct()
            ->orderBy('anonymization_packages.name')
            ->get();

        $job->setRelation(self::PACKAGE_DEPENDENCY_RELATION, $packages);

        return $packages;
    }

    protected static function formatColumnLabel(AnonymousSiebelColumn $column): string
    {
        $column->loadMissing('table.schema.database');

        $segments = array_filter([
            $column->table?->schema?->database?->database_name,
            $column->table?->schema?->schema_name,
            $column->table?->table_name,
            $column->column_name,
        ]);

        return $segments !== []
            ? implode('.', $segments)
            : $column->column_name;
    }

    protected static function handleColumnBuilderModeChange(?string $mode, Set $set, Get $get): void
    {
        if ($mode === null) {
            return;
        }

        if (self::isEntireScopeMode($mode)) {
            $set('columns', []);
            self::applyEntireScopePreview($set);
            return;
        }

        self::syncColumnsFromMode($mode, $set, $get);
    }

    protected static function applyEntireScopePreview(Set $set): void
    {
        $set('sql_script', null);
        $set('sql_script_preview', self::allScopePreviewMessage());
    }

    protected static function isEntireScopeMode(?string $mode): bool
    {
        return $mode === 'all';
    }

    protected static function allScopePreviewMessage(): string
    {
        return '-- Entire scope selected. SQL will be generated for all scoped columns after saving this job.';
    }

    /**
     * Build the "entire scope" column list without materializing 200k IDs in Livewire state.
     * Also returns a best-effort anonymization_method_id for the job pivot (MIN method per column).
     */
    protected static function scopedJobColumnSelectionQuery(array $context): Builder
    {
        $query = AnonymousSiebelColumn::query()
            ->select([
                'anonymous_siebel_columns.id as id',
                DB::raw('MIN(amc.method_id) as anonymization_method_id'),
                'anonymous_siebel_columns.anonymization_required',
            ])
            ->join('anonymous_siebel_tables as tables', 'tables.id', '=', 'anonymous_siebel_columns.table_id')
            ->join('anonymous_siebel_schemas as schemas', 'schemas.id', '=', 'tables.schema_id')
            ->join('anonymous_siebel_databases as databases', 'databases.id', '=', 'schemas.database_id')
            ->leftJoin('anonymization_method_column as amc', 'amc.column_id', '=', 'anonymous_siebel_columns.id');

        if ($context['tables'] !== []) {
            $query->whereIn('tables.id', $context['tables']);
        } elseif ($context['schemas'] !== []) {
            $query->whereIn('schemas.id', $context['schemas']);
        } elseif ($context['databases'] !== []) {
            $query->whereIn('databases.id', $context['databases']);
        }

        // Actionable set: flagged required OR has at least one method.
        return $query
            ->where(function (Builder $q) {
                $q->where('anonymous_siebel_columns.anonymization_required', true)
                    ->orWhereNotNull('amc.method_id');
            })
            ->groupBy('anonymous_siebel_columns.id', 'anonymous_siebel_columns.anonymization_required')
            ->orderBy('anonymous_siebel_columns.id');
    }
}
