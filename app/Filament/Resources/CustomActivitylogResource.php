<?php

namespace App\Filament\Resources;

use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Rmsramos\Activitylog\Resources\ActivitylogResource;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

// Extend the ActivitylogResource plugin to customize the table columns and filters
class CustomActivitylogResource extends ActivitylogResource
{
    protected static array $enabledColumns = [
        'log_name' => true,
        'event' => true,
        'description' => true,
        'subject_type' => true,
        'properties' => true,
        'causer_name' => true,
        'created_at' => true,
    ];

    protected static array $enabledFilters = [
        'date' => true,
        'event' => true,
        'causer_name' => true,
        'log_name' => true,
    ];


    public static function withColumns(array $columns): void
    {
        foreach (static::$enabledColumns as $key => $value) {
            static::$enabledColumns[$key] = false;
        }

        foreach ($columns as $column) {
            if (array_key_exists($column, static::$enabledColumns)) {
                static::$enabledColumns[$column] = true;
            }
        }
    }

    // Set filters
    public static function withFilters(array $filters): void
    {
        // Reset all to false first
        foreach (static::$enabledFilters as $key => $value) {
            static::$enabledFilters[$key] = false;
        }

        // Enable only the specified filters
        foreach ($filters as $filter) {
            if (array_key_exists($filter, static::$enabledFilters)) {
                static::$enabledFilters[$filter] = true;
            }
        }
    }

    // Reset the configuration to default
    public static function resetConfiguration(): void
    {
        foreach (static::$enabledColumns as $key => $value) {
            static::$enabledColumns[$key] = true;
        }

        foreach (static::$enabledFilters as $key => $value) {
            static::$enabledFilters[$key] = true;
        }
    }

    public static function getSubjectTypeColumnComponent(): Column
    {
        return TextColumn::make('subject_type')
            ->label('Subject')
            ->formatStateUsing(function ($state, Activity $record) {
                if (!$state) {
                    return '-';
                }
                return Str::of($state)->afterLast('\\')->headline() . ' # ' . $record->subject_id;
            })
            ->toggleable()
            ->searchable(['activity_log.subject_type', 'activity_log.subject_id'])
            ->sortable('activity_log.subject_type');
    }

    public static function getDescriptionColumnComponent(): Column
    {
        return TextColumn::make('description')
            ->label('Description')
            ->searchable('activity_log.description')
            ->sortable('activity_log.description')
            ->limit(50)
            ->tooltip(function ($record): ?string {
                $description = optional($record)->description ?? '';
                return Str::length($description) > 50
                    ? $description
                    : null;
            });
    }

    public static function getCauserNameFilterComponent(): SelectFilter
    {
        return SelectFilter::make('causer_id')
            ->label('Causer')
            ->multiple()
            ->options(function () {
                return \Spatie\Activitylog\Models\Activity::query()
                    ->select('causer_id')
                    ->distinct()
                    ->whereNotNull('causer_id')
                    ->get()
                    ->mapWithKeys(function ($activity) {
                        $user = User::find($activity->causer_id);
                        return $user ? [$user->id => $user->name] : [];
                    });
            })
            ->query(function (Builder $query, array $data): Builder {
                return $query->when(
                    $data['values'],
                    fn(Builder $query, $values): Builder => $query->whereIn('causer_id', $values)
                );
            })
            ->searchable()
            ->preload();
    }

    public static function getStandardColumns(): array
    {
        $columns = [];

        if (static::$enabledColumns['log_name']) {
            $columns[] = ActivitylogResource::getLogNameColumnComponent()
                ->label('Log Type')
                ->toggleable();
        }

        if (static::$enabledColumns['event']) {
            $columns[] = static::getEventColumnComponent()
                ->searchable('activity_log.event');
        }

        if (static::$enabledColumns['description']) {
            $columns[] = static::getDescriptionColumnComponent();
        }

        if (static::$enabledColumns['subject_type']) {
            $columns[] = static::getSubjectTypeColumnComponent();
        }

        if (static::$enabledColumns['properties']) {
            $columns[] = static::getPropertiesColumnComponent()
                ->label('Properties')
                ->searchable('activity_log.properties')
                ->sortable('activity_log.properties');
        }

        if (static::$enabledColumns['causer_name']) {
            $columns[] = ActivitylogResource::getCauserNameColumnComponent()
                ->label('User')
                ->formatStateUsing(function ($state, Activity $record) {
                    if (!$record->causer_id) {
                        return '-';
                    }

                    $user = User::find($record->causer_id);
                    return $user ? $user->name : '-';
                })
                ->toggleable()
                ->searchable(['users.name'])
                ->sortable();
        }

        if (static::$enabledColumns['created_at']) {
            $columns[] = static::getCreatedAtColumnComponent()
                ->sortable('activity_log.created_at')
                ->searchable(false);
        }

        return $columns;
    }

    public static function getStandardFilters(): array
    {
        $filters = [];

        if (static::$enabledFilters['date']) {
            $filters[] = static::getDateFilterComponent()->query(function ($query, array $data) {
                if ($data['created_from']) {
                    $query->where('activity_log.created_at', '>=', $data['created_from']);
                }
                if ($data['created_until']) {
                    $query->where('activity_log.created_at', '<=', $data['created_until']);
                }
            });
        }

        if (static::$enabledFilters['event']) {
            $filters[] = static::getEventFilterComponent();
        }

        if (static::$enabledFilters['causer_name']) {
            $filters[] = static::getCauserNameFilterComponent();
        }

        if (static::$enabledFilters['log_name']) {
            $filters[] = SelectFilter::make('log_name')
                ->label('Log Type')
                ->multiple()
                ->options(function () {
                    return \Spatie\Activitylog\Models\Activity::query()
                        ->select('log_name')
                        ->distinct()
                        ->get()
                        ->pluck('log_name', 'log_name');
                })
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        !empty($data['values']),
                        fn(Builder $query, $values): Builder => $query->whereIn('activity_log.log_name', $data['values'])
                    );
                })
                ->searchable()
                ->preload();
        }

        return $filters;
    }

    public static function configureStandardTable(Table $table): Table
    {
        return $table
            ->searchable()
            ->columns(static::getStandardColumns())
            ->filters(static::getStandardFilters())
            ->defaultSort('activity_log.created_at', 'desc');
    }

    // Add additional columns and filters to the table
    public static function table(Table $table): Table
    {
        $parentTable = parent::table($table);
        $columns = $parentTable->getColumns();
        $newColumns = collect($columns)->splice(0, 2)
            ->merge(collect($columns)->splice(2))
            ->toArray();

        return $parentTable
            ->columns($newColumns)
            ->searchable()
            ->filters(static::getStandardFilters())
            ->defaultSort('activity_log.created_at', 'desc');
    }
}
