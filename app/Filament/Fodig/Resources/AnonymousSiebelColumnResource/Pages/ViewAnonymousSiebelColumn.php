<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelColumnResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelColumnResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAnonymousSiebelColumn extends ViewRecord
{
    protected static string $resource = AnonymousSiebelColumnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function getTitle(): string
    {
        $record = $this->getRecord();
        $column = $record->column_name ?? ('#' . $record->getKey());

        $tableRelation = $record->getRelationValue('table') ?? $record->table()->withTrashed()->first();
        $schemaRelation = $tableRelation?->getRelationValue('schema') ?? $tableRelation?->schema()->withTrashed()->first();

        $table = $tableRelation?->table_name;
        $schema = $schemaRelation?->schema_name;

        if (! $table) {
            return $column;
        }

        $qualified = $schema ? $schema . '.' . $table : $table;

        return $column . ' — ' . $qualified;
    }
}
