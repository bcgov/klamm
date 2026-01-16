<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\RelationManagers;

use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ColumnsRelationManager extends RelationManager
{
    protected static string $relationship = 'columns';

    protected static ?string $recordTitleAttribute = 'column_name';

    protected static ?string $title = 'Columns using this method';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Columns using this method')
            ->description('Catalog columns currently linked to this anonymization method.')
            ->modifyQueryUsing(function (Builder $query) {
                // Override Filament's automatic distinct behavior to avoid SQL errors.
                $query->getQuery()->distinct = false;

                return $query
                    ->select([
                        'anonymous_siebel_columns.id',
                        'anonymous_siebel_columns.column_name',
                        'anonymous_siebel_columns.table_id',
                        'anonymous_siebel_columns.anonymization_required',
                        'anonymous_siebel_columns.seed_contract_mode',
                        'anonymous_siebel_columns.seed_contract_expression',
                        'anonymous_siebel_columns.seed_contract_notes',
                    ])
                    ->with(['table.schema.database'])
                    ->orderBy('anonymous_siebel_columns.column_name');
            })
            ->columns([
                Tables\Columns\TextColumn::make('column_name')
                    ->label('Column')
                    ->sortable()
                    ->searchable()
                    ->url(fn(AnonymousSiebelColumn $record) => AnonymousSiebelColumnResource::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('table.table_name')
                    ->label('Table')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('table.schema.schema_name')
                    ->label('Schema')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('table.schema.database.database_name')
                    ->label('Database')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('anonymization_required')
                    ->label('Required')
                    ->boolean()
                    ->tooltip('Marked as requiring anonymization'),
                Tables\Columns\TextColumn::make('seed_contract_summary')
                    ->label('Seed contract')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\Action::make('attachColumns')
                    ->label('Attach columns')
                    ->modalHeading('Attach columns')
                    ->modalSubmitActionLabel('Attach selected')
                    ->form([
                        Select::make('column_ids')
                            ->label('Columns')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->options(fn() => $this->columnSelectOptions(limit: 25))
                            ->getSearchResultsUsing(fn(string $search) => $this->columnSelectOptions(search: $search))
                            ->getOptionLabelsUsing(fn(array $values) => $this->columnSelectOptions(ids: $values))
                            ->helperText('Search by database, schema, table, or column name.'),
                    ])
                    ->action(function (array $data): void {
                        $columnIds = collect($data['column_ids'] ?? [])
                            ->filter()
                            ->all();

                        if ($columnIds === []) {
                            return;
                        }

                        $this->getRelationship()->syncWithoutDetaching($columnIds);
                    }),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Remove'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }

    private function columnSelectOptions(?string $search = null, ?array $ids = null, int $limit = 25): array
    {
        $query = AnonymousSiebelColumn::query()
            ->with(['table.schema.database']);

        $methodId = $this->getOwnerRecord()?->getKey();

        if ($ids) {
            $query->whereIn('anonymous_siebel_columns.id', $ids);
        } elseif ($methodId) {
            $query->whereDoesntHave('anonymizationMethods', fn(Builder $relationshipQuery) => $relationshipQuery
                ->where('anonymization_methods.id', $methodId));
        }

        if ($search !== null) {
            $this->applyColumnSearch($query, $search);
        }

        if (! $ids) {
            $query
                ->orderBy('anonymous_siebel_columns.column_name')
                ->limit($limit);
        }

        return $query
            ->get()
            ->mapWithKeys(fn(AnonymousSiebelColumn $column) => [
                $column->id => $this->columnPath($column),
            ])
            ->all();
    }

    private function applyColumnSearch(Builder $query, string $search): void
    {
        $term = '%' . strtolower($search) . '%';

        $query->where(function (Builder $builder) use ($term) {
            $builder
                ->whereRaw('LOWER(anonymous_siebel_columns.column_name) LIKE ?', [$term])
                ->orWhereHas('table', fn(Builder $tableQuery) => $tableQuery->whereRaw('LOWER(table_name) LIKE ?', [$term]))
                ->orWhereHas('table.schema', fn(Builder $schemaQuery) => $schemaQuery->whereRaw('LOWER(schema_name) LIKE ?', [$term]))
                ->orWhereHas('table.schema.database', fn(Builder $databaseQuery) => $databaseQuery->whereRaw('LOWER(database_name) LIKE ?', [$term]));
        });
    }

    private function columnPath(AnonymousSiebelColumn $column): string
    {
        $table = $column->table;
        $schema = $table?->schema;
        $database = $schema?->database;

        return collect([
            $database?->database_name,
            $schema?->schema_name,
            $table?->table_name,
            $column->column_name,
        ])->filter()->implode('.');
    }
}
