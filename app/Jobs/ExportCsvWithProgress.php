<?php

namespace App\Jobs;

use App\Services\Anonymizer\ExportProgressNotificationService;
use Filament\Actions\Exports\Jobs\ExportCsv;

class ExportCsvWithProgress extends ExportCsv
{
    public function handle(): void
    {
        parent::handle();

        app(ExportProgressNotificationService::class)
            ->syncForExportId((int) $this->export->getKey(), ExportProgressNotificationService::STAGE_EXPORTING);
    }
}
