<?php

namespace App\Helpers;

use App\Helpers\FormDataHelper;

class FormVersionHelper
{
    public static function getFormFieldLabel(?array $state): string
    {
        if ($state === null) {
            return 'Form Field';
        }

        $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
            ? $state['custom_instance_id']
            : $state['instance_id'] ?? 'unknown';

        $label = 'Unknown Field';
        $dataType = 'unknown';

        if (isset($state['formField']) && isset($state['formField']['label'])) {
            $label = $state['formField']['label'];

            if (isset($state['formField']['data_type']) && isset($state['formField']['data_type']['name'])) {
                $dataType = $state['formField']['data_type']['name'];
            }
        } else {
            $formFields = FormDataHelper::get('form_fields');
            $fieldId = $state['form_field_id'] ?? null;
            $field = $formFields->firstWhere('id', $fieldId);

            if ($field) {
                $label = $field->label;
                $dataType = $field->dataType->name ?? 'unknown';
            }
        }

        if (!empty($state['customize_label']) && $state['customize_label'] === 'customize' && !empty($state['custom_label'])) {
            $label = $state['custom_label'];
        }

        return "$label | $dataType | ID: $id";
    }

    public static function getFieldGroupLabel(?array $state): string
    {
        if ($state === null) {
            return 'Field Group';
        }

        $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
            ? $state['custom_instance_id']
            : $state['instance_id'] ?? 'unknown';

        $label = 'Unknown Group';

        if (isset($state['fieldGroup']) && isset($state['fieldGroup']['label'])) {
            $label = $state['fieldGroup']['label'];
        } else {
            $fieldGroups = FormDataHelper::get('field_groups');
            $groupId = $state['field_group_id'] ?? null;
            $group = $fieldGroups->firstWhere('id', $groupId);

            if ($group) {
                $label = $group->label;
            }
        }

        if (!empty($state['customize_group_label']) && $state['customize_group_label'] === 'customize' && !empty($state['custom_group_label'])) {
            $label = $state['custom_group_label'];
        }

        return "$label | group | ID: $id";
    }

    public static function getContainerLabel(?array $state): string
    {
        if ($state === null) {
            return 'Container';
        }

        $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
            ? $state['custom_instance_id']
            : $state['instance_id'] ?? 'unknown';

        return "Container | ID: $id";
    }

    public static function getHighestID(array $blocks): int
    {
        $maxID = 0;

        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['data'])) {
                continue;
            }

            if (isset($block['data']['instance_id'])) {
                $idString = $block['data']['instance_id'];
                if (is_string($idString)) {
                    $numericPart = str_replace('element', '', $idString);
                    if (is_numeric($numericPart) && $numericPart > 0) {
                        $id = (int) $numericPart;
                        $maxID = max($maxID, $id);
                    }
                }
            }

            if (isset($block['data']['components']) && is_array($block['data']['components'])) {
                $nestedMaxID = self::getHighestID($block['data']['components']);
                $maxID = max($maxID, $nestedMaxID);
            }

            if (isset($block['data']['form_fields']) && is_array($block['data']['form_fields'])) {
                $nestedMaxID = self::getHighestID($block['data']['form_fields']);
                $maxID = max($maxID, $nestedMaxID);
            }
        }

        return $maxID;
    }
}
