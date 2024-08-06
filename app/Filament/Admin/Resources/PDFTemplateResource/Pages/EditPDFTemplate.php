<?php

namespace App\Filament\Admin\Resources\PDFTemplateResource\Pages;

use App\Filament\Admin\Resources\PDFTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPDFTemplate extends EditRecord
{
    protected static string $resource = PDFTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
