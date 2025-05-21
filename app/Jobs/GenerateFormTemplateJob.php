<?php

namespace App\Jobs;

use App\Helpers\FormTemplateHelper;
use App\Models\FormVersion;
use App\Models\User;
use App\Notifications\FormTemplateReady;
use App\Notifications\FormTemplateError;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateFormTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $formVersionId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($formVersionId, $userId)
    {
        $this->formVersionId = $formVersionId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $formVersion = FormVersion::with('form')->find($this->formVersionId);
        $user = User::find($this->userId);

        if (!$formVersion) {
            return;
        }

        if (!$user) {
            return;
        }

        try {
            $formId = $formVersion->form->form_id ?? 'unknown';
            $versionNumber = $formVersion->version_number ?? 'draft';
            $uuid = Str::uuid()->toString();

            $filename = "form_templates/{$formId}_v{$versionNumber}_{$uuid}.json";

            $jsonTemplate = FormTemplateHelper::generateJsonTemplate($formVersion->id);

            Storage::disk('local')->put($filename, $jsonTemplate);

            $url = url('download-template/' . basename($filename));

            $formTitle = $formVersion->form->form_title ?? 'Form';

            $user->notify(new FormTemplateReady($formTitle, $url));

            Log::info('Notification sent successfully');
        } catch (\Exception $e) {
            try {
                $user->notify(new FormTemplateError());
                Log::info('Error notification sent successfully');
            } catch (\Exception $notificationException) {
                Log::error('Failed to send error notification', [
                    'exception' => $notificationException->getMessage()
                ]);
            }
        }
    }
}
