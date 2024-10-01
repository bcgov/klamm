<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

class ListFormVersions extends ListRecords
{
    protected static string $resource = FormVersionResource::class;

    protected function getTableQuery(): Builder|null
    {
        $query = parent::getTableQuery();
    
        if ($formId = request()->query('form_id')) {
            $query->where('form_id', $formId);
        }
    
        return $query;
    }

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
