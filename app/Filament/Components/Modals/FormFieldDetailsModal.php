<?php

namespace App\Filament\Components\Modals;

use App\Helpers\FormDataHelper;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;

class FormFieldDetailsModal
{
    public static function getSchema(): array
    {
        return [
            // Make the form field dropdown editable
            Select::make('form_field_id')
                ->label('Form Field')
                ->options(function () {
                    $fields = FormDataHelper::get('form_fields');
                    return $fields->mapWithKeys(fn($field) => [
                        $field->id => "{$field->label} | {$field->dataType?->name} | name: {$field->name}"
                    ]);
                })
                ->searchable()
                ->required()
                ->live()
                ->preload()
                ->columnSpan(2),

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
                                                ->label('Instance ID')
                                                ->content(fn(Get $get) => $get('instance_id')),

                                            Placeholder::make('data_type')
                                                ->label('Data Type')
                                                ->content(function (Get $get) {
                                                    $fields = FormDataHelper::get('form_fields');
                                                    $field = $fields->get($get('form_field_id'));
                                                    return $field && $field->dataType ? $field->dataType->name : 'Unknown';
                                                }),
                                        ]),

                                    // Make display properties editable
                                    Fieldset::make('Display')
                                        ->schema([
                                            Toggle::make('customize_label')
                                                ->label('Customize Label')
                                                ->default(function (Get $get) {
                                                    return !empty($get('custom_label'));
                                                })
                                                ->live(),

                                            TextInput::make('custom_label')
                                                ->label('Custom Label')
                                                ->visible(fn(Get $get) => $get('customize_label'))
                                                ->default(function (Get $get) {
                                                    return $get('custom_label');
                                                }),

                                            Toggle::make('customize_help_text')
                                                ->label('Customize Help Text')
                                                ->default(function (Get $get) {
                                                    return !empty($get('custom_help_text'));
                                                })
                                                ->live(),

                                            Textarea::make('custom_help_text')
                                                ->label('Custom Help Text')
                                                ->visible(fn(Get $get) => $get('customize_help_text'))
                                                ->default(function (Get $get) {
                                                    return $get('custom_help_text');
                                                }),

                                            Placeholder::make('original_label')
                                                ->label('Original Label')
                                                ->content(function (Get $get) {
                                                    $fields = FormDataHelper::get('form_fields');
                                                    $field = $fields->get($get('form_field_id'));
                                                    return $field ? $field->label : 'No label';
                                                }),

                                            Placeholder::make('original_help_text')
                                                ->label('Original Help Text')
                                                ->content(function (Get $get) {
                                                    $fields = FormDataHelper::get('form_fields');
                                                    $field = $fields->get($get('form_field_id'));
                                                    return $field && $field->help_text ? $field->help_text : 'None';
                                                }),
                                        ]),
                                ]),
                        ]),

                    Tab::make('Data Binding')
                        ->icon('heroicon-o-link')
                        ->schema([
                            Fieldset::make('Data Source')
                                ->schema([
                                    Toggle::make('customize_data_binding')
                                        ->label('Customize Data Source')
                                        ->default(function (Get $get) {
                                            return !empty($get('custom_data_binding'));
                                        })
                                        ->live(),

                                    Select::make('custom_data_binding')
                                        ->label('Custom Data Source')
                                        ->options(function () {
                                            $dataSources = FormDataHelper::get('form_data_sources');
                                            return $dataSources->pluck('name', 'name');
                                        })
                                        ->visible(fn(Get $get) => $get('customize_data_binding'))
                                        ->default(function (Get $get) {
                                            return $get('custom_data_binding');
                                        }),

                                    Toggle::make('customize_data_binding_path')
                                        ->label('Customize Data Binding Path')
                                        ->default(function (Get $get) {
                                            return !empty($get('custom_data_binding_path'));
                                        })
                                        ->live(),

                                    Textarea::make('custom_data_binding_path')
                                        ->label('Custom Data Binding Path')
                                        ->visible(fn(Get $get) => $get('customize_data_binding_path'))
                                        ->default(function (Get $get) {
                                            return $get('custom_data_binding_path');
                                        }),

                                    Placeholder::make('original_data_binding')
                                        ->label('Original Data Source')
                                        ->content(function (Get $get) {
                                            $fields = FormDataHelper::get('form_fields');
                                            $field = $fields->get($get('form_field_id'));
                                            return $field && $field->data_binding ? $field->data_binding : 'None';
                                        }),

                                    Placeholder::make('original_data_binding_path')
                                        ->label('Original Data Binding Path')
                                        ->content(function (Get $get) {
                                            $fields = FormDataHelper::get('form_fields');
                                            $field = $fields->get($get('form_field_id'));
                                            return $field && $field->data_binding_path ? $field->data_binding_path : 'None';
                                        }),
                                ]),
                        ]),

                    Tab::make('Validation & Options')
                        ->icon('heroicon-o-check-circle')
                        ->schema([
                            Fieldset::make('Validations')
                                ->schema([
                                    TagsInput::make('validations')
                                        ->label('Validation Rules')
                                        ->disabled()
                                        ->default(function (Get $get) {
                                            $fields = FormDataHelper::get('form_fields');
                                            $field = $fields->get($get('form_field_id'));
                                            if (!$field || !$field->validations) {
                                                return [];
                                            }

                                            return $field->validations->map(function ($validation) {
                                                return "{$validation->type}: {$validation->value}";
                                            })->toArray();
                                        }),
                                ]),

                            // Only show select options for fields that have them
                            Fieldset::make('Select Options')
                                ->schema([
                                    TagsInput::make('select_options')
                                        ->label('Available Options')
                                        ->disabled()
                                        ->default(function (Get $get) {
                                            $fields = FormDataHelper::get('form_fields');
                                            $field = $fields->get($get('form_field_id'));

                                            if (!$field || !$field->selectOptionInstances || !in_array($field->dataType?->name, ['radio', 'dropdown'])) {
                                                return [];
                                            }

                                            $selectOptions = FormDataHelper::get('select_options');

                                            return $field->selectOptionInstances->map(function ($instance) use ($selectOptions) {
                                                $option = $selectOptions->get($instance->select_option_id);
                                                return $option ? "{$option->label}: {$option->value}" : null;
                                            })->filter()->toArray();
                                        }),
                                ])
                                ->visible(function (Get $get) {
                                    $fields = FormDataHelper::get('form_fields');
                                    $field = $fields->get($get('form_field_id'));
                                    return $field && $field->dataType && in_array($field->dataType->name, ['radio', 'dropdown']);
                                }),
                        ]),

                    Tab::make('Styling')
                        ->icon('heroicon-o-paint-brush')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Fieldset::make('Web Styles')
                                        ->schema([
                                            TagsInput::make('web_styles')
                                                ->label('Styles')
                                                ->disabled()
                                                ->default(function (Get $get) {
                                                    $fields = FormDataHelper::get('form_fields');
                                                    $field = $fields->get($get('form_field_id'));

                                                    if (!$field || !$field->webStyles) {
                                                        return [];
                                                    }

                                                    return $field->webStyles->pluck('name')->toArray();
                                                }),
                                        ]),

                                    Fieldset::make('PDF Styles')
                                        ->schema([
                                            TagsInput::make('pdf_styles')
                                                ->label('Styles')
                                                ->disabled()
                                                ->default(function (Get $get) {
                                                    $fields = FormDataHelper::get('form_fields');
                                                    $field = $fields->get($get('form_field_id'));

                                                    if (!$field || !$field->pdfStyles) {
                                                        return [];
                                                    }

                                                    return $field->pdfStyles->pluck('name')->toArray();
                                                }),
                                        ]),
                                ]),
                        ]),
                ]),
        ];
    }
}
