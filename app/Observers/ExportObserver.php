<?php

namespace App\Observers;

use App\Services\Anonymizer\ExportProgressNotificationService;
use Filament\Actions\Exports\Models\Export;

class ExportObserver
{
    public function created(Export $export): void
    {
        app(ExportProgressNotificationService::class)->syncForExport($export, ExportProgressNotificationService::STAGE_QUEUED);
    }
}
