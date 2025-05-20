<?php

namespace App\Helpers;

use App\Models\FormField;
use App\Models\FieldGroup;
use App\Models\FormDataSource;
use App\Models\SelectOptions;
use App\Models\Style;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FormDataHelper
{
    protected static array $cache = [];
    protected static bool $isFullyLoaded = false;
    protected static array $preloadedComponents = [];
    protected static array $preloadedBlocks = [];

    /**
     * Load all data eagerly (used for template generation and full views)
     */
    public static function load(): void
    {
        $isViewPage = request()->route()?->getName() === 'filament.forms.resources.form-versions.view';

        // For view pages, load only essential relations
        $fieldRelations = $isViewPage ? [
            'dataType:id,name',
            'formFieldValue:id,form_field_id,value',
        ] : [
            'dataType:id,name',
            'webStyles:id,name',
            'pdfStyles:id,name',
            'validations:id,form_field_id,type,value,error_message',
            'selectOptionInstances:id,form_field_id,select_option_id,order',
            'formFieldValue:id,form_field_id,value',
            'formFieldDateFormat:id,form_field_id,date_format',
        ];

        // Use cursor pagination for more memory-efficient loading
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

        // Use cursor for groups too
        $groups = collect();
        $groupQuery = FieldGroup::select(['id', 'label', 'name']);
        if (!$isViewPage) {
            $groupQuery->with(['formFields:id,label,name,field_group_id']);
        }
        foreach ($groupQuery->cursor() as $group) {
            $groups[$group->id] = $group;
        }

        // Load other data minimally
        self::$cache = [
            'fields' => $fields,
            'groups' => $groups,
            'styles' => $isViewPage ? collect() : Style::select(['id', 'name'])->get()->keyBy('id'),
            'dataSources' => $isViewPage ? collect() : FormDataSource::select(['id', 'name'])->get()->keyBy('id'),
            'selectOptions' => $isViewPage ? collect() : SelectOptions::select(['id', 'label'])->get()->keyBy('id'),
        ];

        self::$isFullyLoaded = true;
        self::$preloadedComponents = [];
        self::$preloadedBlocks = [];
    }

    /**
     * Load absolute minimal data for block headers and display rendering
     */
    public static function loadMinimal(): void
    {
        if (self::$isFullyLoaded) {
            return;
        }

        // Only load IDs and display fields initially - absolute minimum needed for dropdowns
        self::$cache = [
            'fields' => FormField::select(['id', 'label', 'name', 'data_type_id'])  // Include data_type_id for the relation
                ->with([
                    'dataType:id,name',
                    'formFieldValue:id,form_field_id,value'
                ])
                ->get()->keyBy('id'),
            'groups' => FieldGroup::select(['id', 'label', 'name'])->get()->keyBy('id'),
            'styles' => Style::select(['id', 'name'])->get()->keyBy('id'),
            'dataSources' => FormDataSource::select(['id', 'name'])->get()->keyBy('id'),
            'selectOptions' => SelectOptions::select(['id', 'label'])->get()->keyBy('id'),
        ];
    }

    /**
     * Get data from cache, loading minimal if needed
     */
    public static function get(string $key): Collection
    {
        if (empty(self::$cache)) {
            self::loadMinimal();
        }

        return collect(self::$cache[$key] ?? []);
    }

    /**
     * Preload a block's required data (used when expanding a block)
     */
    public static function preloadBlockData(string $blockType, array $ids): void
    {
        if (self::$isFullyLoaded) {
            return;
        }

        $blockKey = $blockType . '_' . implode('_', $ids);

        if (isset(self::$preloadedBlocks[$blockKey])) {
            return;
        }

        switch ($blockType) {
            case 'field':
                $data = FormField::with([
                    'dataType:id,name',
                    'formFieldValue:id,form_field_id,value',
                ])->whereIn('id', $ids)->get();

                foreach ($data as $item) {
                    if (isset(self::$cache['fields'])) {
                        self::$cache['fields'][$item->id] = $item;
                    }
                }
                break;

            case 'group':
                $data = FieldGroup::with(['formFields:id,name,label'])
                    ->whereIn('id', $ids)
                    ->get();

                foreach ($data as $item) {
                    if (isset(self::$cache['groups'])) {
                        self::$cache['groups'][$item->id] = $item;
                    }
                }
                break;
        }

        self::$preloadedBlocks[$blockKey] = true;
    }

    /**
     * Load full data for a specific component when it's being edited
     */
    public static function preloadComponentData(string $componentType, int $id): void
    {
        if (self::$isFullyLoaded) {
            return;
        }

        $cacheKey = "{$componentType}_{$id}";

        if (isset(self::$preloadedComponents[$cacheKey])) {
            return;
        }

        switch ($componentType) {
            case 'field':
                self::$preloadedComponents[$cacheKey] = FormField::with([
                    'dataType:id,name',
                    'webStyles:id,name',
                    'pdfStyles:id,name',
                    'validations:id,form_field_id,type,value,error_message',
                    'selectOptionInstances:id,form_field_id,select_option_id,order',
                    'formFieldValue:id,form_field_id,value',
                    'formFieldDateFormat:id,form_field_id,date_format',
                ])->find($id);

                if (isset(self::$cache['fields'])) {
                    self::$cache['fields'][$id] = self::$preloadedComponents[$cacheKey];
                }
                break;

            case 'group':
                self::$preloadedComponents[$cacheKey] = FieldGroup::with([
                    'formFields.dataType',
                    'formFields.formFieldValue',
                    'formFields.formFieldDateFormat',
                ])->find($id);

                if (isset(self::$cache['groups'])) {
                    self::$cache['groups'][$id] = self::$preloadedComponents[$cacheKey];
                }
                break;
        }
    }

    /**
     * Get component data
     */
    public static function getComponentData(string $componentType, int $id)
    {
        if (self::$isFullyLoaded) {
            switch ($componentType) {
                case 'field':
                    return self::$cache['fields'][$id] ?? null;
                case 'group':
                    return self::$cache['groups'][$id] ?? null;
            }
        }

        $cacheKey = "{$componentType}_{$id}";

        if (!isset(self::$preloadedComponents[$cacheKey])) {
            self::preloadComponentData($componentType, $id);
        }

        return self::$preloadedComponents[$cacheKey] ?? null;
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

    public static function isFullyLoaded(): bool
    {
        return self::$isFullyLoaded;
    }
}
