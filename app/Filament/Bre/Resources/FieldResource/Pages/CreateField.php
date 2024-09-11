<?php

namespace App\Filament\Bre\Resources\FieldResource\Pages;

use App\Filament\Bre\Resources\FieldResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateField extends CreateRecord
{
    protected static string $resource = FieldResource::class;
    protected static ?string $title = 'Create BRE Rule Field';
}
