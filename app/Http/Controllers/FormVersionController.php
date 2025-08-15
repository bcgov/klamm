<?php

namespace App\Http\Controllers;

use App\Models\FormBuilding\FormVersion;
use App\Models\WebhookSubscription;
use App\Services\FormVersionJsonService;
use App\Helpers\DraftCacheHelper;
use App\Events\FormVersionUpdateEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class FormVersionController extends Controller
{

    // Get the JSON template for a form version if it exists
    // If it doesn't exist, generate it and store it in cache for one day
    public function getFormTemplate($id, Request $request)
    {
        try {
            // Find the form version
            $formVersion = FormVersion::findOrFail($id);
            if (!$formVersion) {
                return response()->json(['error' => 'Form version not found'], 404);
            }

            $isDraft = $request->query('draft', false);
            $isDraft = filter_var($isDraft, FILTER_VALIDATE_BOOLEAN);

            $cacheKey = $isDraft ? "formtemplate:{$id}:draft_cached_json" : "formtemplate:{$id}:cached_json";
            $cacheTag = $isDraft ? 'draft' : 'form-template';

            $jsonTemplate = Cache::tags([$cacheTag])->get($cacheKey);
            if ($jsonTemplate === null) {
                // Generate JSON using FormVersionJsonService
                $jsonService = new FormVersionJsonService();
                $jsonData = $jsonService->generateJson($formVersion);
                $jsonTemplate = json_encode($jsonData, JSON_PRETTY_PRINT);

                // Cache the result
                Cache::tags([$cacheTag])->put($cacheKey, $jsonTemplate, now()->addDay());
            }

            // Generate filename using same logic as GenerateFormVersionJsonJob
            $formTitle = $formVersion->form->form_title ?? 'Unknown Form';
            $sanitizedTitle = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $formTitle);
            $draftSuffix = $isDraft ? '_draft' : '';
            $filename = "form_{$sanitizedTitle}_v{$formVersion->version_number}_{$formVersion->id}{$draftSuffix}.json";

            return response($jsonTemplate)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', "attachment; filename={$filename}");
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate form template',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity logs for a form version ordered by most recent first
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFormVersionLogs($id)
    {
        try {
            // Find the form version
            $formVersion = FormVersion::findOrFail($id);

            // Get the logs for this form version with most recent first
            $logs = Activity::where('subject_type', FormVersion::class)
                ->where('subject_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'logs' => $logs,
                'form_version' => [
                    'id' => $formVersion->id,
                    'form_id' => $formVersion->form_id,
                    'version_number' => $formVersion->version_number,
                    'status' => $formVersion->status
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve form version logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getFormData($id, Request $request)
    {
        try {
            // Find the form version
            $formVersion = FormVersion::findOrFail($id);

            if (!$formVersion) {
                return response()->json(['error' => 'Form version not found'], 404);
            }

            // Get logs for this form version
            $logs = Activity::where('subject_type', FormVersion::class)
                ->where('subject_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $isDraft = $request->query('draft', false);
            $isDraft = filter_var($isDraft, FILTER_VALIDATE_BOOLEAN);

            // Check if pre-migration format is requested
            $usePreMigrationFormat = $request->query('pre_migration', false);
            $usePreMigrationFormat = filter_var($usePreMigrationFormat, FILTER_VALIDATE_BOOLEAN);

            $jsonService = new FormVersionJsonService();

            if ($usePreMigrationFormat) {
                // Use pre-migration format
                $formTemplate = $jsonService->generatePreMigrationJson($formVersion);

                return response()->json([
                    'logs' => $logs,
                    'form_version' => [
                        'id' => $formVersion->id,
                        'form_id' => $formVersion->form_id,
                        'version_number' => $formVersion->version_number,
                        'status' => $formVersion->status
                    ],
                    'form_template' => $formTemplate,
                    'is_draft' => $isDraft,
                    'format' => 'pre_migration'
                ]);
            }

            // Use original format with caching
            $cacheKey = $isDraft ? "formtemplate:{$id}:draft_cached_json" : "formtemplate:{$id}:cached_json";
            $cacheTag = $isDraft ? 'draft' : 'form-template';

            // Try to get the requested template from cache
            // $cachedTemplate = Cache::tags([$cacheTag])->get($cacheKey);
            $cachedTemplate = null;

            if ($cachedTemplate !== null) {
                $jsonTemplate = $cachedTemplate;
            } else {
                // Generate JSON using FormVersionJsonService
                $jsonData = $jsonService->generateJson($formVersion);
                $jsonTemplate = json_encode($jsonData, JSON_PRETTY_PRINT);

                // Cache the result
                Cache::tags([$cacheTag])->put($cacheKey, $jsonTemplate, now()->addDay());
            }

            // Return a properly formatted response with both logs and form template
            return response()->json([
                'logs' => $logs,
                'form_version' => [
                    'id' => $formVersion->id,
                    'form_id' => $formVersion->form_id,
                    'version_number' => $formVersion->version_number,
                    'status' => $formVersion->status
                ],
                'form_template' => json_decode($jsonTemplate),
                'is_draft' => $isDraft,
                'cache_source' => $cachedTemplate !== null ? 'cache' : 'generated',
                'format' => 'original'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve form version data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update components of a form version and broadcast the changes
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFormComponents($id, Request $request)
    {
        try {
            // Find the form version
            $formVersion = FormVersion::findOrFail($id);

            // Validate that the components data exists
            $request->validate([
                'components' => 'required|array',
            ]);

            // Get the components from the request
            $components = $request->input('components');

            // Update the form version with the new components
            $formVersion->components = $components;
            $formVersion->save();

            // Clear both published and draft caches for this form version
            Cache::forget("formtemplate:{$id}:cached_json");
            Cache::forget("formtemplate:{$id}:draft_cached_json");

            // Dispatch event about the component update
            event(new FormVersionUpdateEvent(
                $formVersion->id,
                $formVersion->form_id,
                $formVersion->version_number,
                $components,
                'components'
            ));

            return response()->json([
                'success' => true,
                'message' => 'Form components updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update form components',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
