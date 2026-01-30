<?php

namespace App\Jobs;

use App\Models\Anonymizer\AnonymizationJobs;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAnonymizationJobConstraintsSql implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(public int $jobId)
    {
        $this->onQueue('anonymization');
    }

    public function handle(AnonymizationJobScriptService $scriptService): void
    {
        $job = AnonymizationJobs::query()->find($this->jobId);

        if (! $job) {
            throw new ModelNotFoundException("AnonymizationJobs {$this->jobId} not found.");
        }

        Log::info('GenerateAnonymizationJobConstraintsSql: starting', [
            'job_id' => $this->jobId,
            'job_name' => $job->name,
        ]);

        // Build a constraints-only script so PK/FK creation can be appended to the job SQL.
        $constraintsSql = $scriptService->buildConstraintsOnlyForJob($job);
        if (trim($constraintsSql) === '') {
            Log::warning('GenerateAnonymizationJobConstraintsSql: no constraints generated', [
                'job_id' => $this->jobId,
            ]);
            return;
        }

        $markerStart = '-- BEGIN CONSTRAINTS ONLY';
        $markerEnd = '-- END CONSTRAINTS ONLY';
        $block = $markerStart . PHP_EOL . $constraintsSql . PHP_EOL . $markerEnd;

        // Remove any prior constraints block before appending the fresh one.
        $existing = (string) ($job->sql_script ?? '');
        if ($existing !== '' && str_contains($existing, $markerStart) && str_contains($existing, $markerEnd)) {
            $existing = preg_replace(
                '/' . preg_quote($markerStart, '/') . '.*' . preg_quote($markerEnd, '/') . '/s',
                '',
                $existing
            ) ?? $existing;
        }

        $combined = trim($existing);
        if ($combined !== '') {
            $combined .= PHP_EOL . PHP_EOL;
        }
        $combined .= $block;

        DB::table('anonymization_jobs')
            ->where('id', $this->jobId)
            ->update([
                'sql_script' => $combined,
                'updated_at' => now(),
            ]);

        Log::info('GenerateAnonymizationJobConstraintsSql: completed', [
            'job_id' => $this->jobId,
            'constraints_length' => strlen($constraintsSql),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateAnonymizationJobConstraintsSql: failed', [
            'job_id' => $this->jobId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
