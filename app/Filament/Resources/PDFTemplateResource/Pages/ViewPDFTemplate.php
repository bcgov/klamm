<?php

namespace App\Filament\Resources\PDFTemplateResource\Pages;

use App\Filament\Resources\PDFTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPDFTemplate extends ViewRecord
{
    protected static string $resource = PDFTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
