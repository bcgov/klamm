<?php

namespace App\Traits;

use App\Models\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

trait HasBusinessAreaAccess
{
    protected function getUserBusinessAreaIds(): array
    {
        $user = Auth::user();
        return $user->businessAreas->pluck('id')->toArray();
    }

    protected function hasBusinessAreaAccess(): bool
    {
        return !empty($this->getUserBusinessAreaIds());
    }

    protected function getAccessibleForms(): Collection
    {
        $businessAreaIds = $this->getUserBusinessAreaIds();

        if (empty($businessAreaIds)) {
            return collect();
        }

        return Form::whereHas('businessAreas', function ($query) use ($businessAreaIds) {
            $query->whereIn('business_areas.id', $businessAreaIds);
        })->get();
    }

    protected function hasAccessToForm($form): bool
    {
        $businessAreaIds = $this->getUserBusinessAreaIds();

        if (empty($businessAreaIds)) {
            return false;
        }

        return $form->businessAreas()
            ->whereIn('business_areas.id', $businessAreaIds)
            ->exists();
    }

    protected function hasAccessToFormVersion($formVersion): bool
    {
        if (!$formVersion->form) {
            return false;
        }

        return $this->hasAccessToForm($formVersion->form);
    }

    protected function getAccessibleFormVersions(): Collection
    {
        $businessAreaIds = $this->getUserBusinessAreaIds();

        if (empty($businessAreaIds)) {
            return collect();
        }

        return \App\Models\FormVersion::whereHas('form.businessAreas', function ($query) use ($businessAreaIds) {
            $query->whereIn('business_areas.id', $businessAreaIds);
        })->get();
    }
}
