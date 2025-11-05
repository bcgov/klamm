<?php

namespace App\Helpers;

use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\FormElement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * Base query for visible *elements* (soft-deletes excluded).
     * Does NOT filter by save_on_submit so validation sees all fields.
     */
    public static function visibleElementsQuery(int $formVersionId): Builder
    {
        return FormElement::query()
            ->where('form_version_id', $formVersionId)
            ->whereNull('deleted_at');
    }


    /**
     * Visible, reachable *fields*:
     *  - soft-deleted excluded
     *  - non-field types excluded (containers/buttons/displays/etc.)
     *  - full ancestor chain must exist (no deep orphans)
     *  - does NOT filter by save_on_submit (prevents false negatives)
     */
    public static function visibleFieldElements(int $formVersionId): Collection
    {
        $all = self::visibleElementsQuery($formVersionId)
            ->get(['id', 'parent_id', 'elementable_type', 'reference_id', 'name', 'uuid']);

        $byId = $all->keyBy('id');

        $isField = static function ($type): bool {
            $base = class_basename((string) $type);
            return !Str::contains($base, [
                'Container',
                'Section',
                'Group',
                'Page',
                'Button',
                'Display',
                'TextDisplay',
                'Heading',
                'Title',
                'Divider',
                'Separator',
                'Label',
                'Note',
            ]);
        };

        $chainOk = static function ($el) use ($byId): bool {
            $p = (int) ($el->parent_id ?? -1);
            $guard = 0;
            while ($p !== -1) {
                if (++$guard > 1000)
                    return false;
                $parent = $byId->get($p);
                if (!$parent)
                    return false;
                $p = (int) ($parent->parent_id ?? -1);
            }
            return true;
        };

        return $all->filter(fn($el) => $isField($el->elementable_type) && $chainOk($el))->values();
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
