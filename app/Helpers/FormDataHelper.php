<?php

namespace App\Helpers;

use App\Models\FormField;
use App\Models\FieldGroup;
use App\Models\FormDataSource;
use App\Models\SelectOptions;
use App\Models\Style;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class FormDataHelper
{

    protected static array $cache = [];

    protected static array $loadedKeys = [];

    public static function loadAll(): void
    {
        self::$cache = [
            'form_fields' => FormField::with([
                'dataType',
                'webStyles',
                'pdfStyles',
                'validations',
                'selectOptionInstances',
                'formFieldValue',
                'formFieldDateFormat',
            ])->get()->keyBy('id'),
            'field_groups' => FieldGroup::with([])->get()->keyBy('id'),
            'styles' => Style::all()->keyBy('id'),
            'form_data_sources' => FormDataSource::all()->keyBy('id'),
            'select_options' => SelectOptions::all()->keyBy('id'),
        ];

        self::$loadedKeys = array_keys(self::$cache);
    }


    public static function load(): void
    {
        //
    }

    public static function get(string $key): Collection
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        switch ($key) {
            case 'form_fields':
                self::$cache[$key] = FormField::select('id', 'label', 'data_type_id')
                    ->with(['dataType:id,name'])
                    ->get()
                    ->keyBy('id');
                break;

            case 'field_groups':
                self::$cache[$key] = FieldGroup::select('id', 'label')
                    ->get()
                    ->keyBy('id');
                break;

            case 'styles':
                self::$cache[$key] = Style::select('id', 'name', 'property', 'value')
                    ->get()
                    ->keyBy('id');
                break;

            case 'form_data_sources':
                self::$cache[$key] = FormDataSource::select('id', 'name')
                    ->get()
                    ->keyBy('id');
                break;

            case 'select_options':
                self::$cache[$key] = SelectOptions::select('id', 'label', 'value')
                    ->get()
                    ->keyBy('id');
                break;

            default:
                Log::warning("Requested unknown key from FormDataHelper: {$key}");
                return collect();
        }

        self::$loadedKeys[] = $key;
        return self::$cache[$key];
    }

    public static function refresh(): void
    {
        foreach (self::$loadedKeys as $key) {
            unset(self::$cache[$key]);
            self::get($key);
        }
    }

    public static function clearCache(): void
    {
        self::$cache = [];
        self::$loadedKeys = [];
    }
}
