<?php

namespace App\Helpers;

use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Actions\Action;

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
            Fieldset::make('Field Label')
                ->schema([
                    TextInput::make('elementable_data.labelText')
                        ->label('Field Label')
                        ->disabled($disabled)
                        ->autocomplete(false)
                        ->suffixAction(
                            Action::make('generate_label_text')
                                ->icon('heroicon-o-arrow-path')
                                ->tooltip('Regenerate from Element Name')
                                ->action(function (callable $set, callable $get) {
                                    $name = $get('name');
                                    if (!empty($name)) {
                                        $set('elementable_data.labelText', $name);
                                    }
                                }),
                        ),
                    SchemaHelper::getEnableVariableSubstitutionToggle($disabled),
                    Toggle::make('elementable_data.hideLabel')
                        ->label('Hide Label')
                        ->default(false)
                        ->disabled($disabled),
                ])
                ->columns(1),
        ];
    }

    /**
     * Get individual Carbon Design System field components
     */
    public static function getPlaceholderTextField(bool $disabled = false)
    {
        return TextInput::make('elementable_data.placeholder')
            ->label('Placeholder Text')
            ->autocomplete(false)
            ->disabled($disabled);

    }

    public static function getLabelTextField(bool $disabled = false)
    {
        return TextInput::make('elementable_data.labelText')
            ->label('Field Label')
            ->suffixAction(
                Action::make('generate_label_text')
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip('Regenerate from Element Name')
                    ->action(function (callable $set, callable $get) {
                        $name = $get('name');
                        if (!empty($name)) {
                            $set('elementable_data.labelText', $name);
                        }
                    }),
            )
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

    public static function getEnableVariableSubstitutionToggle(bool $disabled = false)
    {
        return Toggle::make('elementable_data.enableVarSub')
            ->label('Enable Variable Subsitution')
            ->helperText('Use {{variableName}} syntax in the label to dynamically insert values from other form fields. 
                Make sure you also insert the Moustache library in the Scripts tab and register the variable.')
            ->default(false)
            ->disabled($disabled);
    }
}
