<?php

namespace App\Helpers;

use App\Models\FormField;
use App\Models\FieldGroup;
use App\Models\FormDataSource;
use App\Models\SelectOptions;
use App\Models\Style;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class FormDataHelper
{
    protected static array $cache = [];
    protected static bool $isFullyLoaded = false;
    protected static array $preloadedComponents = [];
    protected static array $preloadedBlocks = [];

    public static function load(): void
    {
        $routeName = request()->route()?->getName() ?? '';
        $isViewPage = str_contains($routeName, '.view');
        $isEditPage = str_contains($routeName, '.edit');
        $isCreatePage = str_contains($routeName, '.create');
        $formId = request()->route('record');

        $needsFullLoad = ($isEditPage || $isCreatePage);

        // Check for full cache
        $fullCacheKey = "form_data:full:{$formId}";
        if ($formId && Cache::has($fullCacheKey)) {
            $cachedData = Cache::get($fullCacheKey);
            if ($cachedData) {
                self::$cache = $cachedData;
                self::$isFullyLoaded = true;
                self::$preloadedComponents = [];
                self::$preloadedBlocks = [];
                Log::info("Using cached full form data for form: {$formId}");
                return;
            }
        }

        // Check for basic cache
        // supports view only pages since they don't need full data
        $basicCacheKey = "form_data:basic:{$formId}";
        if (($isViewPage || $isEditPage) && $formId && (Cache::has($basicCacheKey))) {
            $cachedData = Cache::get($basicCacheKey);
            if ($cachedData) {
                self::$cache = $cachedData;
                self::$isFullyLoaded = false;
                self::$preloadedComponents = [];
                self::$preloadedBlocks = [];
                if ($isEditPage) {
                    self::loadAdditionalRelationships($formId);
                    self::$isFullyLoaded = true;
                    Log::info("loading full data for form: {$formId}");
                } else {
                    Log::info("Using cached basic form data for form: {$formId}");
                }
                return;
            }
        }

        $fieldRelations = $isViewPage ? [
            'dataType:id,name',
            'formFieldValue:id,form_field_id,value',
        ] : [
            'dataType:id,name',
            'formFieldValue:id,form_field_id,value',
        ];

        $fields = collect();
        $query = FormField::with($fieldRelations)
            ->select([
                'id',
                'label',
                'name',
                'data_type_id',
                'data_binding',
                'data_binding_path',
                'mask',
                'help_text'
            ]);

        foreach ($query->cursor() as $field) {
            $fields[$field->id] = $field;
        }

        $groups = collect();
        $groupQuery = FieldGroup::select(['id', 'label', 'name']);
        foreach ($groupQuery->cursor() as $group) {
            $groups[$group->id] = $group;
        }

        // Load other data minimally
        self::$cache = [
            'fields' => $fields,
            'groups' => $groups,
            'styles' => Style::select(['id', 'name'])->get()->keyBy('id'),
            'dataSources' => FormDataSource::select(['id', 'name'])->get()->keyBy('id'),
            'selectOptions' => SelectOptions::select(['id', 'label'])->get()->keyBy('id'),
        ];

        // For edit and view pages with a form ID, cache this basic data for quick future access
        if (($isEditPage || $isViewPage) && $formId) {
            Cache::put("form_data:basic:{$formId}", self::$cache, now()->addHour());
        }

        // For non-view pages that need full data, queue the loading of additional relationships
        if ($needsFullLoad && $formId) {
            self::$isFullyLoaded = true;
            Log::info("Loading full form data for form: {$formId}");

            // Load the additional relationships in background or immediately based on context
            self::loadAdditionalRelationships($formId);
        } else {
            self::$isFullyLoaded = false;
        }

        self::$preloadedComponents = [];
        self::$preloadedBlocks = [];
    }


    // Load absolute minimal data for block headers and display rendering

    public static function loadMinimal(): void
    {
        if (self::$isFullyLoaded) {
            return;
        }
        $cacheKey = 'form_data:minimal';
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            self::$cache = $cachedData;
            return;
        }

        $formFieldValues = \App\Models\FormFieldValue::select(['id', 'form_field_id', 'value'])
            ->get()
            ->keyBy('form_field_id');

        self::$cache = [
            'fields' => FormField::select([
                'id',
                'label',
                'name',
                'data_type_id',
                'data_binding',
                'data_binding_path',
                'mask',
                'help_text'
            ])
                ->with([
                    'dataType:id,name',
                ])
                ->get()
                ->keyBy('id')
                ->map(function ($field) use ($formFieldValues) {
                    if (isset($formFieldValues[$field->id])) {
                        $field->formFieldValue = $formFieldValues[$field->id];
                    }
                    return $field;
                }),
            'groups' => FieldGroup::select(['id', 'label', 'name'])->get()->keyBy('id'),
            'styles' => Style::select(['id', 'name'])->get()->keyBy('id'),
            'dataSources' => FormDataSource::select(['id', 'name'])->get()->keyBy('id'),
            'selectOptions' => SelectOptions::select(['id', 'label', 'name', 'value'])->get()->keyBy('id'),
            'dataTypes' => \App\Models\DataType::select(['id', 'name'])->get()->keyBy('id'),
            'formFieldValues' => $formFieldValues,
        ];

        Cache::put($cacheKey, self::$cache, now()->addMinutes(10));
    }



    // Get data from cache, loading minimal if needed

    public static function get(string $key): Collection
    {
        if (empty(self::$cache)) {
            self::loadMinimal();
        }

        return collect(self::$cache[$key] ?? []);
    }

    public static function ensureFullyLoaded(): void
    {
        if (!self::$isFullyLoaded) {
            self::load();
        }
    }

    public static function refresh(): void
    {
        self::load();
    }

    // Load additional relationships for full data
    protected static function loadAdditionalRelationships(mixed $formId): void
    {
        if (!$formId) {
            return;
        }
        $cacheKey = "form_data:full:{$formId}";
        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                self::$cache = $cachedData;
                return;
            }
        }

        // Get all the additional relationships
        try {
            FormField::with([
                'webStyles:id,name',
                'pdfStyles:id,name',
                'validations:id,form_field_id,type,value,error_message',
                'selectOptionInstances:id,form_field_id,select_option_id,order',
            ])->chunk(100, function ($fields) use ($formId) {
                foreach ($fields as $field) {
                    if (isset(self::$cache['fields'][$field->id])) {
                        self::$cache['fields'][$field->id]->webStyles = $field->webStyles;
                        self::$cache['fields'][$field->id]->pdfStyles = $field->pdfStyles;
                        self::$cache['fields'][$field->id]->validations = $field->validations;
                        self::$cache['fields'][$field->id]->selectOptionInstances = $field->selectOptionInstances;
                    }
                }
            });

            FieldGroup::with(['formFields' => function ($query) {
                $query->select(['form_fields.id', 'form_fields.label', 'form_fields.name']);
            }])->chunk(50, function ($groups) {
                foreach ($groups as $group) {
                    if (isset(self::$cache['groups'][$group->id])) {
                        self::$cache['groups'][$group->id]->formFields = $group->formFields;
                    }
                }
            });

            Log::info("Loaded additional relationships for form: {$formId}");
            Cache::put($cacheKey, self::$cache, now()->addHour());
            Log::info("Cached full form data for form: {$formId}");
        } catch (\Exception $e) {
            Log::error("Error loading additional relationships: " . $e->getMessage());
        }
    }



    /**
     * Invalidate cache for a field or group when it's updated
     */
    public static function invalidateCache(string $type, int $id): void
    {
        $keys = [];
        $formId = null;

        if ($type === 'field') {
            $keys[] = "form_field:{$id}:data";

            // Get the field to find related form ID
            try {
                $field = FormField::select(['id', 'form_id', 'field_group_id'])->find($id);
                if ($field) {
                    if ($field->form_id) {
                        $formId = $field->form_id;
                        $keys[] = "form_data:full:{$formId}";
                        $keys[] = "form_data:minimal:{$formId}";
                    }

                    if ($field->field_group_id) {
                        $keys[] = "form_group:{$field->field_group_id}:data";
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error invalidating field cache: " . $e->getMessage());
            }
        } else if ($type === 'group') {
            $keys[] = "form_group:{$id}:data";

            // Get the group to find related form ID
            try {
                $group = FieldGroup::select(['id', 'form_id'])->find($id);
                if ($group && $group->form_id) {
                    $formId = $group->form_id;
                    $keys[] = "form_data:full:{$formId}";
                    $keys[] = "form_data:minimal:{$formId}";
                }
            } catch (\Exception $e) {
                Log::error("Error invalidating group cache: " . $e->getMessage());
            }
        } else if ($type === 'form') {
            $formId = $id;
            $keys[] = "form_data:full:{$formId}";
            $keys[] = "form_data:minimal:{$formId}";

            // Also invalidate any field-related caches for this form
            try {
                $fieldIds = FormField::where('form_id', $formId)->pluck('id')->toArray();
                foreach ($fieldIds as $fieldId) {
                    $keys[] = "form_field:{$fieldId}:data";
                }

                // And group-related caches
                $groupIds = FieldGroup::where('form_id', $formId)->pluck('id')->toArray();
                foreach ($groupIds as $groupId) {
                    $keys[] = "form_group:{$groupId}:data";
                }
            } catch (\Exception $e) {
                Log::error("Error invalidating form-related caches: " . $e->getMessage());
            }
        }

        // Also invalidate the global minimal data cache since it might contain this data
        $keys[] = "form_data:minimal";

        // Also invalidate any form templates that might be cached
        if ($formId) {
            $keys[] = "formtemplate:{$formId}:cached_json";
        }

        // Delete all affected cache keys
        foreach ($keys as $key) {
            Cache::forget($key);
            Log::debug("Cache invalidated: {$key}");
        }

        // Reset instance caches when invalidating
        self::$preloadedComponents = [];
        self::$preloadedBlocks = [];
    }

    /**
     * Force refresh all caches - use sparingly
     * Can be targeted to a specific form ID if provided
     */
    public static function invalidateAllCaches(?int $formId = null): void
    {
        // If we have a form ID, be more targeted
        if ($formId) {
            // Clear specific form data caches
            Cache::forget("form_data:full:{$formId}");
            Cache::forget("form_data:minimal:{$formId}");
            Cache::forget("formtemplate:{$formId}:cached_json");

            // Also invalidate any field caches for this form
            try {
                $fieldIds = FormField::where('form_id', $formId)->pluck('id')->toArray();
                foreach ($fieldIds as $fieldId) {
                    Cache::forget("form_field:{$fieldId}:data");
                }

                // And group caches
                $groupIds = FieldGroup::where('form_id', $formId)->pluck('id')->toArray();
                foreach ($groupIds as $groupId) {
                    Cache::forget("form_group:{$groupId}:data");
                }
            } catch (\Exception $e) {
                Log::error("Error invalidating form-related caches: " . $e->getMessage());
            }
        } else {
            // Clear all form data caches
            Cache::forget('form_data:minimal');

            // Clear any keys with pattern matching using Redis when no specific form ID
            try {
                $store = Cache::getStore();
                if ($store instanceof \Illuminate\Cache\RedisStore && method_exists($store, 'connection')) {
                    $redis = $store->connection();
                    if (method_exists($redis, 'eval')) {
                        $lua = "local keys = redis.call('keys', ARGV[1]) " .
                            "for i = 1, #keys do " .
                            "    redis.call('del', keys[i]) " .
                            "end " .
                            "return #keys";


                        $redis->eval($lua, 0, 'form_data:*');
                        $redis->eval($lua, 0, 'form_field:*');
                        $redis->eval($lua, 0, 'form_group:*');
                        $redis->eval($lua, 0, 'formtemplate:*');
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error during mass cache invalidation: " . $e->getMessage());
            }
        }

        Log::info("Cache invalidated " . ($formId ? "for form ID: {$formId}" : "globally"));

        // Reset instance variables
        self::$cache = [];
        self::$preloadedComponents = [];
        self::$preloadedBlocks = [];
        self::$isFullyLoaded = false;
    }

    /**
     * Helper method to invalidate form cache when a form is updated
     * Should be called from form update/save methods
     */
    public static function invalidateFormCache(int $formId): void
    {
        self::invalidateCache('form', $formId);
    }
}
