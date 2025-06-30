<?php

namespace App\Http\Controllers;

use App\Models\FormBuilding\FormVersion;
use App\Models\WebhookSubscription;
use App\Helpers\FormTemplateHelper;
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
                if ($isDraft) {
                    // For draft requests, first try to get published template and use it as base
                    // Otherwise, generate a new draft template
                    $publishedCacheKey = "formtemplate:{$id}:cached_json";
                    $publishedTemplate = Cache::tags(['form-template'])->get($publishedCacheKey);

                    if ($publishedTemplate !== null) {
                        $jsonTemplate = $publishedTemplate;
                        Cache::tags(['draft'])->put($cacheKey, $jsonTemplate, now()->addDay());
                    } else {
                        $jsonTemplate = FormTemplateHelper::generateJsonTemplate($id);
                        Cache::tags(['draft'])->put($cacheKey, $jsonTemplate, now()->addDay());
                    }
                } else {

                    $jsonTemplate = FormTemplateHelper::generateJsonTemplate($id);
                    Cache::tags(['form-template'])->put($cacheKey, $jsonTemplate, now()->addDay());
                }
            }

            $filename = $isDraft ? "form_template_draft_{$id}.json" : "form_template_{$id}.json";

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

            $cacheKey = $isDraft ? "formtemplate:{$id}:draft_cached_json" : "formtemplate:{$id}:cached_json";
            $cacheTag = $isDraft ? 'draft' : 'form-template';

            // Try to get the requested template from cache
            $cachedTemplate = Cache::tags([$cacheTag])->get($cacheKey);

            if ($cachedTemplate !== null) {
                $jsonTemplate = $cachedTemplate;
            } else {
                if ($isDraft) {
                    // For draft requests, first try to get published template and use it as base
                    // Otherwise, generate a new draft template
                    $publishedCacheKey = "formtemplate:{$id}:cached_json";
                    $publishedTemplate = Cache::tags(['form-template'])->get($publishedCacheKey);

                    if ($publishedTemplate !== null) {
                        // Use published template as base for new draft
                        $jsonTemplate = $publishedTemplate;
                        // Store it as draft cache
                        Cache::tags([$cacheTag])->put($cacheKey, $jsonTemplate, now()->addDay());
                    } else {
                        // No published template exists, generate fresh
                        $jsonTemplate = FormTemplateHelper::generateJsonTemplate($id);
                        Cache::tags([$cacheTag])->put($cacheKey, $jsonTemplate, now()->addDay());
                    }
                } else {
                    // Generate published template
                    $jsonTemplate = FormTemplateHelper::generateJsonTemplate($id);
                    Cache::tags([$cacheTag])->put($cacheKey, $jsonTemplate, now()->addDay());
                }
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
                'cache_source' => $cachedTemplate !== null ? 'cache' : 'generated'
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
            FormTemplateHelper::clearFormTemplateCache($id);

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
