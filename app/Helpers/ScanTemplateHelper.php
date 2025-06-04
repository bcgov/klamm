<?php

namespace App\Helpers;

use App\Models\DataType;
use App\Models\FieldGroup;
use App\Models\Form;
use App\Models\FormDataSource;
use App\Models\FormField;
use App\Models\SelectOptions;

class ScanTemplateHelper
{

    public static function validateForm($string)
    {
        $valid = false;

        $form = json_decode($string, true);
        $jsonErrors = self::validateJSON();

        if ($jsonErrors[0] === "✅ No errors.") {
            $messages = self::validateStructure($form);
            if (str_contains($messages['errors'][0], '✅')) {
                $valid = true;
            }

            $messages = array_merge(
                ["### JSON validation:"],
                ["\n- " . $jsonErrors[0]],
                ["\n### Form structure validation:"],
                $messages['errors'],
                ["\n### Form element validation:"],
                $messages['warnings']
            );

            return ['isValid' => $valid, 'messages' => implode("", $messages)];
        }

        return ['isValid' => $valid, 'messages' => implode("", $jsonErrors)];
    }

    private static function validateJSON()
    {
        // Check JSON for errors. json_decode is run in self::validateForm, which sets value of json_last_error.
        $errors = [];
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $errors[] = '✅ No errors.';
                break;
            case JSON_ERROR_DEPTH:
                $errors[] = '❌ Maximum stack depth exceeded.';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $errors[] = '❌ Underflow or the modes mismatch.';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $errors[] = '❌ Unexpected control character found.';
                break;
            case JSON_ERROR_SYNTAX:
                $errors[] = '❌ Syntax error, malformed JSON.';
                break;
            case JSON_ERROR_UTF8:
                $errors[] = '❌ Malformed UTF-8 characters, possibly incorrectly encoded.';
                break;
            default:
                $errors[] = '❌ Unknown error.';
                break;
        }

        return $errors;
    }

    private static function validateStructure($form)
    {
        $errors = [];
        $warnings = [];
        $instance_ids = [];

        // Check for form_id
        if (!isset($form['form_id'])) {
            $errors[] = "❌ Missing key `form_id`.";
        } else {
            // Check for existing form
            $formTemplate = Form::where('form_id', $form['form_id'])->first();
            if (!$formTemplate) {
                $warnings[] = "⚠️ Form with ID `{$form['form_id']}` not found. Importing will create a new Form.";
                // Check for title
                if (!isset($form['title'])) {
                    $errors[] = "❌ Missing key `title` Cannot create new Form without `form_id` and `title`";
                }
            }
        }

        // Check for dataSources section
        if (isset($form['dataSources'])) {
            foreach ($form['dataSources'] as $index => $source) {
                $num = $index + 1;
                // Check for name
                if (!isset($source['name'])) {
                    $errors[] = "❌ Data source #{$num} is missing `name`.";
                } else {
                    // Check that name is in form_data_sources list
                    $validSources = FormDataSource::all()->pluck('name')->toArray();
                    if (!in_array($source['name'], $validSources)) {
                        $errors[] = "❌ Data source #{$num}: Invalid key `name`: `{$source['name']}`";
                    }
                }
            }
        }

        // Check for data section
        if (!isset($form['data'])) {
            $errors[] = "❌ Missing key `data`.";
            return $errors;
        }

        // Check for items section in data
        if (!isset($form['data']['items'])) {
            $errors[] = "❌ Missing key `items` in `data` section.";
            return $errors;
        }

        // Check each in data
        foreach ($form['data']['items'] as $index => $item) {
            $num = $index + 1;
            // Check for type
            if (!isset($item['type'])) {
                $errors[] = "❌ #{$num}: Missing key `type`.";
            } else {
                // Validate type
                if ($item['type'] === 'container') {
                    $messages = self::validateContainer($item, containerNum: $num);
                    $errors = array_merge($errors, $messages['errors']);
                    $warnings = array_merge($warnings, $messages['warnings']);
                    $instance_ids = array_merge($instance_ids, $messages['instance_ids']);
                } elseif ($item['type'] === 'group') {
                    $messages = self::validateGroup($item, groupNum: $num);
                    $errors = array_merge($errors, $messages['errors']);
                    $warnings = array_merge($warnings, $messages['warnings']);
                    $instance_ids = array_merge($instance_ids, $messages['instance_ids']);
                } else {
                    $messages = self::validateField($item, fieldNum: $num);
                    $errors = array_merge($errors, $messages['errors']);
                    $warnings = array_merge($warnings, $messages['warnings']);
                    $instance_ids = array_merge($instance_ids, $messages['instance_ids']);
                }
            }
        }

        // Check for duplicate ids
        $duplicates = self::find_duplicate_ids($instance_ids);
        if ($duplicates) {
            $errors[] = "❌ Duplicate IDs: ";
            foreach ($duplicates as $index => $id) {
                $num = $index + 1;
                $errors[] = "   ❌ #{$num}: `{$id}`";
            }
        }

        //  Report no errors
        if (count($errors) === 0) {
            $errors[] = '✅ No errors.';
        }

        //  Report no warnings
        if (count($warnings) === 0) {
            $warnings[] = '✅ No warnings.';
        }

        return [
            'errors' => array_map(fn($str) => "\n- " . $str, $errors),
            'warnings' => array_map(fn($str) => "\n- " . $str, $warnings)
        ];
    }

    private static function validateContainer($container, $containerNum)
    {
        $errors = [];
        $warnings = [];
        $instance_ids = [];

        // Check for containerItems section
        if (!isset($container['containerItems'])) {
            $errors[] = "❌ #{$containerNum}: Missing key `containerItems`.";
            return ['errors' => $errors, 'warnings' => $warnings, 'instance_ids' => $instance_ids];
        }

        // Check for id
        if (!isset($container['id'])) {
            $errors[] = "❌ #{$containerNum}: Missing key `id`.";
        } else {
            $instance_ids[] = $container['id'];
        }

        // Check each in containerItems
        foreach ($container['containerItems'] as $index => $item) {
            $num = $index + 1;
            // Check for type
            if (!isset($item['type'])) {
                $errors[] = "❌ #{$containerNum}.{$num}: Missing key `type`.";
            } else {
                // Validate type
                if ($item['type'] === 'container') {
                    $errors[] = "❌ #{$containerNum}.{$num}: Cannot nest container inside container.";
                } elseif ($item['type'] === 'group') {
                    $messages = self::validateGroup($item, groupNum: $num, containerNum: $containerNum);
                    $errors = array_merge($errors, $messages['errors']);
                    $warnings = array_merge($warnings, $messages['warnings']);
                    $instance_ids = array_merge($instance_ids, $messages['instance_ids']);
                } else {
                    $messages = self::validateField($item, fieldNum: $num, containerNum: $containerNum);
                    $errors = array_merge($errors, $messages['errors']);
                    $warnings = array_merge($warnings, $messages['warnings']);
                    $instance_ids = array_merge($instance_ids, $messages['instance_ids']);
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'instance_ids' => $instance_ids];
    }

    private static function validateGroup($group, $groupNum, $containerNum = null)
    {
        $errors = [];
        $warnings = [];
        $instance_ids = [];
        $labelNum = $containerNum ? "{$containerNum}.{$groupNum}" : "{$groupNum}";

        // Check for groupItems section
        if (!isset($group['groupItems'])) {
            $errors[] = "❌ #{$labelNum}: Missing key `groupItems`.";
            return ['errors' => $errors, 'warnings' => $warnings, 'instance_ids' => $instance_ids];
        }

        // Check for fields section in groupItems
        if (!isset($group['groupItems'][0]['fields'])) {
            $errors[] = "❌ #{$labelNum}: Missing key `fields` in `groupItems`.";
            return ['errors' => $errors, 'warnings' => $warnings, 'instance_ids' => $instance_ids];
        }

        // Check for name in codeContext
        if (!isset($group['codeContext']) || !isset($group['codeContext']['name'])) {
            $errors[] = "❌ #{$labelNum}: Missing key `name` in `codeContext`.";
        } else {
            // Check if template group exists
            if (!FieldGroup::where('name', $group['codeContext']['name'])->first()) {
                $warnings[] = "⚠️ #{$labelNum}: `{$group['codeContext']['name']}` does not match any existing group; a customized `generic_group` will be created.";
                // Check if generic group exists
                if (!FieldGroup::where('name', 'generic_group')->first()) {
                    $errors[] = "❌ #{$labelNum}: Cannot create `{$group['codeContext']['name']}` as no `generic_{$group['type']}` exists.";
                }
            }
        }

        // Check for id
        if (!isset($group['id'])) {
            $errors[] = "❌ #{$labelNum}: Missing key `id`.";
        } else {
            $instance_ids[] = $group['id'];
        }

        // Check for repeater
        if (!isset($group['repeater'])) {
            $errors[] = "❌ #{$labelNum}: Missing key `repeater`.";
        } elseif (!is_bool($group['repeater'])) {
            $type = gettype($group['repeater']);
            $errors[] = "❌ #{$labelNum}: `repeater` must be boolean value: found `{$type}`.";
        }

        // Check each in groupItems
        foreach ($group['groupItems'][0]['fields'] as $index => $item) {
            $num = $index + 1;
            // Check for type
            if (!isset($item['type'])) {
                $errors[] = "❌ #{$labelNum}.{$num}: Missing key `type`";
            } else {
                // Validate type
                if ($item['type'] === 'container') {
                    $errors[] = "❌ #{$labelNum}.{$num}: Cannot nest container inside group.";
                } elseif ($item['type'] === 'group') {
                    $errors[] = "❌ #{$labelNum}.{$num}: Cannot nest group inside group.";
                } else {
                    $messages = self::validateField($item, fieldNum: $num, groupNum: $groupNum, containerNum: $containerNum);
                    $errors = array_merge($errors, $messages['errors']);
                    $warnings = array_merge($warnings, $messages['warnings']);
                    $instance_ids = array_merge($instance_ids, $messages['instance_ids']);
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'instance_ids' => $instance_ids];
    }

    private static function validateField($field, $fieldNum, $groupNum = null, $containerNum = null)
    {
        $errors = [];
        $warnings = [];
        $instance_ids = [];
        $labelNum = implode('.', array_filter([$containerNum, $groupNum, $fieldNum]));

        // Check for id
        if (!isset($field['id'])) {
            $errors[] = "❌ #{$labelNum}: Missing key `id`.";
        } else {
            $instance_ids[] = $field['id'];
        }

        // Validate type
        $validTypes = DataType::all()->pluck('name')->toArray();
        if (!in_array($field['type'], $validTypes)) {
            $errors[] = "❌ #{$labelNum}: Invalid key `type`: `{$field['type']}`";
        }

        // Check for name in codeContext
        if (!isset($field['codeContext']) || !isset($field['codeContext']['name'])) {
            $errors[] = "❌ #{$labelNum}: Missing key `name` in `codeContext`.";
        } else {
            // Check if template field exists
            if (!FormField::where('name', $field['codeContext']['name'])->first()) {
                $warnings[] = "⚠️ #{$labelNum}: `{$field['codeContext']['name']}` does not match any existing field; a custom field of type `{$field['type']}` will be created.";
                // Check if generic field of that type exists
                $type = str_replace('-', '_', $field['type']);
                if (!FormField::where('name', "generic_{$type}")->first()) {
                    $errors[] = "❌ #{$labelNum}: Cannot create `{$field['codeContext']['name']}` as no `generic_{$type}` field exists.";
                }
            }
        }

        // Check if SelectOptions exist
        if (isset($field['listItems'])) {
            foreach ($field['listItems'] as $index => $item) {
                $num = $index + 1;
                if (!isset($item['name'])) {
                    $errors[] = "❌ #{$labelNum}: SelectOption #{$num} requires key `name`.";
                } else {
                    $option = SelectOptions::where('name', $item['name'])->first();
                    if (!$option) {
                        $warnings[] = "⚠️ #{$labelNum}: SelectOption #{$num} `{$item['name']}` not found. Importing will create a new SelectOption.";
                    }
                }
                if (!isset($item['text'])) {
                    $errors[] = "❌ #{$labelNum}: SelectOption #{$num} requires key `text`.";
                }
                if (!isset($item['value'])) {
                    $errors[] = "❌ #{$labelNum}: SelectOption #{$num} requires key `value`.";
                }
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'instance_ids' => $instance_ids];
    }

    private static function find_duplicate_ids(array $ids)
    {
        return array_diff_assoc($ids, array_unique($ids));
    }
}
