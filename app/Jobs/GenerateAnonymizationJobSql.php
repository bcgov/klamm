<?php

namespace App\Jobs;

use App\Models\AnonymizationJobs;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAnonymizationJobSql implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobId) {}

    public function handle(AnonymizationJobScriptService $scriptService): void
    {
        $job = AnonymizationJobs::query()
            ->with([
                'columns.anonymizationMethods',
                'columns.table.schema.database',
                'columns.parentColumns.table.schema.database',
            ])
            ->find($this->jobId);

        if (! $job) {
            return;
        }

        $script = $scriptService->buildForJob($job);

        $job->forceFill([
            'sql_script' => $script,
        ])->save();
    }
}
