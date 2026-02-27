<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymizationRuleResource\Pages;
use App\Filament\Fodig\Resources\AnonymizationRuleResource\RelationManagers;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\Anonymizer\AnonymizationRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class AnonymizationRuleResource extends Resource
{
    protected static ?string $model = AnonymizationRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Rules';

    protected static ?int $navigationSort = 55;

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
                Forms\Components\Section::make('Rule Details')
                    ->description('Define a reusable anonymization rule that groups methods under strategies. Assign this rule to columns to control which masking approach is used per job.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Short, descriptive name for this anonymization rule.'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Describe when and why this rule should be applied.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Method Assignments')
                    ->description('Attach one or more methods to this rule. Mark exactly one as the default. Others should have a strategy label so jobs can select which variant to use.')
                    ->schema([
                        Forms\Components\Repeater::make('method_assignments')
                            ->label('Methods')
                            ->schema([
                                Forms\Components\Select::make('method_id')
                                    ->label('Method')
                                    ->options(fn() => AnonymizationMethods::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),
                                Forms\Components\Toggle::make('is_default')
                                    ->label('Default')
                                    ->helperText('Only one method should be the default.')
                                    ->default(false)
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('strategy')
                                    ->label('Strategy')
                                    ->maxLength(100)
                                    ->placeholder('e.g. aggressive, development, light')
                                    ->helperText('Leave blank for the default method. Non-default methods should have a strategy label.')
                                    ->datalist(fn() => AnonymizationRule::availableStrategies())
                                    ->columnSpan(2),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->addActionLabel('Add method')
                            ->reorderable(false)
                            ->columnSpanFull()
                            ->afterStateHydrated(function (Forms\Components\Repeater $component, ?AnonymizationRule $record): void {
                                if (! $record) {
                                    return;
                                }

                                $items = $record->methods->map(fn(AnonymizationMethods $method) => [
                                    'method_id' => $method->getKey(),
                                    'is_default' => (bool) $method->pivot->is_default,
                                    'strategy' => $method->pivot->strategy,
                                ])->values()->all();

                                $component->state($items);
                            })
                            ->dehydrated(true),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Record Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn(?AnonymizationRule $record) => optional($record?->created_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Updated')
                            ->content(fn(?AnonymizationRule $record) => optional($record?->updated_at)?->toDayDateTimeString() ?? '—'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->withCount(['methods', 'columns']))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(80)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('default_method_name')
                    ->label('Default method')
                    ->getStateUsing(fn(AnonymizationRule $record) => $record->defaultMethod()?->name ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('strategies_summary')
                    ->label('Strategies')
                    ->getStateUsing(function (AnonymizationRule $record) {
                        $strategies = $record->strategies();

                        return $strategies !== [] ? implode(', ', $strategies) : '—';
                    })
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('methods_count')
                    ->label('Methods')
                    ->sortable(),
                Tables\Columns\TextColumn::make('columns_count')
                    ->label('Columns')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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

                        $ids = array_values(array_filter(array_map(fn($v) => is_numeric($v) ? (int) $v : null, $values)));

                        if ($ids === []) {
                            return $query;
                        }

                        return $query->whereHas('methods', fn(Builder $b) => $b->whereIn('anonymization_methods.id', $ids));
                    }),
                Tables\Filters\SelectFilter::make('strategy')
                    ->label('Strategy')
                    ->options(fn() => collect(AnonymizationRule::availableStrategies())
                        ->mapWithKeys(fn(string $s) => [$s => Str::headline($s)])
                        ->all())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || $value === '') {
                            return $query;
                        }

                        return $query->whereHas('methods', fn(Builder $b) => $b->where('anonymization_rule_methods.strategy', $value));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
                Section::make('Rule Summary')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Rule')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('description')
                            ->placeholder('No description provided.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Method Assignments')
                    ->schema([
                        RepeatableEntry::make('methods')
                            ->label('Methods')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Method')
                                    ->weight('medium'),
                                TextEntry::make('pivot.is_default')
                                    ->label('Default')
                                    ->formatStateUsing(fn($state) => $state ? 'Yes' : '—')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'gray'),
                                TextEntry::make('pivot.strategy')
                                    ->label('Strategy')
                                    ->placeholder('—'),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Usage')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('methods_count')
                                    ->label('Methods assigned'),
                                TextEntry::make('columns_count')
                                    ->label('Columns using this rule'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->label('Created'),
                                TextEntry::make('updated_at')
                                    ->dateTime()
                                    ->label('Updated'),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnonymizationRules::route('/'),
            'create' => Pages\CreateAnonymizationRule::route('/create'),
            'view' => Pages\ViewAnonymizationRule::route('/{record}'),
            'edit' => Pages\EditAnonymizationRule::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ColumnsRelationManager::class,
            \App\Filament\Fodig\RelationManagers\ActivityLogRelationManager::class,
        ];
    }

    /**
     * Normalize pivot data: ensure strategy is null when is_default is true,
     * and set a sensible default for unfilled strategy fields.
     */
    protected static function normalizeMethodPivotData(array $data): array
    {
        if (! empty($data['is_default'])) {
            $data['strategy'] = null;
        }

        if (isset($data['strategy']) && trim((string) $data['strategy']) === '') {
            $data['strategy'] = null;
        }

        return $data;
    }
}
