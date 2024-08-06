<?php

namespace App\Filament\Forms\Resources\RenderedFormResource\Pages;

use App\Filament\Forms\Resources\RenderedFormResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRenderedForm extends CreateRecord
{
    protected static string $resource = RenderedFormResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
