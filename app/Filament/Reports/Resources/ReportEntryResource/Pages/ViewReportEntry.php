<?php

namespace App\Filament\Reports\Resources\ReportEntryResource\Pages;

use App\Filament\Reports\Resources\ReportEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\Actions\NextAction;
use App\Filament\Resources\Actions\PreviousAction;
use App\Filament\Resources\Pages\Concerns\CanPaginateViewRecord;

class ViewReportEntry extends ViewRecord
{
    use CanPaginateViewRecord;

    protected static string $resource = ReportEntryResource::class;

    protected static ?bool $disableLabel = true;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            PreviousAction::make(),
            NextAction::make(),
        ];
    }
}
