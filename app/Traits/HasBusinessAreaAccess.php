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
}
