<?php

namespace App\Filament\Plugins\ActivityLog\Pages;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;

class CustomListActivitylog extends ListRecords
{
    protected static string $resource = CustomActivitylogResource::class;
}
