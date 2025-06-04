<?php

namespace App\Filament\Components;

use App\Helpers\FormDataHelper;
use App\Helpers\UniqueIDsHelper;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class FieldGroupBlock
{
    public static function make(Closure $calculateIDCallback): Block
    {
        $groups = FormDataHelper::get('groups');
        $styles = FormDataHelper::get('styles');
        $dataSources = FormDataHelper::get('dataSources');

        return Block::make('field_group')
            ->label(function (?array $state) use ($groups): string {
                if ($state === null) {
                    return 'Group';
                }
                $group = $groups->get($state['field_group_id']);
                if ($group) {
                    $customLabel = strlen($state['custom_group_label'] ?? '') > 50 ? substr($state['custom_group_label'], 0, 50) . ' ...' : $state['custom_group_label'];
                    $label = ($customLabel ?? $group->label ?? '(no label)')
                        . ' | group '
                        . ' | id: ' . ($state['customize_instance_id'] && !empty($state['custom_instance_id']) ? $state['custom_instance_id'] : $state['instance_id']);
                    return $label;
                }
                return 'New Group | id: ' . $state['instance_id'];
            })
            ->icon('heroicon-o-rectangle-group')
            ->columns(2)
            ->preview('filament.forms.resources.form-resource.components.block-previews.blank')
            ->schema([
                Select::make('field_group_id')
                    ->label('Field Group')
                    ->options(function () use ($groups) {
                        // Compose option labels
                        $options = $groups->pluck('label', 'id');
                        foreach ($options as $id => $option) {
                            $options[$id] = ($option ?? '(no label)') . ' | group | name: ' . ($groups->get($id)->name ?? '');
                        }
                        return $options;
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->columnSpan(2)
                    ->afterStateUpdated(function ($state, callable $set) use ($groups) {
                        $fieldGroup = $groups->get($state);
                        if ($fieldGroup) {
                            $formFields = $fieldGroup->formFields()->get()->map(function ($field) {
                                $validations = $field->validations()->get()->map(function ($validation) {
                                    return [
                                        'type' => $validation->type,
                                        'value' => $validation->value,
                                        'error_message' => $validation->error_message,
                                    ];
                                })->toArray();
                                return [
                                    'type' => 'form_field',
                                    'data' => [
                                        'form_field_id' => $field->id,
                                        'label' => $field->label,
                                        'data_binding_path' => $field->data_binding_path,
                                        'data_binding' => $field->data_binding,
                                        'help_text' => $field->help_text,
                                        'mask' => $field->mask,
                                        'validations' => $validations,
                                        'conditionals' => [],
                                        'instance_id' => UniqueIDsHelper::calculateElementID(),
                                        'customize_label' => 'default',
                                        'customize_group_label' => 'default',
                                    ],
                                ];
                            })->toArray();
                            $set('form_fields', $formFields);
                            $set('repeater', $fieldGroup->repeater);
                            $set('clear_button', $fieldGroup->clear_button);
                            $set('custom_repeater_item_label', $fieldGroup->repeater_item_label);
                        } else {
                            $set('form_fields', []);
                        }
                    }),
                Section::make('Group Properties')
                    ->collapsible()
                    ->collapsed(true)
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
                                            ->dehydrated(false)
                                            ->content(fn($get) => $get('instance_id')), // Set the sequential default value
                                        Hidden::make('instance_id') // used to populate value in template
                                            ->hidden()
                                            ->default($calculateIDCallback), // Set the sequential default value
                                        Toggle::make('customize_instance_id')
                                            ->label('Customize Instance ID')
                                            ->inline()
                                            ->lazy(),
                                        TextInput::make('custom_instance_id')
                                            ->label(false)
                                            ->alphanum()
                                            ->lazy()
                                            ->distinct()
                                            ->alphaNum()
                                            ->rule(fn() => UniqueIDsHelper::uniqueIDsRule())
                                            ->visible(fn($get) => $get('customize_instance_id')),
                                    ]),
                                Fieldset::make('Group Label')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('group_label')
                                            ->label("Default")
                                            ->dehydrated(false)
                                            ->content(fn($get) => $groups->get($get('field_group_id'))->label ?? 'null'),
                                        Radio::make('customize_group_label')
                                            ->options([
                                                'default' => 'Use Default',
                                                'hide' => 'Hide Label',
                                                'customize' => 'Customize Label'
                                            ])
                                            ->default('default')
                                            ->inline()
                                            ->inlineLabel(false)
                                            ->lazy()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if ($state !== 'customize') {
                                                    $set('custom_group_label', null);
                                                }
                                            }),
                                        TextInput::make('custom_group_label')
                                            ->label(false)
                                            ->lazy()
                                            ->visible(fn($get) => $get('customize_group_label') == 'customize'),
                                    ]),
                                Toggle::make('repeater')
                                    ->label('Repeater')
                                    ->lazy()
                                    ->live()
                                    ->columnSpan(fn($get) => $get('repeater') ? 'full' : 1)
                                    ->afterStateUpdated(fn(bool $state, callable $set) => $state && $set('clear_button', false)),
                                Toggle::make('clear_button')
                                    ->label('Clear Button')
                                    ->lazy()
                                    ->live()
                                    ->visible(fn($get) => !$get('repeater'))
                                    ->afterStateUpdated(fn(bool $state, callable $set) => $state && $set('repeater', false)),
                                Fieldset::make('Repeater Item Label')
                                    ->columns(1)
                                    ->visible(fn($get) => $get('repeater'))
                                    ->schema([
                                        Placeholder::make('repeater_item_label')
                                            ->label("Default")
                                            ->dehydrated(false)
                                            ->content(fn($get) => $groups->get($get('field_group_id'))->repeater_item_label ?? 'null'),
                                        Toggle::make('customize_repeater_item_label')
                                            ->label('Customize Repeater Item Label')
                                            ->inline()
                                            ->lazy(),
                                        TextInput::make('custom_repeater_item_label')
                                            ->label(false)
                                            ->visible(fn($get) => $get('customize_repeater_item_label')),
                                    ]),
                                Fieldset::make('Data Bindings')
                                    ->schema([
                                        Fieldset::make('Data Source')
                                            ->columns(1)
                                            ->columnSpan(1)
                                            ->schema([
                                                Placeholder::make('data_binding')
                                                    ->label("Default")
                                                    ->dehydrated(false)
                                                    ->content(fn($get) => $groups->get($get('field_group_id'))->data_binding ?? 'null'),
                                                Toggle::make('customize_data_binding')
                                                    ->label('Customize Data Source')
                                                    ->inline()
                                                    ->lazy(),
                                                Select::make('custom_data_binding')
                                                    ->label(false)
                                                    ->options($dataSources->pluck('name', 'name'))
                                                    ->visible(fn($get) => $get('customize_data_binding')),
                                            ]),
                                        Fieldset::make('Data Binding Path')
                                            ->columns(1)
                                            ->columnSpan(1)
                                            ->schema([
                                                Placeholder::make('data_binding_path')
                                                    ->label("Default")
                                                    ->dehydrated(false)
                                                    ->content(fn($get) => $groups->get($get('field_group_id'))->data_binding_path ?? 'null'),
                                                Toggle::make('customize_data_binding_path')
                                                    ->label('Customize Data Binding Path')
                                                    ->inline()
                                                    ->lazy(),
                                                Textarea::make('custom_data_binding_path')
                                                    ->label(false)
                                                    ->rows(1)
                                                    ->visible(fn($get) => $get('customize_data_binding_path')),
                                            ]),
                                    ]),
                                Textarea::make('visibility')
                                    ->columnSpanFull()
                                    ->label('Visibility'),
                            ]),
                    ]),
                Section::make('Group Elements')
                    ->collapsible()
                    ->collapsed(true)
                    ->compact()
                    ->columnSpan(2)
                    ->schema([
                        Builder::make('form_fields')
                            ->label(false)
                            ->addActionLabel('Add to Group Elements')
                            ->addBetweenActionLabel('Insert between fields')
                            ->cloneable()
                            ->cloneAction(UniqueIDsHelper::cloneElement())
                            ->collapsible()
                            ->collapsed(true)
                            ->blockNumbers(false)
                            ->columnSpan(2)
                            ->blocks([
                                FormFieldBlock::make(fn($get) => UniqueIDsHelper::calculateElementID()),
                            ]),
                    ]),

            ]);
    }
}
