<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Rmsramos\Activitylog\Resources\ActivitylogResource;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class CustomActivitylogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'description';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return static::$title ?? (string) str(ActivitylogPlugin::get()->getPluralLabel())
            ->kebab()
            ->replace('-', ' ')
            ->headline();
    }

    public function form(Form $form): Form
    {
        return ActivitylogResource::form($form);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(ActivitylogPlugin::get()->getPluralLabel())
            ->searchable()
            ->columns([
                ActivitylogResource::getEventColumnComponent()
                    ->searchable('activity_log.event'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable('activity_log.description')
                    ->sortable('activity_log.description')
                    ->limit(50),

                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(function ($state, Model $record) {
                        if (!$state) {
                            return '-';
                        }
                        return Str::of($state)->afterLast('\\')->headline() . ' # ' . $record->subject_id;
                    })
                    ->searchable(['activity_log.subject_type', 'activity_log.subject_id']),

                ActivitylogResource::getPropertiesColumnComponent()
                    ->label('Properties')
                    ->searchable('activity_log.properties')
                    ->sortable('activity_log.properties'),

                ActivitylogResource::getCauserNameColumnComponent(),

                ActivitylogResource::getCreatedAtColumnComponent()
                    ->sortable('activity_log.created_at')
                    ->searchable(false),
            ])
            ->filters([
                ActivitylogResource::getDateFilterComponent()->query(function ($query, array $data) {
                    if ($data['created_from']) {
                        $query->where('activity_log.created_at', '>=', $data['created_from']);
                    }
                    if ($data['created_until']) {
                        $query->where('activity_log.created_at', '<=', $data['created_until']);
                    }
                }),
                ActivitylogResource::getEventFilterComponent(),
                SelectFilter::make('causer_id')
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
                    ->preload()
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('activity_log.created_at', 'desc');
    }
}
