<?php

namespace App\Filament\Bre\Resources\DataValidationResource\Pages;

use App\Filament\Bre\Resources\DataValidationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDataValidation extends CreateRecord
{
    protected static string $resource = DataValidationResource::class;
    protected static ?string $title = 'Create Field Data Validation';
}
