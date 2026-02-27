<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\RelationManagers;

use App\Filament\Fodig\Resources\AnonymizationRuleResource;
use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\Anonymizer\AnonymizationRule;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ColumnsRelationManager extends RelationManager
{
    protected static string $relationship = 'columns';

    protected static ?string $recordTitleAttribute = 'column_name';

    protected static ?string $title = 'Columns using this method';

    /**
     * Columns are now connected to methods through rules.
     * This relation manager shows columns that reach this method
     * via anonymization_rule_column → anonymization_rule_methods.
     */
    public function table(Table $table): Table
    {
        return $table
            ->heading('Columns using this method (via rules)')
            ->description('Columns connected to this method through anonymization rules. To change column associations, edit the rule or the column\'s rule assignment.')
            ->query(function (): Builder {
                $methodId = $this->getOwnerRecord()->getKey();

                return AnonymousSiebelColumn::query()
                    ->select('anonymous_siebel_columns.*')
                    ->addSelect([
                        'rule_name' => DB::table('anonymization_rules')
                            ->select('anonymization_rules.name')
                            ->join('anonymization_rule_column', 'anonymization_rules.id', '=', 'anonymization_rule_column.rule_id')
                            ->whereColumn('anonymization_rule_column.column_id', 'anonymous_siebel_columns.id')
                            ->join('anonymization_rule_methods', 'anonymization_rules.id', '=', 'anonymization_rule_methods.rule_id')
                            ->where('anonymization_rule_methods.method_id', $methodId)
                            ->limit(1),
                        'rule_id' => DB::table('anonymization_rules')
                            ->select('anonymization_rules.id')
                            ->join('anonymization_rule_column', 'anonymization_rules.id', '=', 'anonymization_rule_column.rule_id')
                            ->whereColumn('anonymization_rule_column.column_id', 'anonymous_siebel_columns.id')
                            ->join('anonymization_rule_methods', 'anonymization_rules.id', '=', 'anonymization_rule_methods.rule_id')
                            ->where('anonymization_rule_methods.method_id', $methodId)
                            ->limit(1),
                        'is_default_for_rule' => DB::table('anonymization_rule_methods')
                            ->select('anonymization_rule_methods.is_default')
                            ->join('anonymization_rule_column', 'anonymization_rule_methods.rule_id', '=', 'anonymization_rule_column.rule_id')
                            ->whereColumn('anonymization_rule_column.column_id', 'anonymous_siebel_columns.id')
                            ->where('anonymization_rule_methods.method_id', $methodId)
                            ->limit(1),
                    ])
                    ->whereExists(function ($query) use ($methodId) {
                        $query->select(DB::raw(1))
                            ->from('anonymization_rule_column')
                            ->join('anonymization_rule_methods', 'anonymization_rule_column.rule_id', '=', 'anonymization_rule_methods.rule_id')
                            ->whereColumn('anonymization_rule_column.column_id', 'anonymous_siebel_columns.id')
                            ->where('anonymization_rule_methods.method_id', $methodId);
                    })
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
                Tables\Columns\TextColumn::make('rule_name')
                    ->label('Rule')
                    ->url(fn(AnonymousSiebelColumn $record) => $record->rule_id
                        ? AnonymizationRuleResource::getUrl('view', ['record' => $record->rule_id])
                        : null)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_default_for_rule')
                    ->label('Default')
                    ->boolean()
                    ->tooltip('This method is the default for the column\'s rule'),
                Tables\Columns\IconColumn::make('anonymization_required')
                    ->label('Required')
                    ->boolean()
                    ->tooltip('Marked as requiring anonymization'),
                Tables\Columns\TextColumn::make('seed_contract_summary')
                    ->label('Seed contract')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
