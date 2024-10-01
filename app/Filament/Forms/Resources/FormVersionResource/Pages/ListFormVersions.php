<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListFormVersions extends ListRecords
{
    protected static string $resource = FormVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('New Form Version')
                ->label('New Form Version')
                ->url(fn() => route('filament.forms.resources.form-versions.create', ['form_id' => request()->query('form_id')]))
                ->visible(fn() => Gate::allows('form-developer')),
        ];
    }
}
