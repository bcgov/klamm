<?php

namespace App\Helpers;

use App\Models\FormField;
use App\Models\FieldGroup;
use App\Models\FormDataSource;
use App\Models\SelectOptions;
use App\Models\Style;

class FormDataHelper
{
    protected static array $cache = [];

    public static function load(): void
    {
        self::$cache = [
            'fields' => FormField::with([
                'dataType',
                'webStyles',
                'pdfStyles',
                'validations',
                'selectOptionInstances',
                'formFieldValue'
            ])->get()->keyBy('id'),
            'groups' => FieldGroup::with([])->get()->keyBy('id'),
            'styles' => Style::all()->keyBy('id'),
            'dataSources' => FormDataSource::all()->keyBy('id'),
            'selectOptions' => SelectOptions::all()->keyBy('id'),
        ];
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
}
