<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource;
use App\Filament\Fodig\Resources\AnonymousSiebelSchemaResource\RelationManagers\TablesRelationManager;
use App\Filament\Fodig\RelationManagers\ActivityLogRelationManager;
use Filament\Resources\Pages\ViewRecord;

class ViewAnonymousSiebelSchema extends ViewRecord
{
    protected static string $resource = AnonymousSiebelSchemaResource::class;

    protected const DEFAULT_RELATION_MANAGERS = [
        TablesRelationManager::class,
        ActivityLogRelationManager::class,
    ];

    public function getRelationManagers(): array
    {
        return self::DEFAULT_RELATION_MANAGERS;
    }
}
