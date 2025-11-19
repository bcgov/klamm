<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;
use App\Models\AnonymizationJobs;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Form;
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
use Illuminate\Support\Str;

class AnonymizationJobResource extends Resource
{
    protected static ?string $model = AnonymizationJobs::class;

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
                            ->placeholder('Select databases to anonymize'),
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
                            ->placeholder('Optionally scope to schemas'),
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
                            ->placeholder('Optionally scope to tables'),
                        Select::make('columns')
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
                            ->afterStateHydrated(function ($state, callable $set, $livewire) {
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
                            ->afterStateUpdated(function (?array $state, callable $set) {
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
                                TextEntry::make('category')
                                    ->label('Category')
                                    ->placeholder('—'),
                            ])
                            ->columns(2)
                            ->visible(fn(AnonymizationJobs $record) => $record->methods->isNotEmpty()),
                    ])
                    ->visible(fn(AnonymizationJobs $record) => $record->methods->isNotEmpty()),
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

        return app(AnonymizationJobScriptService::class)->buildForColumnIds($columnIds);
    }
}
