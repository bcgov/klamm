<?php

namespace App\Filament\Admin\Resources\UserResource\RelationManagers;

use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Rmsramos\Activitylog\Resources\ActivitylogResource;
use Illuminate\Database\Eloquent\Model;

class UserLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return ActivitylogResource::form($form);
    }

    public function table(Table $table): Table
    {
        CustomActivitylogResource::withColumns(['log_name', 'event', 'description', 'created_at', 'subject_type', 'properties']);
        CustomActivitylogResource::withFilters(['log_name', 'date', 'event']);
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
