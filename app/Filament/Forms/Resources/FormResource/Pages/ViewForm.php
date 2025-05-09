<?php

namespace App\Filament\Forms\Resources\FormResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Forms\Resources\FormResource\Pages;
use App\Filament\Forms\Resources\FormResource\RelationManagers\FormVersionRelationManager;
use Illuminate\Support\Facades\Gate;

use Illuminate\Support\Facades\Auth;
use App\Filament\Forms\Resources\FormResource\RelationManagers\ViewFormActivitiesRelationManager;

class ViewForm extends ViewRecord
{
    protected static string $resource = FormResource::class;

    protected static string $view = 'filament.forms.resources.form-resource.pages.view-form';

    public function getRelationManagers(): array
    {


        if (Gate::allows('admin') || Gate::allows('form-developer')) {
            return [
                FormVersionRelationManager::class,
                // ViewFormActivitiesRelationManager::make(),
                ViewFormActivitiesRelationManager::class,

            ];
        }

        // Check business area access
        $user = Auth::user();
        $businessAreaIds = $user->businessAreas->pluck('id')->toArray();

        if (!empty($businessAreaIds)) {
            $form = $this->getRecord();
            $hasAccess = $form->businessAreas()
                ->whereIn('business_areas.id', $businessAreaIds)
                ->exists();

            if ($hasAccess) {
                return [
                    FormVersionRelationManager::class,
                    // CustomActivitylogRelationManager::make(),
                    ViewFormActivitiesRelationManager::class,

                ];
            }
        }
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
