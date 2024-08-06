<?php

namespace App\Filament\Forms\Resources\UserTypeResource\Pages;

use App\Filament\Forms\Resources\UserTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserType extends CreateRecord
{
    protected static string $resource = UserTypeResource::class;
}
