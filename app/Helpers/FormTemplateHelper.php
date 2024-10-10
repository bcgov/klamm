<?php

namespace App\Helpers;

use App\Models\FormInstanceField;
use Illuminate\Support\Str;
use App\Models\SelectOptions;
use App\Models\FormVersion;
use App\Models\FormDataSource;

class FormTemplateHelper
{
    public static function generateJsonTemplate($formVersionId)
    {
        $fields = FormInstanceField::where('form_version_id', $formVersionId)->orderBy('order')->get();
        $formVersion = FormVersion::with('formDataSources')->find($formVersionId);

        $items = $fields->map(function ($field, $index) {
            return self::formatField($field, $index + 1);
        })->all();
        
        return json_encode([
            "version" => "0.0.1",
            "id" => (string) Str::uuid(),
            "lastModified" => now()->toIso8601String(),
            "title" => $formVersion->form->form_title,
            "dataSources" => $formVersion->formDataSources->map(function ($dataSource) {
                return [
                    'name' => $dataSource->name,
                    'source' => $dataSource->source,
                ];
            })->toArray(),           
            "data" => [
                "items" => $items,
                "id" => $formVersionId,
            ],
            "allCssClasses" => [],
        ], JSON_PRETTY_PRINT);
    }

    protected static function formatField($field, $index)
    {
        $base = [
            "type" => $field->formField->dataType->name,
            "id" => (string) $index,
            "label" => $field->formField->label,
            "customLabel" => $field->label,
            "dataBinding" => $field->formField->data_binding,
            "customDataBinding" => $field->data_binding,
            "validation" => $field->formField->validation,
            "customValidation" => $field->validations->map(function ($validation) {
                return [
                    'type' => $validation->type,
                    'value' => $validation->value,
                    'errorMessage' => $validation->error_message,
                ];
            })->toArray(),
            "conditionalLogic" => $field->formField->conditional_logic,
            "customConditionalLogic" => $field->conditional_logic,
            "styles" => $field->formField->styles,
            "customStyles" => $field->styles,
            "codeContext" => [
                "name" => $field->formField->name,
            ],
        ];

        switch ($field->formField->dataType->name) {
            case "text-input":
                return array_merge($base, [
                    "placeholder" => "Enter your {$field->label}",
                    "helperText" => "{$field->label} as it appears on official documents",
                    "inputType" => "text",
                ]);
            case "dropdown":
                return array_merge($base, [
                    "placeholder" => "Select your {$field->label}",
                    "isMulti" => false,
                    "isInline" => false,
                    "selectionFeedback" => "top-after-reopen",
                    "direction" => "bottom",
                    "size" => "md",
                    "helperText" => "Choose one from the list",
                    "listItems" => collect(SelectOptions::where('form_field_id', $field->form_field_id)->get())
                        ->map(function ($selectOption) {
                            return ["text" => $selectOption->label];
                        })
                        ->toArray(),
                ]);
            case "checkbox":
                return array_merge($base, []);
            case "toggle":
                return array_merge($base, [
                    "header" => "Enable {$field->label}",
                    "offText" => "Off",
                    "onText" => "On",
                    "disabled" => false,
                    "checked" => false,
                    "size" => "md",
                ]);
            default:
                return $base;
        }
    }
}
