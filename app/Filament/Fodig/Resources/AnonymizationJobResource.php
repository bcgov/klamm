<?php

namespace App\Filament\Fodig\Resources;

use App\Enums\SeedContractMode;
use App\Filament\Concerns\HasMonacoSql;
use App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;
use App\Filament\Fodig\Resources\AnonymizationJobResource\Support\AnonymizationJobReadinessHelper;
use App\Jobs\GenerateAnonymizationJobSql;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\Anonymizer\AnonymizationPackage;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class AnonymizationJobResource extends Resource
{
    use HasMonacoSql;

    protected static ?string $model = AnonymizationJobs::class;

    protected const COLUMN_MODE_MANUAL = 'custom';
    protected const COLUMN_MODE_FLAGGED = 'flagged';
    protected const COLUMN_MODE_WITH_METHODS = 'with_methods';
    protected const COLUMN_MODE_MISSING = 'missing';
    protected const COLUMN_MODE_ENTIRE_SCOPE = 'all';

    protected static array $packageDependencyCache = [];


    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Jobs';

    protected static ?int $navigationSort = 70;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->without(['methods']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Job Details')
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
                Forms\Components\Section::make('Execution Options')
                    ->schema([
                        Forms\Components\TextInput::make('target_schema')
                            ->label('Target schema')
                            ->maxLength(64)
                            ->placeholder('e.g. SBLANONP')
                            ->helperText('Optional. Overrides the schema used for ALTER SESSION SET CURRENT_SCHEMA in the generated script.'),
                        Select::make('target_table_mode')
                            ->label('Target tables')
                            ->options([
                                'prefixed' => 'Job-scoped working copies (safe) (e.g. <prefix>_INITIAL_S_CONTACT)',
                                'anon' => 'Mirror ANON_* tables (e.g. INITIAL_S_CONTACT → ANON_S_CONTACT)',
                            ])
                            ->default('prefixed')
                            ->helperText('Controls where the clone + masking updates are applied. Use ANON_* mode when you want the job to produce anonymized copies in your ANON_ tables.'),
                        Select::make('target_relation_kind')
                            ->label('Target creation')
                            ->options([
                                'table' => 'Create table (w/ masking updates)',
                                'view' => 'Create view (read-only w/ masking updates)',
                            ])
                            ->nullable()
                            ->placeholder('Default (tables)')
                            ->helperText('Leave blank to use the default table behavior. Table-level overrides can change this per table.'),
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
                    ->collapsible()
                    ->columns(2),
                Forms\Components\Section::make('Scope')
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
                            ->afterStateUpdated(fn($state, Set $set, Get $get) => self::handleScopeUpdated($set, $get))
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
                            ->afterStateUpdated(fn($state, Set $set, Get $get) => self::handleScopeUpdated($set, $get))
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
                            ->afterStateUpdated(fn($state, Set $set, Get $get) => self::handleScopeUpdated($set, $get))
                            ->placeholder('Optionally focus on specific tables that require anonymization tweaks.'),
                        Fieldset::make('Column builder')
                            ->schema([
                                ToggleButtons::make('column_builder_mode')
                                    ->label('Column selection mode')
                                    ->options(self::columnBuilderModeOptions())
                                    ->inline()
                                    ->default(self::COLUMN_MODE_MANUAL)
                                    ->live()
                                    ->dehydrated(false)
                                    ->helperText('Use helper presets to auto-populate the column list based on catalog metadata. Switch back to Manual to take full control.')
                                    ->afterStateUpdated(fn(?string $state, Set $set, Get $get) => self::handleColumnBuilderModeChange($state, $set, $get)),
                                Forms\Components\Placeholder::make('column_selection_summary')
                                    ->label('Selection summary')
                                    ->content(fn(Get $get) => self::columnSelectionSummary($get('columns'), $get('column_builder_mode')))
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('package_selection_summary')
                                    ->label('Package dependencies')
                                    ->content(fn(Get $get) => self::packageDependenciesSummary($get('columns'), $get('column_builder_mode')))
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('readiness_summary')
                                    ->label('Readiness summary')
                                    ->content(fn(Get $get, $livewire) => self::readinessSummary($get, $livewire->getRecord()))
                                    ->columnSpanFull(),
                                Forms\Components\Placeholder::make('readiness_issues')
                                    ->label('Readiness issues')
                                    ->content(fn(Get $get, $livewire) => self::readinessIssuesHtml($get, $livewire->getRecord()))
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
                            ->options(fn(Get $get) => self::scopedColumnPickerOptions(self::scopeContextFromForm($get), limit: 50))
                            ->getSearchResultsUsing(fn(string $search, Get $get) => self::scopedColumnPickerOptions(self::scopeContextFromForm($get), search: $search, limit: 75))
                            ->getOptionLabelsUsing(fn(array $values, Get $get) => self::scopedColumnPickerOptions(self::scopeContextFromForm($get), ids: $values))
                            ->reactive()
                            ->disabled(function (Get $get): bool {
                                if (self::isEntireScopeMode($get('column_builder_mode'))) {
                                    return true;
                                }
                                $context = self::scopeContextFromForm($get);
                                return $context['tables'] === [];
                            })
                            ->saveRelationshipsUsing(fn($record, $state, Get $get) => self::persistColumnsSelection($record, $state, $get))
                            ->helperText(function (Get $get): string {
                                if (self::isEntireScopeMode($get('column_builder_mode'))) {
                                    return 'Manual selection is disabled in Entire scope mode.';
                                }

                                $context = self::scopeContextFromForm($get);
                                if ($context['tables'] === []) {
                                    return 'Select one or more tables above to enable manual column selection.';
                                }

                                return 'Fine-tune the generated list by searching or removing specific columns. The SQL preview updates automatically.';
                            })
                            ->getOptionLabelFromRecordUsing(fn(AnonymousSiebelColumn $record) => self::formatColumnLabel($record))
                            ->afterStateHydrated(function ($state, callable $set, Get $get, $livewire) {
                                $existingScript = optional($livewire->getRecord())->sql_script ?? '';
                                self::updateSqlPreviewFromSelection($state, $set, $get, $existingScript);
                            })
                            ->afterStateUpdated(function (?array $state, callable $set, Get $get) {
                                self::updateSqlPreviewFromSelection($state, $set, $get);
                            })
                            ->placeholder('Optionally scope to columns'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Run Tracking')
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
                Forms\Components\Section::make('Generated Script')
                    ->schema([
                        Forms\Components\Placeholder::make('sql_generation_status')
                            ->label('Generation status')
                            ->content(function ($livewire) {
                                if (! $livewire instanceof Pages\ViewAnonymizationJob) {
                                    return '';
                                }

                                return new HtmlString(
                                    '<span class="text-sm text-warning-600">Regenerating SQL…</span>'
                                );
                            })
                            ->visible(fn($livewire) => $livewire instanceof Pages\ViewAnonymizationJob && $livewire->isSqlRegenerating)
                            ->extraAttributes(fn($livewire) => $livewire instanceof Pages\ViewAnonymizationJob && $livewire->isSqlRegenerating
                                ? ['wire:poll.5s' => 'refreshSqlPreview']
                                : [])
                            ->dehydrated(false),
                        Forms\Components\Hidden::make('sql_script')
                            ->default(fn(?AnonymizationJobs $record) => $record?->sql_script),
                        self::sqlEditor(
                            field: 'sql_script_preview',
                            label: 'Generated SQL',
                            height: '475px',
                            helperText: 'SQL is generated from anonymization methods linked to the selected columns.',
                        )
                            ->default(fn(?AnonymizationJobs $record) => $record?->sql_script)
                            ->disabled()
                            ->live()
                            ->reactive()
                            ->dehydrated(false),
                    ])
                    ->columns(1),
            ]);
    }

    // Readiness report for the current form selection (scope vs explicit columns).
    protected static function readinessReport(Get $get, ?AnonymizationJobs $record): array
    {
        return AnonymizationJobReadinessHelper::reportForSelection(
            mode: $get('column_builder_mode'),
            columns: $get('columns'),
            scope: self::scopeContextFromForm($get),
            jobId: $record?->getKey(),
        );
    }

    protected static function readinessSummary(Get $get, ?AnonymizationJobs $record): string
    {
        return AnonymizationJobReadinessHelper::summary(self::readinessReport($get, $record));
    }

    protected static function readinessIssuesHtml(Get $get, ?AnonymizationJobs $record): HtmlString
    {
        return AnonymizationJobReadinessHelper::issuesHtml(
            self::readinessReport($get, $record),
            emptyMessage: 'No issues detected for the current selection.'
        );
    }

    protected static function readinessReportForRecord(AnonymizationJobs $record): array
    {
        return AnonymizationJobReadinessHelper::reportForJob($record);
    }

    protected static function readinessSummaryForRecord(AnonymizationJobs $record): string
    {
        return AnonymizationJobReadinessHelper::summary(self::readinessReportForRecord($record));
    }

    protected static function readinessIssuesHtmlForRecord(AnonymizationJobs $record): HtmlString
    {
        return AnonymizationJobReadinessHelper::issuesHtml(
            self::readinessReportForRecord($record),
            emptyMessage: 'No issues detected for this job selection.'
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->addSelect('anonymization_jobs.*')
                    ->selectSub(
                        DB::table('anonymization_job_columns')
                            ->selectRaw('COUNT(DISTINCT anonymization_method_id)')
                            ->whereColumn('anonymization_job_columns.job_id', 'anonymization_jobs.id')
                            ->whereNotNull('anonymization_method_id'),
                        'methods_distinct_count'
                    );
            })
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
                TextColumn::make('methods_distinct_count')
                    ->label('Methods')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('methods_summary')
                    ->label('Method list')
                    ->state(function (AnonymizationJobs $record): string {
                        $methods = $record->methods
                            ->unique('id')
                            ->pluck('name')
                            ->filter()
                            ->values();

                        if ($methods->isEmpty()) {
                            return '—';
                        }

                        return $methods->implode(' • ');
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('job_type')
                    ->label('Job type')
                    ->options(self::jobTypeOptions()),
                Tables\Filters\SelectFilter::make('output_format')
                    ->label('Output format')
                    ->options(self::outputFormatOptions()),
                Tables\Filters\SelectFilter::make('methods')
                    ->label('Method')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn() => AnonymizationMethods::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data) {
                        $values = $data['values'] ?? null;

                        if (! is_array($values) || $values === []) {
                            return $query;
                        }

                        $ids = array_values(array_filter(array_map(fn($value) => is_numeric($value) ? (int) $value : null, $values)));

                        if ($ids === []) {
                            return $query;
                        }

                        return $query->whereHas('methods', fn(Builder $builder) => $builder->whereIn('anonymization_methods.id', $ids));
                    }),
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
                Section::make('Job Summary')
                    ->schema([
                        TextEntry::make('name')
                            ->columnSpanFull()
                            ->size('lg')
                            ->weight('bold'),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])
                            ->schema([
                                TextEntry::make('job_type')
                                    ->label('Job type')
                                    ->formatStateUsing(fn(string $state) => self::jobTypeOptions()[$state] ?? Str::headline($state))
                                    ->badge()
                                    ->color(fn(string $state) => $state === AnonymizationJobs::TYPE_FULL ? 'primary' : 'info'),
                                TextEntry::make('output_format')
                                    ->label('Output format')
                                    ->formatStateUsing(fn(string $state) => self::outputFormatOptions()[$state] ?? Str::upper($state))
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('status')
                                    ->formatStateUsing(fn(string $state) => Str::headline($state))
                                    ->badge()
                                    ->color(fn(string $state) => self::statusColor($state)),
                                TextEntry::make('duration_human')
                                    ->label('Last duration')
                                    ->placeholder('—'),
                            ]),
                        Grid::make(2)
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
                Section::make('Selected Data')
                    ->schema([
                        Grid::make([
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
                Section::make('Readiness (Non-blocking)')
                    ->schema([
                        TextEntry::make('readiness_summary_view')
                            ->label('Readiness summary')
                            ->getStateUsing(fn(AnonymizationJobs $record) => self::readinessSummaryForRecord($record))
                            ->columnSpanFull(),
                        TextEntry::make('readiness_issues_view')
                            ->label('What is blocking?')
                            ->getStateUsing(fn(AnonymizationJobs $record) => self::readinessIssuesHtmlForRecord($record))
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Section::make('Methods in Use')
                    ->schema([
                        RepeatableEntry::make('methods_list')
                            ->label('Methods')
                            ->getStateUsing(fn(AnonymizationJobs $record) => self::methodsForJob($record))
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Method')
                                    ->weight('medium')
                                    ->getStateUsing(fn(?AnonymizationMethods $record) => $record?->name ?? '—'),
                                TextEntry::make('categories')
                                    ->label('Category')
                                    ->getStateUsing(fn(?AnonymizationMethods $record) => $record?->categorySummary() ?? '—')
                                    ->placeholder('—'),
                            ])
                            ->columns(2)

                            ->visible(fn(AnonymizationJobs $record) => self::methodsForJob($record)->isNotEmpty()),
                    ])
                    ->collapsible()
                    ->visible(fn(AnonymizationJobs $record) => self::methodsForJob($record)->isNotEmpty()),
                Section::make('Package Dependencies')
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
                                    ->weight('medium'),
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
                Section::make('Generated SQL Script')
                    ->schema([
                        TextEntry::make('sql_generation_status')
                            ->label('Generation status')
                            ->state(fn($livewire) => $livewire instanceof Pages\ViewAnonymizationJob && $livewire->isSqlRegenerating
                                ? 'Regenerating SQL…'
                                : null)
                            ->color('warning')
                            ->visible(fn($livewire) => $livewire instanceof Pages\ViewAnonymizationJob && $livewire->isSqlRegenerating)
                            ->extraEntryWrapperAttributes(fn($livewire) => $livewire instanceof Pages\ViewAnonymizationJob && $livewire->isSqlRegenerating
                                ? ['wire:poll.3s' => 'refreshSqlPreview']
                                : [])
                            ->columnSpanFull(),
                        self::sqlViewer(
                            field: 'sql_script',
                            label: 'Generated SQL',
                            height: '475px',
                            helperText: 'SQL is generated from anonymization methods linked to the selected columns.',
                        ),
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

    public static function buildSqlPreview(array $columnIds): string
    {
        $columnIds = array_filter(array_map('intval', $columnIds));
        if ($columnIds === []) {
            return '';
        }

        self::ensureSeedContractModesForColumns($columnIds);

        return app(AnonymizationJobScriptService::class)->buildForColumnIds($columnIds);
    }

    // Keep the generated SQL preview synced to the current selection.
    // if "Entire scope" mode is selected, avoid generating a large SQL preview client-side
    protected static function updateSqlPreviewFromSelection(
        mixed $state,
        Set | callable $set,
        Get $get,
        string $existingScript = ''
    ): void {
        if (self::isEntireScopeMode($get('column_builder_mode'))) {
            self::applyEntireScopePreview($set);
            return;
        }

        $columnIds = is_array($state) ? $state : ($state ? [$state] : []);
        $columnIds = self::sanitizeIds($columnIds);

        if ($columnIds === []) {
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
    }

    // Ensure that selected columns have a valid SeedContractMode backing value.
    // this updates only columns in the provided list and does not overwrite non-null values.
    protected static function ensureSeedContractModesForColumns(array $columnIds): void
    {
        $columnIds = array_values(array_filter(array_map('intval', $columnIds)));

        if ($columnIds === []) {
            return;
        }
        self::normalizeSeedContractModesForColumns($columnIds);
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

    // Map semantic labels (seed_source/consumer/composite) to SeedContractMode enum backing values.
    // Convert a loose semantic label (e.g. 'seed_source', 'consumer') into the actual string value used by the SeedContractMode enum.
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
            self::COLUMN_MODE_MANUAL => 'Manual',
            self::COLUMN_MODE_FLAGGED => 'Flagged columns',
            self::COLUMN_MODE_WITH_METHODS => 'Has methods',
            self::COLUMN_MODE_MISSING => 'Missing method',
            self::COLUMN_MODE_ENTIRE_SCOPE => 'Entire scope',
        ];
    }

    protected static function syncColumnsFromMode(?string $mode, Set $set, Get $get): void
    {
        if ($mode === null || $mode === self::COLUMN_MODE_MANUAL || $mode === self::COLUMN_MODE_ENTIRE_SCOPE) {
            return;
        }

        $set('columns', self::autoColumnsForMode($mode, self::scopeContextFromForm($get)));
    }

    // When scope changes, keep column selection consistent
    protected static function handleScopeUpdated(Set $set, Get $get): void
    {
        $mode = $get('column_builder_mode') ?? self::COLUMN_MODE_MANUAL;

        if ($mode === self::COLUMN_MODE_MANUAL) {
            $context = self::scopeContextFromForm($get);
            $hasScope = ($context['databases'] !== []) || ($context['schemas'] !== []) || ($context['tables'] !== []);
            $hasManualColumns = self::sanitizeIds($get('columns')) !== [];

            if ($hasScope && ! $hasManualColumns) {
                $set('column_builder_mode', self::COLUMN_MODE_ENTIRE_SCOPE);
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

    // Auto-select columns based on a preset mode and current scope context.
    // Modes include flagged, missing, or has_methods.
    protected static function autoColumnsForMode(string $mode, array $context): array
    {
        $query = self::scopedColumnsQuery($context)
            ->orderBy('schemas.schema_name')
            ->orderBy('tables.table_name')
            ->orderBy('anonymous_siebel_columns.column_name');

        $query = match ($mode) {
            self::COLUMN_MODE_FLAGGED => $query->where('anonymous_siebel_columns.anonymization_required', true),
            self::COLUMN_MODE_MISSING => $query->whereDoesntHave('anonymizationMethods'),
            self::COLUMN_MODE_WITH_METHODS => $query->whereHas('anonymizationMethods'),
            default => $query,
        };

        return $query
            ->get()
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    // Build a reusable query joining columns->tables->schemas->databases and apply the context filters
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

    // Options provider for the manual Columns picker. Enforces scoping so the dropdown only shows columns in the selected scope.
    protected static function scopedColumnPickerOptions(array $context, ?string $search = null, ?array $ids = null, int $limit = 50): array
    {
        if ($context['tables'] === [] && $ids === null) {
            return [];
        }

        $query = AnonymousSiebelColumn::query()
            ->select([
                'anonymous_siebel_columns.id',
                'databases.database_name',
                'schemas.schema_name',
                'tables.table_name',
                'anonymous_siebel_columns.column_name',
            ])
            ->join('anonymous_siebel_tables as tables', 'tables.id', '=', 'anonymous_siebel_columns.table_id')
            ->join('anonymous_siebel_schemas as schemas', 'schemas.id', '=', 'tables.schema_id')
            ->join('anonymous_siebel_databases as databases', 'databases.id', '=', 'schemas.database_id');

        if (is_array($ids) && $ids !== []) {
            $query->whereIn('anonymous_siebel_columns.id', array_values(array_filter(array_map('intval', $ids))));
        } else {
            $query->whereIn('tables.id', $context['tables']);

            if (is_string($search) && trim($search) !== '') {
                $needle = '%' . Str::lower(trim($search)) . '%';

                $query->where(function (Builder $q) use ($needle): void {
                    $q->whereRaw('LOWER(anonymous_siebel_columns.column_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(tables.table_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(schemas.schema_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(databases.database_name) LIKE ?', [$needle]);
                });
            }

            $query
                ->orderBy('schemas.schema_name')
                ->orderBy('tables.table_name')
                ->orderBy('anonymous_siebel_columns.column_name')
                ->limit(max(1, $limit));
        }

        return $query
            ->get()
            ->mapWithKeys(function ($row): array {
                $label = implode('.', [
                    (string) $row->database_name,
                    (string) $row->schema_name,
                    (string) $row->table_name,
                    (string) $row->column_name,
                ]);

                return [(int) $row->id => $label];
            })
            ->all();
    }

    protected static function scopeContextFromForm(Get $get): array
    {
        return [
            'databases' => self::sanitizeIds($get('databases')),
            'schemas' => self::sanitizeIds($get('schemas')),
            'tables' => self::sanitizeIds($get('tables')),
        ];
    }

    // Normalize null/single/array values.
    protected static function sanitizeIds($value): array
    {
        $ids = array_map('intval', Arr::wrap($value));

        return array_values(array_filter($ids, fn(int $id) => $id > 0));
    }

    // If a scope exists but no explicit columns, treat it as implicit full-scope behavior.
    protected static function columnSelectionSummary($columnIds, ?string $mode = null): string
    {
        if (self::isEntireScopeMode($mode)) {
            return 'Entire scope selected — SQL will be generated for every in-scope column after save.';
        }
        $ids = self::sanitizeIds($columnIds);
        if ($ids === []) {
            return 'No columns selected yet.';
        }

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

    protected static function methodsForJob(AnonymizationJobs $job): Collection
    {
        $methodIds = DB::table('anonymization_job_columns')
            ->where('job_id', $job->getKey())
            ->whereNotNull('anonymization_method_id')
            ->distinct()
            ->pluck('anonymization_method_id')
            ->map(fn($id) => (int) $id)
            ->filter();

        if ($methodIds->isEmpty()) {
            return collect();
        }

        return AnonymizationMethods::query()
            ->whereIn('id', $methodIds->all())
            ->orderBy('name')
            ->get();
    }

    protected static function normalizeMethodKey(?string $name, ?string $categories): string
    {
        $normalize = static function (?string $value): string {
            if ($value === null) {
                return '';
            }

            $value = str_replace("\u{00A0}", ' ', $value);
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

            return mb_strtolower(trim($value));
        };

        return $normalize($name) . '|' . $normalize($categories);
    }

    protected static function packagesForJob(AnonymizationJobs $job)
    {
        $jobId = (int) $job->getKey();

        if (array_key_exists($jobId, self::$packageDependencyCache)) {
            return self::$packageDependencyCache[$jobId];
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

        self::$packageDependencyCache[$jobId] = $packages;

        return self::$packageDependencyCache[$jobId];
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

    protected static function applyEntireScopePreview(Set | callable $set): void
    {
        $set('sql_script', null);
        $set('sql_script_preview', self::allScopePreviewMessage());
    }

    protected static function isEntireScopeMode(?string $mode): bool
    {
        return $mode === self::COLUMN_MODE_ENTIRE_SCOPE;
    }

    protected static function allScopePreviewMessage(): string
    {
        return '-- Entire scope selected. SQL will be generated for all scoped columns after saving this job.';
    }

    protected static function persistColumnsSelection($record, mixed $state, Get $get): void
    {
        if (! $record instanceof AnonymizationJobs) {
            return;
        }

        if (! self::isEntireScopeMode($get('column_builder_mode'))) {
            $record->columns()->sync(Arr::wrap($state));
            GenerateAnonymizationJobSql::dispatch($record->getKey());
            return;
        }

        self::syncJobColumnsForScope($record, self::scopeContextFromForm($get));

        GenerateAnonymizationJobSql::dispatch($record->getKey());
    }

    public static function syncEntireScopeSelectionForJob(AnonymizationJobs $job, array $scope): void
    {
        $context = [
            'databases' => self::sanitizeIds($scope['databases'] ?? []),
            'schemas' => self::sanitizeIds($scope['schemas'] ?? []),
            'tables' => self::sanitizeIds($scope['tables'] ?? []),
        ];

        self::syncJobColumnsForScope($job, $context);
    }

    protected static function syncJobColumnsForScope(AnonymizationJobs $job, array $context): void
    {
        $job->columns()->detach();

        if (self::isScopeEmpty($context)) {
            return;
        }

        self::scopedJobColumnSelectionQuery($context)
            ->chunkById(
                5_000,
                function (Collection $rows) use ($job) {
                    $payload = [];

                    foreach ($rows as $row) {
                        $payload[(int) $row->id] = [
                            'anonymization_method_id' => $row->anonymization_method_id !== null
                                ? (int) $row->anonymization_method_id
                                : null,
                        ];
                    }

                    if ($payload !== []) {
                        $job->columns()->attach($payload);
                    }
                },
                'anonymous_siebel_columns.id',
                'id'
            );
    }

    protected static function isScopeEmpty(array $context): bool
    {
        return ($context['databases'] ?? []) === []
            && ($context['schemas'] ?? []) === []
            && ($context['tables'] ?? []) === [];
    }

    // Query for “entire scope” selection (actionable columns + MIN method id for pivot).
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
        return $query
            ->where(function (Builder $q) {
                $q->where('anonymous_siebel_columns.anonymization_required', true)
                    ->orWhereNotNull('amc.method_id');
            })
            ->groupBy('anonymous_siebel_columns.id', 'anonymous_siebel_columns.anonymization_required')
            ->orderBy('anonymous_siebel_columns.id');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Fodig\RelationManagers\ActivityLogRelationManager::class,
        ];
    }
}
