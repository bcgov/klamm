<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFormVersions extends ListRecords
{
    protected static string $resource = FormVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('New Form Version')
                ->label('New form version')
                ->url(fn() => route('filament.forms.resources.form-versions.create', ['form_id' => request()->query('form_id')])),
        ];
    }
}
