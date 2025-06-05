<?php

namespace App\Filament\Forms\Resources\ApprovalRequestResource\Pages;

use App\Filament\Forms\Resources\ApprovalRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewApprovalRequest extends ViewRecord
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
