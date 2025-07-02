<?php

namespace App\Helpers;

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
            $newModel->save();
        }
    }
}
