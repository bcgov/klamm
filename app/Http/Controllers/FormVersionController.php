<?php

namespace App\Http\Controllers;

use App\Models\FormVersion;
use App\Helpers\FormTemplateHelper;
use Illuminate\Support\Facades\Cache;

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
}
