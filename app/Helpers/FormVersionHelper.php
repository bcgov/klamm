<?php

namespace App\Helpers;

use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class FormVersionHelper
{
    /**
     * Helper method to duplicate related models
     */
    public static function duplicateRelatedModels(int $originalVersionId, int $newVersionId, string $modelClass): void
    {
        $models = $modelClass::where('form_version_id', $originalVersionId)->get();

        foreach ($models as $model) {
            $newModel = $model->replicate(['id', 'form_version_id', 'created_at', 'updated_at']);
            $newModel->form_version_id = $newVersionId;

            // Handle special cases for StyleSheet and FormScript to copy files and generate new filenames
            if ($modelClass === StyleSheet::class) {
                self::duplicateStyleSheet($model, $newModel);
            } elseif ($modelClass === FormScript::class) {
                self::duplicateFormScript($model, $newModel);
            }

            $newModel->save();
        }
    }

    /**
     * Duplicate a StyleSheet with new filename and copy CSS content
     */
    private static function duplicateStyleSheet(StyleSheet $original, StyleSheet $new): void
    {
        // Get the original CSS content
        $cssContent = $original->getCssContent();

        // Generate new filename
        $new->filename = StyleSheet::createCssFilename($new->formVersion, $new->type);

        // Save the CSS content to the new file if content exists
        if ($cssContent !== null) {
            $new->saveCssContent($cssContent);
        }
    }

    /**
     * Duplicate a FormScript with new filename and copy JS content
     */
    private static function duplicateFormScript(FormScript $original, FormScript $new): void
    {
        // Get the original JS content
        $jsContent = $original->getJsContent();

        // Generate new filename
        $new->filename = FormScript::createJsFilename($new->formVersion, $new->type);

        // Save the JS content to the new file if content exists
        if ($jsContent !== null) {
            $new->saveJsContent($jsContent);
        }
    }
}
