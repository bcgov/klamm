<?php

namespace App\Filament\Admin\Resources\PDFTemplateResource\Pages;

use App\Filament\Admin\Resources\PDFTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPDFTemplates extends ListRecords
{
    protected static string $resource = PDFTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
