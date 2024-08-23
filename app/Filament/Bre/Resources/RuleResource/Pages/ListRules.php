<?php

namespace App\Filament\Bre\Resources\RuleResource\Pages;

use App\Filament\Bre\Resources\RuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRules extends ListRecords
{
    protected static string $resource = RuleResource::class;
    protected static ?string $title = 'BRE Rules';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Add New Rule')),
        ];
    }
}
