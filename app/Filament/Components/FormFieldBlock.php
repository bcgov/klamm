<?php

namespace App\Filament\Components;

use App\Models\Style;
use App\Models\FormField;
use App\Models\FormDataSource;
use App\Models\SelectOptions;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Enums\Alignment;

class FormFieldBlock
{
    public static function make(Closure $calculateIDCallback): Block
    {
        $validationOptions = [
            'minValue' => 'Minimum Value',
            'maxValue' => 'Maximum Value',
            'minLength' => 'Minimum Length',
            'maxLength' => 'Maximum Length',
            'required' => 'Required',
            'email' => 'Email',
            'phone' => 'Phone Number',
            'javascript' => 'JavaScript',
        ];

        $conditionalOptions = [
            'visibility' => 'Visibility',
            'calculatedValue' => 'Calculated Value',
            'saveOnSubmit' => 'Save on Submit',
            'readOnly' => 'Read Only',
        ];

        $selectOptions = fn() => SelectOptions::all()->keyBy('id');

        return Block::make('form_field')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Field';
                }
                $field = FormField::find($state['form_field_id']) ?: null;
                if ($field) {
                    $label = '';
                    if ($state['customize_label'] !== 'hide') {
                        $label .= ($state['custom_label'] ?? $field->label ?? 'null') . ' | ';
                    } else {
                        $label .= '(label hidden) | ';
                    }
                    $label .= ($field->dataType->name ?? '') . ' | id: ';
                    if (!empty($state['customize_instance_id'] ?? null) && !empty($state['custom_instance_id'] ?? null)) {
                        $label .= $state['custom_instance_id'];
                    } else {
                        $label .= $state['instance_id'] ?? 'null';
                    }
                    return $label;
                }
                return 'New Field | id: ' . $state['instance_id'];
            })
            ->icon('heroicon-o-stop')
            ->columns(2)
            ->schema([
                Select::make('form_field_id')
                    ->label('Form Field')
                    ->live()
                    ->options(fn() => FormField::with('dataType')->get()->mapWithKeys(fn($field) => [
                        $field->id => "{$field->label} | {$field->dataType?->name} | name: {$field->name}"
                    ]))
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->columnSpan(2)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $field = FormField::with(['webStyles', 'pdfStyles', 'validations', 'selectOptionInstances'])->find($state);
                        if ($field) {
                            $set('webStyles', $field->webStyles->pluck('id')->toArray());
                            $set('pdfStyles', $field->pdfStyles->pluck('id')->toArray());
                            $set('validations', $field->validations->map(fn($validation) => [
                                'type' => $validation->type,
                                'value' => $validation->value,
                                'error_message' => $validation->error_message,
                            ])->toArray());
                            $set('select_option_instances', $field->selectOptionInstances->map(fn($instance) => [
                                'type' => 'select_option_instance',
                                'data' => [
                                    'select_option_id' => $instance->select_option_id,
                                    'order' => $instance->order,
                                ],
                            ])->toArray());
                        } else {
                            // Reset when no field is selected
                            $set('webStyles', []);
                            $set('pdfStyles', []);
                            $set('validations', []);
                            $set('select_option_instances', []);
                        }
                    }),
                Section::make('Field Properties')
                    ->collapsible()
                    ->compact()
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Fieldset::make('Instance ID')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('instance_id_placeholder') // used to view value in builder
                                            ->label("Default")
                                            ->content(fn($get) => $get('instance_id')), // Set the sequential default value
                                        Hidden::make('instance_id') // used to populate value in template 
                                            ->hidden()
                                            ->default($calculateIDCallback), // Set the sequential default value
                                        Toggle::make('customize_instance_id')
                                            ->label('Customize Instance ID')
                                            ->inline()
                                            ->live(),
                                        TextInput::make('custom_instance_id')
                                            ->label(false)
                                            ->alphanum()
                                            ->lazy()
                                            ->distinct()
                                            ->visible(fn($get) => $get('customize_instance_id')),
                                    ]),
                                Fieldset::make('Label')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('label')
                                            ->label("Default")
                                            ->content(fn($get) => FormField::find($get('form_field_id'))->label ?? 'null'),
                                        Radio::make('customize_label')
                                            ->options([
                                                'default' => 'Use Default',
                                                'hide' => 'Hide Label',
                                                'customize' => 'Customize Label'
                                            ])
                                            ->default('default')
                                            ->inline()
                                            ->inlineLabel(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if ($state !== 'customize') {
                                                    $set('custom_label', null);
                                                }
                                            }),
                                        TextInput::make('custom_label')
                                            ->label(false)
                                            ->lazy()
                                            ->visible(fn($get) => $get('customize_label') == 'customize'),
                                    ]),
                                Fieldset::make('Field Value')
                                    ->visible(fn($get) => FormField::find($get('form_field_id'))?->isValueInputNeededForField() ?? false)
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->schema([
                                        RichEditor::make('field_value')
                                            ->label("Default")
                                            ->toolbarButtons([])
                                            ->disabled()
                                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                                $value = FormField::find($get('form_field_id'))?->formFieldValue?->value ?? '';
                                                $set('field_value', $value);
                                            }),
                                        Toggle::make('customize_field_value')
                                            ->label('Customize Field Value')
                                            ->inline()
                                            ->live(),
                                        RichEditor::make('custom_field_value')
                                            ->label(false)
                                            ->visible(fn($get) => $get('customize_field_value'))
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'link',
                                                'h1',
                                                'h2',
                                                'h3',
                                                'blockquote',
                                                'codeBlock',
                                                'bulletList',
                                                'orderedList',
                                                'undo',
                                                'redo',
                                            ]),
                                    ]),
                                Fieldset::make('Data Binding Path')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('data_binding_path')
                                            ->label("Default")
                                            ->content(fn($get) => FormField::find($get('form_field_id'))->data_binding_path ?? 'null'),
                                        Toggle::make('customize_data_binding_path')
                                            ->label('Customize Data Binding Path')
                                            ->inline()
                                            ->live(),
                                        Textarea::make('custom_data_binding_path')
                                            ->label(false)
                                            ->visible(fn($get) => $get('customize_data_binding_path')),
                                    ]),
                                Fieldset::make('Data Source')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('data_binding')
                                            ->label("Default")
                                            ->content(fn($get) => FormField::find($get('form_field_id'))->data_binding ?? 'null'),
                                        Toggle::make('customize_data_binding')
                                            ->label('Customize Data Source')
                                            ->inline()
                                            ->live(),
                                        Select::make('custom_data_binding')
                                            ->label(false)
                                            ->options(FormDataSource::pluck('name', 'name'))
                                            ->visible(fn($get) => $get('customize_data_binding')),
                                    ]),
                                Fieldset::make('Mask')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('mask')
                                            ->label("Default")
                                            ->content(fn($get) => FormField::find($get('form_field_id'))->mask ?? 'null'),
                                        Toggle::make('customize_mask')
                                            ->label('Customize Mask')
                                            ->inline()
                                            ->live(),
                                        TextInput::make('custom_mask')
                                            ->label(false)
                                            ->visible(fn($get) => $get('customize_mask')),
                                    ]),
                                Fieldset::make('Help Text')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('help_text')
                                            ->label("Default")
                                            ->content(fn($get) => FormField::find($get('form_field_id'))->help_text ?? 'null'),
                                        Toggle::make('customize_help_text')
                                            ->label('Customize Help text')
                                            ->inline()
                                            ->live(),
                                        Textarea::make('custom_help_text')
                                            ->label(false)
                                            ->visible(fn($get) => $get('customize_help_text')),
                                    ]),
                            ]),
                    ]),
                Builder::make('select_option_instances')
                    ->label('Select Option Instances')
                    ->columnSpanFull()
                    ->reorderable()
                    ->blockNumbers(false)
                    ->collapsible()
                    ->collapsed(true)
                    ->live()
                    ->reactive()
                    ->visible(fn($get) => in_array(FormField::find($get('form_field_id'))?->dataType?->name, ['radio', 'dropdown']))
                    ->blocks([
                        Block::make('select_option_instance')
                            ->label(
                                fn(?array $state): string =>
                                isset($state['select_option_id']) && $selectOptions()->has($state['select_option_id'])
                                    ? $selectOptions()[$state['select_option_id']]->label
                                    . ' | ' . $selectOptions()[$state['select_option_id']]->name
                                    . ' | value: ' . $selectOptions()[$state['select_option_id']]->value
                                    : 'New Option'
                            )
                            ->schema([
                                Select::make('select_option_id')
                                    ->label('Option')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->options($selectOptions()->map(function ($option) {
                                        return "{$option->label} | {$option->name} | value: {$option->value}";
                                    })->toArray()),
                            ])
                    ]),

                Select::make('webStyles')
                    ->label('Web Styles')
                    ->options(Style::pluck('name', 'id'))
                    ->multiple()
                    ->preload()
                    ->columnSpan(1)
                    ->live()
                    ->reactive(),
                Select::make('pdfStyles')
                    ->label('PDF Styles')
                    ->options(Style::pluck('name', 'id'))
                    ->multiple()
                    ->preload()
                    ->columnSpan(1)
                    ->live()
                    ->reactive(),
                Repeater::make('validations')
                    ->label('Validations')
                    ->itemLabel(fn($state): ?string => $validationOptions[$state['type']] ?? 'New Validation')
                    ->collapsible()
                    ->collapsed()
                    ->defaultItems(0)
                    ->addActionAlignment(Alignment::Start)
                    ->schema([
                        Select::make('type')
                            ->label('Validation Type')
                            ->options($validationOptions)
                            ->reactive()
                            ->required(),
                        TextInput::make('value')
                            ->label('Value'),
                        TextInput::make('error_message')
                            ->label('Error Message'),
                    ]),
                Repeater::make('conditionals')
                    ->label('Conditionals')
                    ->itemLabel(fn($state): ?string => $conditionalOptions[$state['type']] ?? 'New Conditional')
                    ->collapsible()
                    ->collapsed()
                    ->defaultItems(0)
                    ->addActionAlignment(Alignment::Start)
                    ->schema([
                        Select::make('type')
                            ->label('Conditional Type')
                            ->options($conditionalOptions)
                            ->reactive()
                            ->required(),
                        TextInput::make('value')
                            ->label('Value'),
                    ]),
            ]);
    }
}
