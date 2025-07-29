<?php

namespace App\Filament\Forms\Resources\FormInterfaceResource\Pages;

use App\Filament\Forms\Resources\FormInterfaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use App\Filament\Forms\Resources\FormInterfaceResource\RelationManagers\FormVersionsRelationManager;

class ViewFormInterface extends ViewRecord
{
    protected static string $resource = FormInterfaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected const DEFAULT_RELATION_MANAGERS = [
        FormVersionsRelationManager::class,
    ];

    public function getRelationManagers(): array
    {
        if (Gate::allows('admin') || Gate::allows('form-developer')) {
            return self::DEFAULT_RELATION_MANAGERS;
        }
        if ($this->hasBusinessAreaAccess()) {
            $formVersion = $this->getRecord();

            if ($this->hasAccessToFormVersion($formVersion)) {
                return self::DEFAULT_RELATION_MANAGERS;
            }
        }

        return [];
    }
}
