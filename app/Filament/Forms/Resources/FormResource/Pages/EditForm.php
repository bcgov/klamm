<?php

namespace App\Filament\Forms\Resources\FormResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }
}
