<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelTableResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelTableResource;
use App\Filament\Fodig\Resources\AnonymousSiebelTableResource\RelationManagers\ColumnsRelationManager;
use App\Filament\Fodig\RelationManagers\ActivityLogRelationManager;
use Filament\Resources\Pages\ViewRecord;

class ViewAnonymousSiebelTable extends ViewRecord
{
    protected static string $resource = AnonymousSiebelTableResource::class;

    protected const DEFAULT_RELATION_MANAGERS = [
        ColumnsRelationManager::class,
        ActivityLogRelationManager::class,
    ];

    public function getRelationManagers(): array
    {
        return self::DEFAULT_RELATION_MANAGERS;
    }
}
