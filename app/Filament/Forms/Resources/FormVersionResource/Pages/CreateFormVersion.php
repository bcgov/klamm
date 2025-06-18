<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFormVersion extends CreateRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
