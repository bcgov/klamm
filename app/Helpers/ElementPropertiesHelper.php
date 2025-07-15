<?php

namespace App\Helpers;

use Filament\Forms\Components\Placeholder;

class ElementPropertiesHelper
{
    /**
     * Get the Element Properties tab schema for form elements
     *
     * @param string|null $elementType The element type class name
     * @param bool $disabled Whether the schema should be disabled (for view mode)
     * @return array The schema array
     */
    public static function getElementPropertiesSchema(
        ?string $elementType = null,
        bool $disabled = false
    ): array {
        // Handle case where no element type is available
        if (!$elementType) {
            $content = $disabled
                ? 'No specific properties available.'
                : 'Please select an element type in the General tab first.';

            $placeholderKey = $disabled ? 'select_element_type' : 'no_element_type';

            return [
                Placeholder::make($placeholderKey)
                    ->label('')
                    ->content($content)
            ];
        }

        // Check if the element type class exists and has the getFilamentSchema method
        if (class_exists($elementType) && method_exists($elementType, 'getFilamentSchema')) {
            return $elementType::getFilamentSchema($disabled);
        }

        // Fallback for element types that don't have schema defined yet
        return [
            Placeholder::make('no_specific_properties')
                ->label('')
                ->content('This element type has no specific properties defined yet.')
        ];
    }

    /**
     * Get the Element Properties tab schema for create forms (BuildFormVersion)
     *
     * @param string|null $elementType The element type class name
     * @return array The schema array
     */
    public static function getCreateSchema(?string $elementType = null): array
    {
        return self::getElementPropertiesSchema(
            elementType: $elementType,
            disabled: false
        );
    }

    /**
     * Get the Element Properties tab schema for edit forms (FormElementTreeBuilder edit)
     *
     * @param string|null $elementType The element type class name
     * @return array The schema array
     */
    public static function getEditSchema(?string $elementType = null): array
    {
        return self::getElementPropertiesSchema(
            elementType: $elementType,
            disabled: false
        );
    }

    /**
     * Get the Element Properties tab schema for view forms (FormElementTreeBuilder view)
     *
     * @param string|null $elementType The element type class name
     * @return array The schema array
     */
    public static function getViewSchema(?string $elementType = null): array
    {
        return self::getElementPropertiesSchema(
            elementType: $elementType,
            disabled: true
        );
    }
}
