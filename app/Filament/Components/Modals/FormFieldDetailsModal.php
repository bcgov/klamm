<?php

namespace App\Filament\Components\Modals;

use App\Models\FormInstanceField;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

class FormFieldDetailsModal
{
    protected static function getFormFieldAttributes($fieldId): array
    {
        $fields = \App\Helpers\FormDataHelper::get('form_fields');
        $field = $fields->firstWhere('id', $fieldId);

        if (!$field) {
            return [
                'exists' => false,
                'label' => 'Unknown field',
                'mask' => 'None',
                'help_text' => 'None',
                'data_binding' => 'None',
                'data_binding_path' => 'None',
            ];
        }

        return [
            'exists' => true,
            'label' => $field->label ?? 'Unknown field',
            'mask' => $field->mask ?? 'None',
            'help_text' => $field->help_text ?? 'None',
            'data_binding' => $field->data_binding ?? 'None',
            'data_binding_path' => $field->data_binding_path ?? 'None',
        ];
    }

    public static function form(array $state): array
    {
        return [
            Select::make('form_field_id')
                ->label('Form Field')
                ->options(function () {
                    $fields = \App\Helpers\FormDataHelper::get('form_fields');
                    return $fields->mapWithKeys(fn($field) => [
                        $field->id => "{$field->label} | {$field->dataType?->name}"
                    ]);
                })
                ->default($state['form_field_id'] ?? null)
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set) {
                    $set('customize_label', 'default');

                    $fieldId = $get('form_field_id');
                    $fields = \App\Helpers\FormDataHelper::get('form_fields');
                    $field = $fields->firstWhere('id', $fieldId);
                })
                ->preload(),

            Tabs::make('Field Details')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Basic Information')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Fieldset::make('ID & Type')
                                        ->schema([
                                            Placeholder::make('instance_id')
                                                ->label('Default Instance ID')
                                                ->content(fn(Get $get) => $state['instance_id'] ?? 'Unknown'),

                                            TextInput::make('custom_instance_id')
                                                ->label('Custom Instance ID')
                                                ->helperText('Leave empty to use default instance ID')
                                                ->default($state['custom_instance_id'] ?? null),

                                            Placeholder::make('data_type')
                                                ->label('Data Type')
                                                ->content(function (Get $get) {
                                                    $fields = \App\Helpers\FormDataHelper::get('form_fields');
                                                    $fieldId = $get('form_field_id');
                                                    $field = $fields->firstWhere('id', $fieldId);
                                                    return $field && $field->dataType ? $field->dataType->name : 'Unknown';
                                                }),
                                        ]),

                                    Fieldset::make('Label')
                                        ->schema([
                                            Placeholder::make('default_label')
                                                ->label('Default Label')
                                                ->content(function (Get $get) {
                                                    $fieldId = $get('form_field_id');
                                                    if (!$fieldId) {
                                                        return 'Select a form field to see its default label';
                                                    }

                                                    $attributes = self::getFormFieldAttributes($fieldId);
                                                    return $attributes['label'];
                                                }),

                                            Radio::make('customize_label')
                                                ->label('Label Display')
                                                ->options([
                                                    'default' => 'Use Default Label',
                                                    'customize' => 'Use Custom Label',
                                                    'hide' => 'Hide Label',
                                                ])
                                                ->default($state['customize_label'] ?? 'default')
                                                ->live(),

                                            TextInput::make('custom_label')
                                                ->label('Custom Label')
                                                ->default($state['custom_label'] ?? null)
                                                ->visible(fn(Get $get) => $get('customize_label') === 'customize'),
                                        ]),
                                ]),

                            Grid::make(2)
                                ->schema([
                                    Fieldset::make('Help Text')
                                        ->schema([
                                            Placeholder::make('default_help_text')
                                                ->label('Default Help Text')
                                                ->content(function (Get $get) {
                                                    $fieldId = $get('form_field_id');
                                                    if (!$fieldId) {
                                                        return '';
                                                    }

                                                    $attributes = self::getFormFieldAttributes($fieldId);
                                                    return $attributes['help_text'];
                                                }),

                                            Textarea::make('custom_help_text')
                                                ->label('Custom Help Text')
                                                ->helperText('Leave empty to use default help text')
                                                ->default($state['custom_help_text'] ?? null),
                                        ]),

                                    Fieldset::make('Input Mask')
                                        ->schema([
                                            Placeholder::make('default_mask')
                                                ->label('Default Input Mask')
                                                ->content(function (Get $get) {
                                                    $fieldId = $get('form_field_id');
                                                    if (!$fieldId) {
                                                        return '';
                                                    }

                                                    $attributes = self::getFormFieldAttributes($fieldId);
                                                    return $attributes['mask'];
                                                }),

                                            TextInput::make('custom_mask')
                                                ->label('Custom Input Mask')
                                                ->helperText('Leave empty to use default input mask')
                                                ->default($state['custom_mask'] ?? null),
                                        ]),
                                ]),
                        ]),

                    Tab::make('Data Binding')
                        ->icon('heroicon-o-circle-stack')
                        ->schema([
                            Fieldset::make('Data Source')
                                ->schema([
                                    Placeholder::make('default_data_binding')
                                        ->label('Default Data Source')
                                        ->content(function (Get $get) {
                                            $fieldId = $get('form_field_id');
                                            if (!$fieldId) {
                                                return '';
                                            }

                                            $attributes = self::getFormFieldAttributes($fieldId);
                                            return $attributes['data_binding'];
                                        }),

                                    Select::make('custom_data_binding')
                                        ->label('Custom Data Source')
                                        ->options(function () {
                                            $dataSources = \App\Helpers\FormDataHelper::get('form_data_sources');
                                            return $dataSources->pluck('name', 'name');
                                        })
                                        ->searchable()
                                        ->placeholder('Use default data source')
                                        ->default($state['custom_data_binding'] ?? null)
                                        ->helperText('Leave empty to use default data source')
                                        ->preload(),

                                    Placeholder::make('default_data_binding_path')
                                        ->label('Default Data Binding Path')
                                        ->content(function (Get $get) {
                                            $fieldId = $get('form_field_id');
                                            if (!$fieldId) {
                                                return '';
                                            }

                                            $attributes = self::getFormFieldAttributes($fieldId);
                                            return $attributes['data_binding_path'];
                                        }),

                                    Textarea::make('custom_data_binding_path')
                                        ->label('Custom Data Binding Path')
                                        ->helperText('Leave empty to use default data binding path')
                                        ->default($state['custom_data_binding_path'] ?? null),
                                ]),
                        ]),

                    Tab::make('Styles')
                        ->icon('heroicon-o-paint-brush')
                        ->schema([]),

                    Tab::make('Validation')
                        ->icon('heroicon-o-magnifying-glass-plus')
                        ->schema([]),

                    Tab::make('Conditionals')
                        ->icon('heroicon-o-link')
                        ->schema([]),
                ]),

            Hidden::make('id')
                ->default($state['id'] ?? null),
        ];
    }

    public static function action(array $data): void
    {
        $formInstanceFieldId = $data['id'] ?? null;
        if (!$formInstanceFieldId) {
            return;
        }

        $formInstanceField = FormInstanceField::where('id', $formInstanceFieldId)->first();
        if (!$formInstanceField) {
            return;
        }

        $formInstanceField->form_field_id = $data['form_field_id'];

        $formInstanceField->customize_label = $data['customize_label'] ?? 'default';
        $formInstanceField->custom_label = $data['customize_label'] === 'customize' ? ($data['custom_label'] ?? null) : null;

        $formInstanceField->custom_instance_id = !empty($data['custom_instance_id']) ? $data['custom_instance_id'] : null;

        $formInstanceField->custom_help_text = !empty($data['custom_help_text']) ? $data['custom_help_text'] : null;
        $formInstanceField->custom_data_binding = !empty($data['custom_data_binding']) ? $data['custom_data_binding'] : null;
        $formInstanceField->custom_data_binding_path = !empty($data['custom_data_binding_path']) ? $data['custom_data_binding_path'] : null;
        $formInstanceField->custom_mask = !empty($data['custom_mask']) ? $data['custom_mask'] : null;

        $formInstanceField->save();

        Notification::make()
            ->title('Form field updated')
            ->success()
            ->send();
    }
}
