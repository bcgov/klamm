<?php

namespace App\Filament\Fodig\Resources\AnonymizationPackageResource\RelationManagers;

use App\Filament\Fodig\Resources\AnonymizationMethodResource;
use App\Models\Anonymizer\AnonymizationMethods;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'methods';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Methods using this package';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Methods using this package')
            ->description('Anonymization methods that depend on this SQL package.')
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->orderBy('anonymization_methods.name')
                    ->orderBy('anonymization_methods.version');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Method')
                    ->sortable()
                    ->searchable()
                    ->url(fn(AnonymizationMethods $record) => AnonymizationMethodResource::getUrl('view', ['record' => $record->id]))
                    ->openUrlInNewTab(),
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
                    ->formatStateUsing(fn(mixed $state, AnonymizationMethods $record) => $record->categorySummary() ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Columns')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_sql')
                    ->label('SQL')
                    ->state(fn(AnonymizationMethods $record) => filled($record->sql_block))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\Action::make('attachMethods')
                    ->label('Attach methods')
                    ->modalHeading('Attach methods')
                    ->modalSubmitActionLabel('Attach selected')
                    ->form([
                        Select::make('method_ids')
                            ->label('Methods')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->options(fn() => $this->methodSelectOptions(limit: 25))
                            ->getSearchResultsUsing(fn(string $search) => $this->methodSelectOptions(search: $search))
                            ->getOptionLabelsUsing(fn(array $values) => $this->methodSelectOptions(ids: $values))
                            ->helperText('Search by method name or category.'),
                    ])
                    ->action(function (array $data): void {
                        $methodIds = collect($data['method_ids'] ?? [])
                            ->filter()
                            ->all();

                        if ($methodIds === []) {
                            return;
                        }

                        $this->getRelationship()->syncWithoutDetaching($methodIds);
                    }),
                Tables\Actions\Action::make('createMethod')
                    ->label('Create method')
                    ->icon('heroicon-o-plus')
                    ->url(function (): string {
                        $packageId = $this->getOwnerRecord()?->getKey();
                        $returnTo = url()->current();

                        $baseUrl = AnonymizationMethodResource::getUrl('create');

                        return $baseUrl
                            . '?attach_package_id=' . urlencode((string) $packageId)
                            . '&return_to=' . urlencode($returnTo);
                    })
                    ->openUrlInNewTab(),
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

    private function methodSelectOptions(?string $search = null, ?array $ids = null, int $limit = 25): array
    {
        $query = AnonymizationMethods::query();

        $packageId = $this->getOwnerRecord()?->getKey();

        if ($ids) {
            $query->whereIn('anonymization_methods.id', $ids);
        } elseif ($packageId) {
            $query->whereDoesntHave('packages', fn(Builder $relationshipQuery) => $relationshipQuery
                ->where('anonymization_packages.id', $packageId));
        }

        if ($search !== null) {
            $term = '%' . strtolower($search) . '%';

            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->whereRaw('LOWER(anonymization_methods.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(anonymization_methods.category) LIKE ?', [$term]);
            });
        }

        if (! $ids) {
            $query
                ->orderBy('anonymization_methods.name')
                ->orderBy('anonymization_methods.version')
                ->limit($limit);
        }

        return $query
            ->get()
            ->mapWithKeys(fn(AnonymizationMethods $method) => [
                $method->id => $this->methodLabel($method),
            ])
            ->all();
    }

    private function methodLabel(AnonymizationMethods $method): string
    {
        $parts = array_filter([
            $method->name,
            $method->version ? 'v' . $method->version : null,
            $method->is_current ? 'current' : null,
        ]);

        return $parts !== [] ? implode(' • ', $parts) : (string) $method->name;
    }
}
