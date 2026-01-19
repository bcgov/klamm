<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\Pages;

use App\Enums\SeedContractMode;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BulkAssignSeedContracts extends Page
{
    protected static string $resource = AnonymousSiebelColumnResource::class;

    protected static string $view = 'filament.fodig.resources.anonymous-siebel-column-resource.pages.bulk-assign-seed-contracts';

    protected static ?string $title = 'Bulk Assign Seed Contracts';

    protected static ?string $navigationLabel = 'Bulk Seed Assignment';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Filter Columns')
                        ->description('Select columns by pattern or criteria')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('filter_database_id')
                                        ->label('Database')
                                        ->searchable()
                                        ->preload()
                                        ->options(function () {
                                            return \App\Models\Anonymizer\AnonymousSiebelDatabase::query()
                                                ->orderBy('database_name')
                                                ->pluck('database_name', 'id');
                                        })
                                        ->live(),
                                    Forms\Components\Select::make('filter_schema_id')
                                        ->label('Schema')
                                        ->searchable()
                                        ->preload()
                                        ->options(function (Get $get) {
                                            $databaseId = $get('filter_database_id');
                                            if (! $databaseId) {
                                                return [];
                                            }
                                            return \App\Models\Anonymizer\AnonymousSiebelSchema::query()
                                                ->where('database_id', $databaseId)
                                                ->orderBy('schema_name')
                                                ->pluck('schema_name', 'id');
                                        })
                                        ->live(),
                                ]),
                            Forms\Components\Select::make('filter_table_id')
                                ->label('Table (Optional)')
                                ->searchable()
                                ->preload()
                                ->options(function (Get $get) {
                                    $schemaId = $get('filter_schema_id');
                                    if (! $schemaId) {
                                        return [];
                                    }
                                    return AnonymousSiebelTable::query()
                                        ->where('schema_id', $schemaId)
                                        ->orderBy('table_name')
                                        ->pluck('table_name', 'id');
                                })
                                ->live(),
                            Forms\Components\TagsInput::make('column_name_patterns')
                                ->label('Column Name Patterns')
                                ->helperText('Enter patterns to match column names (e.g., INTEGRATION_ID, ROW_ID, PHN, SIN). Case-insensitive.')
                                ->placeholder('Add patterns...')
                                ->suggestions([
                                    'INTEGRATION_ID',
                                    'ROW_ID',
                                    'PAR_ROW_ID',
                                    'PHN',
                                    'SIN',
                                    'PHONE',
                                    'EMAIL',
                                ]),
                            Forms\Components\Toggle::make('include_related_columns')
                                ->label('Auto-detect related columns from FK relationships')
                                ->helperText('Use existing related_columns metadata to suggest parent/child dependencies')
                                ->default(true),
                            Forms\Components\Placeholder::make('preview_count')
                                ->label('Matched Columns')
                                ->content(function (Get $get): string {
                                    $count = $this->getMatchedColumns($get)->count();
                                    return $count > 0 ? "{$count} columns matched" : 'No columns matched';
                                })
                                ->live(),
                        ]),
                    Wizard\Step::make('Preview & Assign')
                        ->description('Review matched columns and assign seed roles')
                        ->schema([
                            Forms\Components\Repeater::make('column_assignments')
                                ->label('Column Assignments')
                                ->schema([
                                    Forms\Components\Placeholder::make('column_path')
                                        ->label('Column')
                                        ->content(fn($state, Get $get) => $state ?? '—'),
                                    Forms\Components\Placeholder::make('suggested_mode_display')
                                        ->label('Suggested Role')
                                        ->content(fn($state) => $state ?? 'Not suggested'),
                                    Forms\Components\Select::make('seed_contract_mode')
                                        ->label('Assign Seed Role')
                                        ->options(SeedContractMode::options())
                                        ->searchable()
                                        ->native(false)
                                        ->nullable()
                                        ->helperText('Leave empty to skip this column'),
                                    Forms\Components\Textarea::make('seed_contract_expression')
                                        ->label('Seed Expression (Optional)')
                                        ->rows(2)
                                        ->placeholder('e.g., integration_id, hash(column_name)'),
                                    Forms\Components\Hidden::make('column_id'),
                                ])
                                ->default(function (Get $get) {
                                    return $this->getMatchedColumns($get)
                                        ->map(function (AnonymousSiebelColumn $column) {
                                            $table = $column->table;
                                            $schema = $table?->schema;
                                            $database = $schema?->database;
                                            $path = collect([
                                                $database?->database_name,
                                                $schema?->schema_name,
                                                $table?->table_name,
                                                $column->column_name,
                                            ])->filter()->implode('.');

                                            $suggested = $this->suggestSeedMode($column);

                                            return [
                                                'column_id' => $column->id,
                                                'column_path' => $path,
                                                'suggested_mode_display' => $suggested ? SeedContractMode::tryFrom($suggested)?->label() : null,
                                                'seed_contract_mode' => $suggested,
                                                'seed_contract_expression' => null,
                                            ];
                                        })
                                        ->all();
                                })
                                ->deleteAction(
                                    fn($action) => $action->requiresConfirmation()
                                )
                                ->reorderable(false)
                                ->collapsible()
                                ->itemLabel(fn($state) => $state['column_path'] ?? 'Column')
                                ->columnSpanFull(),
                        ]),
                    Wizard\Step::make('Create Dependencies')
                        ->description('Optionally create parent-child relationships')
                        ->schema([
                            Forms\Components\Toggle::make('auto_create_dependencies')
                                ->label('Auto-create dependencies from related columns')
                                ->helperText('Use existing FK relationships to create seed dependencies where SOURCE columns are found')
                                ->default(false)
                                ->live(),
                            Forms\Components\Placeholder::make('dependency_preview')
                                ->label('Dependency Preview')
                                ->content(function (Get $get): string {
                                    if (! $get('auto_create_dependencies')) {
                                        return 'Disabled. Enable to preview suggested dependencies.';
                                    }

                                    $assignments = $get('column_assignments') ?? [];
                                    $sourceColumns = collect($assignments)
                                        ->filter(fn($a) => ($a['seed_contract_mode'] ?? null) === SeedContractMode::SOURCE->value)
                                        ->pluck('column_id')
                                        ->all();

                                    if (empty($sourceColumns)) {
                                        return 'No SOURCE columns assigned yet.';
                                    }

                                    $consumerColumns = collect($assignments)
                                        ->filter(fn($a) => ($a['seed_contract_mode'] ?? null) === SeedContractMode::CONSUMER->value)
                                        ->pluck('column_id')
                                        ->all();

                                    if (empty($consumerColumns)) {
                                        return 'No CONSUMER columns assigned yet.';
                                    }

                                    $dependencies = $this->findPotentialDependencies($sourceColumns, $consumerColumns);

                                    return $dependencies->isEmpty()
                                        ? 'No automatic dependencies detected from related_columns metadata.'
                                        : $dependencies->count() . ' potential dependencies detected based on table relationships.';
                                })
                                ->columnSpanFull(),
                        ]),
                ])
                    ->submitAction(new \Illuminate\Support\HtmlString('<button type="submit" class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">Apply Assignments</button>')),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $assignments = $data['column_assignments'] ?? [];
        $autoCreateDeps = $data['auto_create_dependencies'] ?? false;

        $updated = 0;
        $dependenciesCreated = 0;

        foreach ($assignments as $assignment) {
            $columnId = $assignment['column_id'] ?? null;
            $mode = $assignment['seed_contract_mode'] ?? null;
            $expression = $assignment['seed_contract_expression'] ?? null;

            if (! $columnId || ! $mode) {
                continue;
            }

            $column = AnonymousSiebelColumn::find($columnId);
            if (! $column) {
                continue;
            }

            $column->update([
                'seed_contract_mode' => $mode,
                'seed_contract_expression' => $expression,
            ]);

            $updated++;
        }

        if ($autoCreateDeps) {
            $sourceColumns = collect($assignments)
                ->filter(fn($a) => ($a['seed_contract_mode'] ?? null) === SeedContractMode::SOURCE->value)
                ->pluck('column_id')
                ->all();

            $consumerColumns = collect($assignments)
                ->filter(fn($a) => ($a['seed_contract_mode'] ?? null) === SeedContractMode::CONSUMER->value)
                ->pluck('column_id')
                ->all();

            $dependencies = $this->findPotentialDependencies($sourceColumns, $consumerColumns);

            foreach ($dependencies as $dep) {
                $child = AnonymousSiebelColumn::find($dep['child_id']);
                if ($child) {
                    $child->parentColumns()->syncWithoutDetaching([
                        $dep['parent_id'] => [
                            'seed_bundle_label' => $dep['label'] ?? null,
                            'is_seed_mandatory' => true,
                        ],
                    ]);
                    $dependenciesCreated++;
                }
            }
        }

        Notification::make()
            ->success()
            ->title('Seed contracts assigned')
            ->body("Updated {$updated} columns" . ($dependenciesCreated > 0 ? " and created {$dependenciesCreated} dependencies" : ''))
            ->send();

        $this->redirect(AnonymousSiebelColumnResource::getUrl('index'));
    }

    protected function getMatchedColumns(Get $get): Collection
    {
        $databaseId = $get('filter_database_id');
        $schemaId = $get('filter_schema_id');
        $tableId = $get('filter_table_id');
        $patterns = $get('column_name_patterns') ?? [];

        if (! $databaseId && ! $schemaId && empty($patterns)) {
            return collect();
        }

        $query = AnonymousSiebelColumn::query()
            ->with(['table.schema.database']);

        if ($databaseId) {
            $query->whereHas('table.schema', function (Builder $q) use ($databaseId) {
                $q->where('database_id', $databaseId);
            });
        }

        if ($schemaId) {
            $query->whereHas('table', function (Builder $q) use ($schemaId) {
                $q->where('schema_id', $schemaId);
            });
        }

        if ($tableId) {
            $query->where('table_id', $tableId);
        }

        if (! empty($patterns)) {
            $query->where(function (Builder $q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhere('column_name', 'ilike', "%{$pattern}%");
                }
            });
        }

        return $query->limit(100)->get();
    }

    protected function suggestSeedMode(AnonymousSiebelColumn $column): ?string
    {
        $name = Str::upper($column->column_name);

        // Suggest SOURCE for integration/row IDs
        if (Str::contains($name, ['INTEGRATION_ID', 'ROW_ID', 'PAR_ROW_ID'])) {
            return SeedContractMode::SOURCE->value;
        }

        // Suggest CONSUMER for common PII fields
        if (Str::contains($name, ['PHN', 'SIN', 'PHONE', 'EMAIL', 'SSN'])) {
            return SeedContractMode::CONSUMER->value;
        }

        return null;
    }

    protected function findPotentialDependencies(array $sourceColumnIds, array $consumerColumnIds): Collection
    {
        if (empty($sourceColumnIds) || empty($consumerColumnIds)) {
            return collect();
        }

        $sources = AnonymousSiebelColumn::query()
            ->with(['table'])
            ->whereIn('id', $sourceColumnIds)
            ->get()
            ->keyBy('id');

        $consumers = AnonymousSiebelColumn::query()
            ->with(['table'])
            ->whereIn('id', $consumerColumnIds)
            ->get();

        $dependencies = collect();

        foreach ($consumers as $consumer) {
            $consumerTable = $consumer->table;
            if (! $consumerTable) {
                continue;
            }

            // Look for SOURCE columns in the same table
            $sameTableSources = $sources->filter(
                fn($source) => $source->table_id === $consumer->table_id
            );

            foreach ($sameTableSources as $source) {
                $dependencies->push([
                    'parent_id' => $source->id,
                    'child_id' => $consumer->id,
                    'label' => Str::title($source->column_name) . ' Seed',
                ]);
            }

            // Check related_columns for FK hints
            $relatedColumns = $consumer->related_columns ?? [];
            if (is_string($relatedColumns)) {
                $relatedColumns = json_decode($relatedColumns, true) ?? [];
            }

            foreach ($sources as $source) {
                $sourceTableName = $source->table?->table_name;
                if (! $sourceTableName) {
                    continue;
                }

                // Look for references to source table in related_columns
                foreach ($relatedColumns as $related) {
                    if (Str::contains($related, $sourceTableName)) {
                        $dependencies->push([
                            'parent_id' => $source->id,
                            'child_id' => $consumer->id,
                            'label' => 'FK to ' . $sourceTableName,
                        ]);
                    }
                }
            }
        }

        return $dependencies->unique(function ($dep) {
            return $dep['parent_id'] . '-' . $dep['child_id'];
        })->values();
    }
}
