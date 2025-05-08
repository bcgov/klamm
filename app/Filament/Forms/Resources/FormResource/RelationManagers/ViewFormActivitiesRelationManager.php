<?php

namespace App\Filament\Forms\Resources\FormResource\RelationManagers;

use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Rmsramos\Activitylog\Resources\ActivitylogResource;
use Illuminate\Database\Eloquent\Model;

class ViewFormActivitiesRelationManager extends RelationManager
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
        CustomActivitylogResource::withColumns(['event', 'description', 'causer_name', 'created_at', 'subject_type', 'properties']);
        CustomActivitylogResource::withFilters(['date', 'event', 'causer_name']);
        $columns = CustomActivitylogResource::getStandardColumns();

        return $table
            ->heading(ActivitylogPlugin::get()->getPluralLabel())
            ->columns($columns)
            ->filters(CustomActivitylogResource::getStandardFilters())
            ->defaultSort('activity_log.created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ]);
    }
}
