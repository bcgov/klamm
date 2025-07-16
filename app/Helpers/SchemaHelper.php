<?php

namespace App\Helpers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;

class SchemaHelper
{
    /**
     * Get the common Carbon Design System fields for form elements
     * 
     * @param bool $disabled Whether the fields should be disabled
     * @return array Array of Filament form components
     */
    public static function getCommonCarbonFields(bool $disabled = false): array
    {
        return [
            TextInput::make('elementable_data.labelText')
                ->label('Field Label')
                ->disabled($disabled),
            Toggle::make('elementable_data.hideLabel')
                ->label('Hide Label')
                ->default(false)
                ->disabled($disabled),
            TextInput::make('elementable_data.placeholder')
                ->label('Placeholder Text')
                ->disabled($disabled),
            Textarea::make('elementable_data.helperText')
                ->label('Helper Text')
                ->disabled($disabled),
        ];
    }

    /**
     * Get individual Carbon Design System field components
     */
    public static function getLabelTextField(bool $disabled = false)
    {
        return TextInput::make('elementable_data.labelText')
            ->label('Field Label')
            ->disabled($disabled);
    }

    public static function getHideLabelToggle(bool $disabled = false)
    {
        return Toggle::make('elementable_data.hideLabel')
            ->label('Hide Label')
            ->default(false)
            ->live()
            ->disabled($disabled);
    }

    public static function getPlaceholderField(bool $disabled = false)
    {
        return TextInput::make('elementable_data.placeholder')
            ->label('Placeholder Text')
            ->disabled($disabled);
    }

    public static function getHelperTextField(bool $disabled = false)
    {
        return Textarea::make('elementable_data.helperText')
            ->label('Helper Text')
            ->disabled($disabled);
    }
}
