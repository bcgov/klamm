<?php

namespace App\Filament\Fodig\Resources\AnonymizationMethodResource\RelationManagers;

use App\Filament\Fodig\Resources\AnonymizationRuleResource;
use App\Models\Anonymizer\AnonymizationRule;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Rules using this method';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Rules using this method')
            ->description('Anonymization rules that reference this method, either as the default or under a strategy.')
            ->modifyQueryUsing(function (Builder $query) {
                $query->getQuery()->distinct = false;

                return $query
                    ->select([
                        'anonymization_rules.id',
                        'anonymization_rules.name',
                        'anonymization_rules.description',
                    ])
                    ->orderBy('anonymization_rules.name');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rule')
                    ->sortable()
                    ->searchable()
                    ->url(fn(AnonymizationRule $record) => AnonymizationRuleResource::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('pivot.is_default')
                    ->label('Default')
                    ->boolean()
                    ->tooltip('This method is the default for this rule'),
                Tables\Columns\TextColumn::make('pivot.strategy')
                    ->label('Strategy')
                    ->placeholder('—'),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }
}
