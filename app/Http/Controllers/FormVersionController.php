<?php

namespace App\Http\Controllers;

use App\Models\FormVersion;
use App\Models\WebhookSubscription;
use App\Helpers\FormTemplateHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class FormVersionController extends Controller
{

    // Get the JSON template for a form version if it exists
    // If it doesn't exist, generate it and store it in cache for one day
    public function getFormTemplate($id)
    {
        try {
            // Find the form version
            $formVersion = FormVersion::findOrFail($id);
            if (!$formVersion) {
                return response()->json(['error' => 'Form version not found'], 404);
            }

            $cacheKey = "formtemplate:{$id}:cached_json";
            if (Cache::has($cacheKey)) {
                $jsonTemplate = Cache::get($cacheKey);
            } else {
                $jsonTemplate = FormTemplateHelper::generateJsonTemplate($id);
                Cache::tags(['form-template'])->put($cacheKey, $jsonTemplate, now()->addDay());
            }

            return response($jsonTemplate)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', "attachment; filename=form_template_{$id}.json");
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

    public function getFormData($id)
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
                ->get();

            // Get form template
            $cacheKey = "formtemplate:{$id}:cached_json";
            if (Cache::has($cacheKey)) {
                $jsonTemplate = Cache::get($cacheKey);
            } else {
                $jsonTemplate = FormTemplateHelper::generateJsonTemplate($id);
                Cache::tags(['form-template'])->put($cacheKey, $jsonTemplate, now()->addDay());
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
                'form_template' => json_decode($jsonTemplate)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve form version data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
