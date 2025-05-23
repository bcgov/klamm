<?php

namespace App\Filament\Plugins\ActivityLog\Pages;

use Filament\Resources\Pages\ViewRecord;
use App\Filament\Plugins\ActivityLog\CustomActivitylogResource;

class CustomViewActivitylog extends ViewRecord
{
    protected static string $resource = CustomActivitylogResource::class;
}
