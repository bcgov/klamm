<?php

namespace App\Helpers;

use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Actions\Action;

class SchemaHelper
{
    /**
     * Get the common Carbon Design System fields for form elements
     * 
     * @param bool $disabled Whether the fields should be disabled
     * @param bool $labelRequired Whether the label text field should be required
     * @return array Array of Filament form components
     */
    public static function getCommonCarbonFields(bool $disabled = false, bool $labelRequired = false): Fieldset
    {
        return Fieldset::make('Field Label')
            ->schema([
                self::getLabelTextField($disabled, $labelRequired),
                self::getEnableVariableSubstitutionToggle($disabled),
                self::getHideLabelToggle($disabled),
            ])
            ->columns(1);
    }

    /**
     * Get individual Carbon Design System field components
     */
    public static function getPlaceholderTextField(bool $disabled = false)
    {
        return TextInput::make('elementable_data.placeholder')
            ->label('Placeholder Text')
            ->autocomplete(false)
            ->maxLength(255)
            ->disabled($disabled);
    }

    public static function getLabelTextField(bool $disabled = false, bool $required = false)
    {
        return TextInput::make('elementable_data.labelText')
            ->label('Field Label')
            ->maxLength(255)
            ->disabled($disabled)
            ->required($required)
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
            );
    }

    public static function getHideLabelToggle(bool $disabled = false)
    {
        return Toggle::make('elementable_data.hideLabel')
            ->label('Hide Label')
            ->default(false)
            ->live()
            ->disabled($disabled);
    }

    public static function getEnableVariableSubstitutionToggle(bool $disabled = false)
    {
        return Toggle::make('elementable_data.enableVarSub')
            ->label('Enable Variable Substitution')
            ->helperText('Use {{variableName}} syntax in the label to dynamically insert values from other form fields. 
                Make sure you also insert the Moustache library in the Scripts tab and register the variable.')
            ->default(false)
            ->disabled($disabled);
    }

    public static function getOptionsRepeater(bool $disabled = false, string $label = 'Options'): Repeater
    {
        return Repeater::make('elementable_data.options')
            ->label($label)
            ->schema([
                TextInput::make('label')
                    ->label('Option Label')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2)
                    ->autocomplete(false)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (callable $set, callable $get, $state) {
                        $value = $get('value');
                        if (empty($value) && !empty($state)) {
                            $slug = \Illuminate\Support\Str::slug($state, '-');
                            $set('value', $slug);
                        }
                    }),
                TextInput::make('value')
                    ->label('Option Value')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2)
                    ->suffixAction(
                        Action::make('regenerate_value')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip('Regenerate from Option Label')
                            ->action(function (callable $set, callable $get) {
                                $label = $get('label');
                                if (!empty($label)) {
                                    $slug = \Illuminate\Support\Str::slug($label, '-');
                                    $set('value', $slug);
                                }
                            })
                    ),
            ])
            ->columns(2)
            ->defaultItems(1)
            ->addActionLabel('Add Option')
            ->reorderableWithButtons()
            ->collapsible()
            ->itemLabel(fn(array $state): ?string => $state['label'] ?? 'Option')
            ->disabled($disabled)
            ->minItems(1);
    }

    public static function getOptionsDefaultSelectedSelect(bool $disabled = false, bool $multiple = false, string $label = 'Default Selected Value'): Select
    {
        $select = Select::make('elementable_data.defaultSelected')
            ->label($label)
            ->options(function (callable $get) {
                $options = $get('elementable_data.options') ?? [];
                $selectOptions = [];
                foreach ($options as $option) {
                    if (!empty($option['value'])) {
                        $selectOptions[$option['value']] = $option['label'] ?? $option['value'];
                    }
                }
                return $selectOptions;
            })
            ->disabled($disabled);

        if ($multiple) {
            $select->multiple()->nullable()->live();
        }

        return $select;
    }
}
