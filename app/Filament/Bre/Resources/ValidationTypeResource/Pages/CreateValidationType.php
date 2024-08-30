<?php

namespace App\Filament\Bre\Resources\ValidationTypeResource\Pages;

use App\Filament\Bre\Resources\ValidationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateValidationType extends CreateRecord
{
    protected static string $resource = ValidationTypeResource::class;
    protected static ?string $title = 'Create BRE Validation Type';
}
