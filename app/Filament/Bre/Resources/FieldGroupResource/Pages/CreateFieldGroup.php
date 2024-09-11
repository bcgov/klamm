<?php

namespace App\Filament\Bre\Resources\FieldGroupResource\Pages;

use App\Filament\Bre\Resources\FieldGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFieldGroup extends CreateRecord
{
    protected static string $resource = FieldGroupResource::class;
    protected static ?string $title = 'Create BRE Rule Field Group';
}
