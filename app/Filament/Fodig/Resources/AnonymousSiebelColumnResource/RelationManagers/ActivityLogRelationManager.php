<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\RelationManagers;

use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;
use Spatie\Activitylog\Models\Activity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Forms\Form;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Rmsramos\Activitylog\Resources\ActivitylogResource;


class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activity Log';

    protected function getTableQuery(): ?Builder
    {
        $owner = $this->getOwnerRecord();

        return Activity::query()
            ->where('log_name', $owner::activityLogName())
            ->where('subject_type', $owner->getMorphClass())
            ->where('subject_id', $owner->getKey())
            ->latest('activity_log.created_at');
    }

    // public function table(Table $table): Table
    // {
    //     CustomActivitylogResource::resetConfiguration();
    //     CustomActivitylogResource::withColumns(['event', 'description', 'causer_name', 'properties', 'created_at']);
    //     CustomActivitylogResource::withFilters(['date', 'event', 'causer_name']);

    //     $configured = CustomActivitylogResource::configureStandardTable(
    //         $table
    //             ->heading('Activity Log')
    //             ->deferLoading()
    //     );

    //     CustomActivitylogResource::resetConfiguration();

    //     return $configured;
    // }
    public function form(Form $form): Form
    {
        return ActivitylogResource::form($form);
    }

    public function table(Table $table): Table
    {
        CustomActivitylogResource::withColumns(['event', 'description', 'causer_name', 'created_at', 'subject_type', 'properties']);
        CustomActivitylogResource::withFilters(['date', 'event', 'causer_name']);
        $columns = CustomActivitylogResource::getStandardColumns();

        return $table
            ->heading(ActivitylogPlugin::get()->getPluralLabel())
            ->columns($columns)
            ->deferLoading()
            ->filters(CustomActivitylogResource::getStandardFilters())
            ->defaultSort('activity_log.created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ]);
    }
}
