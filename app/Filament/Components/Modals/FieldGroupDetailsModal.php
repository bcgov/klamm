<?php

namespace App\Filament\Components\Modals;

use App\Helpers\FormDataHelper;
use App\Models\FieldGroupInstance;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

class FieldGroupDetailsModal
{
    protected static function getFieldGroupAttributes($groupId): array
    {
        $groups = FormDataHelper::get('field_groups');
        $group = $groups->firstWhere('id', $groupId);

        if (!$group) {
            return [
                'exists' => false,
                'label' => 'Unknown group',
                'data_binding' => 'None',
                'data_binding_path' => 'None',
                'repeater' => false,
                'clear_button' => false,
                'repeater_item_label' => '',
                'webStyles' => [],
                'pdfStyles' => [],
            ];
        }

        $webStyles = [];
        $pdfStyles = [];

        if ($group->webStyles) {
            $webStyles = $group->webStyles->pluck('id')->toArray();
        }

        if ($group->pdfStyles) {
            $pdfStyles = $group->pdfStyles->pluck('id')->toArray();
        }

        return [
            'exists' => true,
            'label' => $group->label ?? 'Unknown group',
            'data_binding' => $group->data_binding ?? 'None',
            'data_binding_path' => $group->data_binding_path ?? 'None',
            'repeater' => $group->repeater ?? false,
            'clear_button' => $group->clear_button ?? false,
            'repeater_item_label' => $group->repeater_item_label ?? '',
            'webStyles' => $webStyles,
            'pdfStyles' => $pdfStyles,
        ];
    }

    public static function form(array $state): array
    {
        $webStyles = [];
        $pdfStyles = [];

        if (!empty($state['id'])) {
            $fieldGroupInstance = FieldGroupInstance::with(['styleInstances.style'])->find($state['id']);
            if ($fieldGroupInstance) {
                foreach ($fieldGroupInstance->styleInstances as $styleInstance) {
                    if ($styleInstance->type === 'web') {
                        $webStyles[] = $styleInstance->style_id;
                    } elseif ($styleInstance->type === 'pdf') {
                        $pdfStyles[] = $styleInstance->style_id;
                    }
                }
            }
        }

        return [
            Select::make('field_group_id')
                ->label('Field Group')
                ->options(function () {
                    $fieldGroups = FormDataHelper::get('field_groups');
                    return $fieldGroups->mapWithKeys(fn($group) => [
                        $group->id => "{$group->label}"
                    ]);
                })
                ->default($state['field_group_id'] ?? null)
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set) {
                    $set('customize_group_label', 'default');

                    $groupId = $get('field_group_id');
                    $groups = FormDataHelper::get('field_groups');
                    $group = $groups->firstWhere('id', $groupId);
                })
                ->preload(),

            Tabs::make('Group Details')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Basic Information')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Fieldset::make('ID')
                                        ->schema([
                                            Placeholder::make('instance_id')
                                                ->label('Default Instance ID')
                                                ->content(fn(Get $get) => $state['instance_id'] ?? 'Unknown'),

                                            TextInput::make('custom_instance_id')
                                                ->label('Custom Instance ID')
                                                ->helperText('Leave empty to use default instance ID')
                                                ->default($state['custom_instance_id'] ?? null),
                                        ]),

                                    Fieldset::make('Label')
                                        ->schema([
                                            Placeholder::make('default_group_label')
                                                ->label('Default Label')
                                                ->content(function (Get $get) {
                                                    $groupId = $get('field_group_id');
                                                    if (!$groupId) {
                                                        return 'Select a field group to see its default label';
                                                    }

                                                    $attributes = self::getFieldGroupAttributes($groupId);
                                                    return $attributes['label'];
                                                }),

                                            Radio::make('customize_group_label')
                                                ->label('Label Display')
                                                ->options([
                                                    'default' => 'Use Default Label',
                                                    'customize' => 'Use Custom Label',
                                                    'hide' => 'Hide Label',
                                                ])
                                                ->default($state['customize_group_label'] ?? 'default')
                                                ->live(),

                                            TextInput::make('custom_group_label')
                                                ->label('Custom Label')
                                                ->default($state['custom_group_label'] ?? null)
                                                ->visible(fn(Get $get) => $get('customize_group_label') === 'customize'),
                                        ]),
                                ]),
                        ]),

                    Tab::make('Properties')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Fieldset::make('Group Properties')
                                        ->schema([
                                            Placeholder::make('default_repeater')
                                                ->label('Default Repeater Setting')
                                                ->content(function (Get $get) {
                                                    $groupId = $get('field_group_id');
                                                    if (!$groupId) {
                                                        return 'Select a field group';
                                                    }

                                                    $attributes = self::getFieldGroupAttributes($groupId);
                                                    return $attributes['repeater'] ? 'Yes' : 'No';
                                                }),

                                            Toggle::make('repeater')
                                                ->label('Make Repeatable')
                                                ->default($state['repeater'] ?? false)
                                                ->live(),

                                            TextInput::make('custom_repeater_item_label')
                                                ->label('Custom Repeater Item Label')
                                                ->default($state['custom_repeater_item_label'] ?? null)
                                                ->visible(fn(Get $get) => $get('repeater')),

                                            Placeholder::make('default_clear_button')
                                                ->label('Default Clear Button Setting')
                                                ->content(function (Get $get) {
                                                    $groupId = $get('field_group_id');
                                                    if (!$groupId) {
                                                        return 'Select a field group';
                                                    }

                                                    $attributes = self::getFieldGroupAttributes($groupId);
                                                    return $attributes['clear_button'] ? 'Yes' : 'No';
                                                }),

                                            Toggle::make('clear_button')
                                                ->label('Show Clear Button')
                                                ->default($state['clear_button'] ?? false)
                                                ->visible(fn(Get $get) => !$get('repeater')),
                                        ]),

                                    Fieldset::make('Visibility')
                                        ->schema([
                                            Textarea::make('visibility')
                                                ->label('Visibility Condition')
                                                ->helperText('JavaScript expression to determine visibility')
                                                ->default($state['visibility'] ?? null)
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(1),
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
                                            $groupId = $get('field_group_id');
                                            if (!$groupId) {
                                                return '';
                                            }

                                            $attributes = self::getFieldGroupAttributes($groupId);
                                            return $attributes['data_binding'] ?? 'None';
                                        }),

                                    Select::make('custom_data_binding')
                                        ->label('Custom Data Source')
                                        ->options(function () {
                                            $dataSources = FormDataHelper::get('form_data_sources');
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
                                            $groupId = $get('field_group_id');
                                            if (!$groupId) {
                                                return '';
                                            }

                                            $attributes = self::getFieldGroupAttributes($groupId);
                                            return $attributes['data_binding_path'] ?? 'None';
                                        }),

                                    Textarea::make('custom_data_binding_path')
                                        ->label('Custom Data Binding Path')
                                        ->helperText('Leave empty to use default data binding path')
                                        ->default($state['custom_data_binding_path'] ?? null),
                                ]),
                        ]),

                    Tab::make('Styles')
                        ->icon('heroicon-o-paint-brush')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Fieldset::make('Web Styles')
                                        ->schema([
                                            Placeholder::make('default_web_styles')
                                                ->label('Default Web Styles')
                                                ->content(function (Get $get) {
                                                    $groupId = $get('field_group_id');
                                                    if (!$groupId) {
                                                        return 'Select a field group to see its default web styles';
                                                    }

                                                    $fieldGroup = \App\Models\FieldGroup::with(['webStyles'])->find($groupId);
                                                    if (!$fieldGroup || $fieldGroup->webStyles->isEmpty()) {
                                                        return 'None';
                                                    }

                                                    return $fieldGroup->webStyles->pluck('name')->implode(', ');
                                                }),

                                            Select::make('web_styles')
                                                ->label('Custom Web Styles')
                                                ->options(function () {
                                                    $styles = FormDataHelper::get('styles')
                                                        ->pluck('name', 'id');
                                                    return $styles;
                                                })
                                                ->multiple()
                                                ->searchable()
                                                ->preload()
                                                ->placeholder('Select custom web styles')
                                                ->default($webStyles)
                                                ->helperText('Select styles to override default web styles'),
                                        ]),

                                    Fieldset::make('PDF Styles')
                                        ->schema([
                                            Placeholder::make('default_pdf_styles')
                                                ->label('Default PDF Styles')
                                                ->content(function (Get $get) {
                                                    $groupId = $get('field_group_id');
                                                    if (!$groupId) {
                                                        return 'Select a field group to see its default PDF styles';
                                                    }

                                                    $fieldGroup = \App\Models\FieldGroup::with(['pdfStyles'])->find($groupId);
                                                    if (!$fieldGroup || $fieldGroup->pdfStyles->isEmpty()) {
                                                        return 'None';
                                                    }

                                                    return $fieldGroup->pdfStyles->pluck('name')->implode(', ');
                                                }),

                                            Select::make('pdf_styles')
                                                ->label('Custom PDF Styles')
                                                ->options(function () {
                                                    $styles = FormDataHelper::get('styles')
                                                        ->pluck('name', 'id');
                                                    return $styles;
                                                })
                                                ->multiple()
                                                ->searchable()
                                                ->preload()
                                                ->placeholder('Select custom PDF styles')
                                                ->default($pdfStyles)
                                                ->helperText('Select styles to override default PDF styles'),
                                        ]),
                                ]),
                        ]),
                ]),

            Hidden::make('id')
                ->default($state['id'] ?? null),
        ];
    }

    public static function action(array $data): void
    {
        $fieldGroupInstanceId = $data['id'] ?? null;
        if (!$fieldGroupInstanceId) {
            return;
        }

        $fieldGroupInstance = FieldGroupInstance::where('id', $fieldGroupInstanceId)->first();
        if (!$fieldGroupInstance) {
            return;
        }

        $fieldGroupInstance->field_group_id = $data['field_group_id'];

        $fieldGroupInstance->customize_group_label = $data['customize_group_label'] ?? 'default';
        $fieldGroupInstance->custom_group_label = $data['customize_group_label'] === 'customize' ? ($data['custom_group_label'] ?? null) : null;
        $fieldGroupInstance->custom_instance_id = !empty($data['custom_instance_id']) ? $data['custom_instance_id'] : null;

        $fieldGroupInstance->repeater = $data['repeater'] ?? false;
        $fieldGroupInstance->clear_button = (!$fieldGroupInstance->repeater && isset($data['clear_button'])) ? $data['clear_button'] : false;
        $fieldGroupInstance->custom_repeater_item_label = ($fieldGroupInstance->repeater && !empty($data['custom_repeater_item_label'])) ? $data['custom_repeater_item_label'] : null;
        $fieldGroupInstance->visibility = !empty($data['visibility']) ? $data['visibility'] : null;

        $fieldGroupInstance->custom_data_binding = !empty($data['custom_data_binding']) ? $data['custom_data_binding'] : null;
        $fieldGroupInstance->custom_data_binding_path = !empty($data['custom_data_binding_path']) ? $data['custom_data_binding_path'] : null;

        $fieldGroupInstance->save();

        if (isset($data['web_styles'])) {
            $fieldGroupInstance->styleInstances()->where('type', 'web')->delete();

            foreach ($data['web_styles'] as $styleId) {
                $fieldGroupInstance->styleInstances()->create([
                    'style_id' => $styleId,
                    'type' => 'web',
                ]);
            }
        }

        if (isset($data['pdf_styles'])) {
            $fieldGroupInstance->styleInstances()->where('type', 'pdf')->delete();

            foreach ($data['pdf_styles'] as $styleId) {
                $fieldGroupInstance->styleInstances()->create([
                    'style_id' => $styleId,
                    'type' => 'pdf',
                ]);
            }
        }

        Notification::make()
            ->title('Field group updated')
            ->success()
            ->send();
    }
}
