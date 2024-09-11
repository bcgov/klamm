<?php

namespace App\Filament\Bre\Resources\RuleResource\Pages;

use App\Filament\Bre\Resources\RuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRule extends EditRecord
{
    protected static string $resource = RuleResource::class;
    protected static ?string $title = 'Edit BRE Rule';
    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
