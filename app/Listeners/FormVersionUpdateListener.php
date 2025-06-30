<?php

namespace App\Listeners;

use App\Events\FormVersionUpdateEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Helpers\FormDataHelper;
use App\Helpers\FormTemplateHelper;
use App\Helpers\DraftCacheHelper;

class FormVersionUpdateListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(FormVersionUpdateEvent $event): void
    {
        $logContext = [
            'form_id' => $event->formId,
            'version_number' => $event->versionNumber,
            'update_type' => $event->updateType,
            'is_draft' => $event->isDraft,
        ];

        Log::info("Handling FormVersionUpdateEvent for form version {$event->formVersionId}", $logContext);

        try {
            if ($event->isDraft) {
                $this->handleDraftUpdate($event);
            } else {
                $this->handleUpdate($event);
            }
        } catch (\Exception $e) {
            Log::error("Error in FormVersionUpdateListener: " . $e->getMessage(), [
                'exception' => $e,
                'form_version_id' => $event->formVersionId,
                'is_draft' => $event->isDraft
            ]);
        }
    }

    /**
     * Handle draft-specific updates
     */
    private function handleDraftUpdate(FormVersionUpdateEvent $event): void
    {
        Log::info("Processing draft update for form version {$event->formVersionId}");
        if ($event->updateType === 'components' && $event->updatedComponents) {
            Cache::forget("formtemplate:{$event->formVersionId}:draft_cached_json");
            Log::info("Draft caches cleared for form version: {$event->formVersionId}");
        }
    }

    /**
     * Handle updates
     */
    private function handleUpdate(FormVersionUpdateEvent $event): void
    {
        // Clear caches based on update type
        if (in_array($event->updateType, ['components', 'general', 'element_created', 'element_updated', 'element_deleted', 'elements_moved'])) {
            // Invalidate caches
            // FormDataHelper::invalidateCache('form_version', $event->formVersionId);
            // FormDataHelper::invalidateCache('form', $event->formId);

            if (in_array($event->updateType, ['components', 'element_created', 'element_updated', 'element_deleted', 'elements_moved']) && $event->updatedComponents) {
                $this->regenerateTemplate($event->formVersionId, $event->updatedComponents);
            }

            Log::info("Updated caches for form version: {$event->formVersionId}, type: {$event->updateType}");
        } elseif (in_array($event->updateType, ['styles', 'scripts', 'styles_scripts'])) {
            Log::info("Styles/Scripts updated for form version: {$event->formVersionId}");
            Cache::forget("formtemplate:{$event->formVersionId}:styles");
            Cache::forget("formtemplate:{$event->formVersionId}:scripts");
        } elseif ($event->updateType === 'deleted') {
            Log::info("Cleared all caches for deleted form version: {$event->formVersionId}");
        } elseif ($event->updateType === 'status' || $event->updateType === 'deployment') {
            // FormDataHelper::invalidateCache('form_version', $event->formVersionId);
        } elseif ($event->updateType === 'manual_broadcast') {
            Log::info("Manual broadcast triggered for form version: {$event->formVersionId}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(FormVersionUpdateEvent $event, \Throwable $exception): void
    {
        Log::error("Failed to process FormVersionUpdateEvent", [
            'form_version_id' => $event->formVersionId,
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Regenerate the template in the background
     */
    protected function regenerateTemplate(int $formVersionId, ?array $updatedComponents = null): void
    {
        try {
            // Format the updated components for the template generator
            if ($updatedComponents) {
                Log::info("Regenerating template with updated components", [
                    'form_version_id' => $formVersionId,
                    'component_count' => count($updatedComponents)
                ]);
                $formattedComponents = [];
                foreach ($updatedComponents as $index => $component) {
                    $type = 'form_field';
                    if (isset($component['data'])) {
                        if (isset($component['data']['block_type'])) {
                            if ($component['data']['block_type'] === 'FormFieldBlock') {
                                $type = 'form_field';
                            } elseif ($component['data']['block_type'] === 'FieldGroupBlock') {
                                $type = 'field_group';
                            } elseif ($component['data']['block_type'] === 'ContainerBlock') {
                                $type = 'container';
                            }
                        }

                        $formattedComponents[] = [
                            'type' => $type,
                            'data' => $component['data'],
                            'order' => $index
                        ];
                    }
                }
                // TODO: Uncomment when FormTemplateHelper is available
                // $jsonTemplate = FormTemplateHelper::generateJsonTemplate($formVersionId, $formattedComponents);
            } else {
                // TODO: Uncomment when FormTemplateHelper is available
                // $jsonTemplate = FormTemplateHelper::generateJsonTemplate($formVersionId);
            }

            $cacheKey = "formtemplate:{$formVersionId}:cached_json";
            // Cache::tags(['form-template'])->put($cacheKey, $jsonTemplate, now()->addDay());

            Log::info("Template regeneration process completed for form version: {$formVersionId}", [
                'using_updated_components' => $updatedComponents !== null
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to regenerate template: " . $e->getMessage(), [
                'form_version_id' => $formVersionId,
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
