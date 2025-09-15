<?php

namespace App\Helpers;

use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\FormElement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

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
     * Visible, data-saving field elements for a given form version.
     */
    public static function visibleFieldElementsQuery(int $formVersionId): Builder
    {
        $q = FormElement::query()
            ->where('form_version_id', $formVersionId)
            ->whereNull('deleted_at');

        if (Schema::hasColumn('form_elements', 'save_on_submit')) {
            $q->where(function (Builder $b) {
                $b->where('save_on_submit', true)
                    ->orWhereNull('save_on_submit');
            });
        }

        $q->where(function (Builder $b) {
            $b->where(
                fn(Builder $x) =>
                $x->where('elementable_type', 'not like', '%Container%')
                    ->where('elementable_type', 'not like', '%Section%')
                    ->where('elementable_type', 'not like', '%Group%')
                    ->where('elementable_type', 'not like', '%Page%')
                    ->where('elementable_type', 'not like', '%Button%')
                    ->where('elementable_type', 'not like', '%Display%')
                    ->where('elementable_type', 'not like', '%Heading%')
                    ->where('elementable_type', 'not like', '%Divider%')
                    ->where('elementable_type', 'not like', '%Label%')
                    ->where('elementable_type', 'not like', '%Note%')
            );
        });

        // Avoid orphans: allow root (-1) or parent exists & not soft-deleted.
        $q->where(function (Builder $b) use ($formVersionId) {
            $b->where('parent_id', -1)
                ->orWhereIn('parent_id', function ($sub) use ($formVersionId) {
                    $sub->from('form_elements')
                        ->select('id')
                        ->where('form_version_id', $formVersionId)
                        ->whereNull('deleted_at');
                });
        });

        return $q;
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
