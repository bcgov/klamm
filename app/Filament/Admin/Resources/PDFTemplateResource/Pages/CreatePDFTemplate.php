<?php

namespace App\Filament\Admin\Resources\PDFTemplateResource\Pages;

use App\Filament\Admin\Resources\PDFTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePDFTemplate extends CreateRecord
{
    protected static string $resource = PDFTemplateResource::class;
}
