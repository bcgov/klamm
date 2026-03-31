<?php

namespace App\Jobs;

use App\Services\Anonymizer\ExportProgressNotificationService;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;

class PrepareCsvExportWithProgress extends PrepareCsvExport
{
    public function handle(): void
    {
        app(ExportProgressNotificationService::class)
            ->syncForExportId((int) $this->export->getKey(), ExportProgressNotificationService::STAGE_PREPARING);

        parent::handle();

        app(ExportProgressNotificationService::class)
            ->syncForExportId((int) $this->export->getKey(), ExportProgressNotificationService::STAGE_EXPORTING);
    }

    public function getExportCsvJob(): string
    {
        return ExportCsvWithProgress::class;
    }
}
