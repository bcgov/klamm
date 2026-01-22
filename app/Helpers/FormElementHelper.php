<?php

namespace App\Helpers;

use App\Models\FormBuilding\CheckboxGroupFormElement;
use App\Models\FormBuilding\SelectInputFormElement;
use App\Models\FormBuilding\RadioInputFormElement;
use App\Models\FormBuilding\SelectOptionFormElement;

class FormElementHelper
{
    /**
     * Create select options for select/radio elements
     *
     * @param mixed $elementableModel The SelectInputFormElement or RadioInputFormElement instance
     * @param array $optionsData Array of option data with 'label' and 'value' keys
     * @return void
     */
    public static function createSelectOptions($elementableModel, array $optionsData): void
    {
        if (!$elementableModel || empty($optionsData)) {
            return;
        }

        // Check if the model supports options (SelectInputFormElement, RadioInputFormElement, or CheckboxGroupFormElement)
        if (!method_exists($elementableModel, 'options')) {
            return;
        }

        foreach ($optionsData as $index => $optionData) {
            if (empty($optionData['label'])) {
                continue; // Skip options without labels
            }

            $optionData['order'] = $index + 1;

            // Create the option using the existing helper method
            if ($elementableModel instanceof SelectInputFormElement) {
                SelectOptionFormElement::createForSelect($elementableModel, $optionData);
            } elseif ($elementableModel instanceof RadioInputFormElement) {
                SelectOptionFormElement::createForRadio($elementableModel, $optionData);
            } elseif ($elementableModel instanceof CheckboxGroupFormElement) {
                SelectOptionFormElement::createForCheckboxGroup($elementableModel, $optionData);
            }
        }
    }

    /**
     * Update select options for select/radio elements
     * This will delete existing options and create new ones
     *
     * @param mixed $elementableModel The SelectInputFormElement or RadioInputFormElement instance
     * @param array $optionsData Array of option data with 'label' and 'value' keys
     * @return void
     */
    public static function updateSelectOptions($elementableModel, array $optionsData): void
    {
        if (!$elementableModel || !method_exists($elementableModel, 'options')) {
            return;
        }

        // Delete existing options
        $elementableModel->options()->delete();

        // Create new options
        self::createSelectOptions($elementableModel, $optionsData);
    }
}
