<?php

namespace App\Filament\Fodig\RelationManagers;

use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Rmsramos\Activitylog\Resources\ActivitylogResource;
use Spatie\Activitylog\Models\Activity;

class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activity Log';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return method_exists($ownerRecord, 'activities')
            && method_exists($ownerRecord, 'getMorphClass')
            && method_exists($ownerRecord, 'getKey')
            && method_exists($ownerRecord, 'activityLogName');
    }

    protected function getTableQuery(): ?Builder
    {
        $owner = $this->getOwnerRecord();

        return Activity::query()
            ->where('log_name', $owner::activityLogName())
            ->where('subject_type', $owner->getMorphClass())
            ->where('subject_id', $owner->getKey())
            ->latest('activity_log.created_at');
    }

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
