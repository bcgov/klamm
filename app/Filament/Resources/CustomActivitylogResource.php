<?php

namespace App\Filament\Resources;

use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Rmsramos\Activitylog\Resources\ActivitylogResource;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;

// Extend the ActivitylogResource plugin to customize the table columns and filters
class CustomActivitylogResource extends ActivitylogResource
{
    public static function getSubjectTypeColumnComponent(): Column
    {
        return parent::getSubjectTypeColumnComponent()
            ->searchable(['activity_log.subject_type', 'activity_log.subject_id'])
            ->visible();
    }

    public static function getDescriptionColumnComponent(): Column
    {
        return TextColumn::make('description')
            ->label('Description')
            ->searchable('activity_log.description')
            ->limit(50);
    }

    // Add custom columns and filters to the table
    public static function table(Table $table): Table
    {
        $parentTable = parent::table($table);
        $columns = $parentTable->getColumns();
        $newColumns = collect($columns)->splice(0, 2)
            ->push(static::getDescriptionColumnComponent())
            ->merge(collect($columns)->splice(2))
            ->toArray();

        return $parentTable
            ->columns($newColumns)
            ->searchable()
            ->filters([
                static::getDateFilterComponent()->query(function ($query, array $data) {
                    if ($data['created_from']) {
                        $query->where('activity_log.created_at', '>=', $data['created_from']);
                    }
                    if ($data['created_until']) {
                        $query->where('activity_log.created_at', '<=', $data['created_until']);
                    }
                }),
                static::getEventFilterComponent(),
                SelectFilter::make('subject_type')
                    ->label('Subject Type')
                    ->options(function () {
                        return Activity::distinct()
                            ->pluck('subject_type')
                            ->map(fn($type) => Str::of($type)->afterLast('\\')->headline())
                            ->filter()
                            ->toArray();
                    }),
            ])
            ->defaultSort('activity_log.created_at', 'desc');
    }
}
