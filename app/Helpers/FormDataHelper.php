<?php

namespace App\Helpers;

use App\Models\FormField;
use App\Models\FieldGroup;
use App\Models\FormDataSource;
use App\Models\SelectOptions;
use App\Models\StyleSheet;
use Illuminate\Support\Facades\Log;

class FormDataHelper
{
    protected static array $cache = [];
    protected static bool $isFullyLoaded = false;

    public static function load(): void
    {
        $routeName = request()->route()?->getName() ?? '';
        $isEditPage = str_contains($routeName, '.edit');
        $isCreatePage = str_contains($routeName, '.create');
        $formId = request()->route('record');

        $needsFullLoad = ($isEditPage || $isCreatePage);

        $fields = collect();
        $query = FormField::with([
            'dataType:id,name',
            'formFieldValue:id,form_field_id,value',
        ])
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

        // First load data minimally
        self::$cache = [
            'fields' => $fields,
            'groups' => $groups,
            'dataSources' => FormDataSource::select(['id', 'name'])->get()->keyBy('id'),
            'selectOptions' => SelectOptions::select(['id', 'label'])->get()->keyBy('id'),
            'styleSheets' => StyleSheet::select(['id', 'name'])->get()->keyBy('id'),
        ];

        // For non-view pages that need full data, load additional relationships
        if ($needsFullLoad && $formId) {
            self::$isFullyLoaded = true;
            self::loadAdditionalRelationships($formId);
        } else {
            self::$isFullyLoaded = false;
        }
    }

    public static function get(string $key)
    {
        if (empty(self::$cache)) {
            self::load();
        }

        return self::$cache[$key] ?? collect();
    }

    public static function refresh(): void
    {
        self::load();
    }

    public static function ensureFullyLoaded(): void
    {
        if (!self::$isFullyLoaded) {
            self::load();
        }
    }

    // Load additional relationships for full data
    protected static function loadAdditionalRelationships(mixed $formId): void
    {
        if (!$formId) {
            return;
        }
        try {
            FormField::with([
                'validations:id,form_field_id,type,value,error_message',
                'selectOptionInstances:id,form_field_id,select_option_id,order',
            ])->chunk(100, function ($fields) use ($formId) {
                foreach ($fields as $field) {
                    if (isset(self::$cache['fields'][$field->id])) {
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
        } catch (\Exception $e) {
            Log::error("Error loading additional relationships: " . $e->getMessage());
        }
    }
}
