<?php

namespace App\Filament\Forms\Resources\RenderedFormResource\Pages;

use App\Filament\Forms\Resources\RenderedFormResource;
use Filament\Resources\Pages\EditRecord;

class EditRenderedForm extends EditRecord
{
    protected static string $resource = RenderedFormResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['structure'] = json_encode(json_decode($data['structure'], true), JSON_PRETTY_PRINT);

        return $data;
    }
}
