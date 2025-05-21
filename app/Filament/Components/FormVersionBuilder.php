<?php

namespace App\Filament\Components;

use App\Helpers\FormTemplateHelper;
use App\Helpers\FormDataHelper;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Session;
use App\Models\FormVersion;
use Closure;

class FormVersionBuilder
{
    public static function schema(): Grid
    {
        FormDataHelper::load();

        return Grid::make()
            ->schema([
                Select::make('form_id')
                    ->relationship('form', 'form_id_title')
                    ->required()
                    ->reactive()
                    ->preload()
                    ->searchable()
                    ->default(request()->query('form_id_title')),

                Select::make('status')
                    ->options(FormVersion::getStatusOptions())
                    ->required(),

                Section::make('Form Properties')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->compact()
                    ->schema([
                        Fieldset::make('Requester Information')
                            ->schema([
                                TextInput::make('form_requester_name')
                                    ->label('Name'),
                                TextInput::make('form_requester_email')
                                    ->label('Email')
                                    ->email(),
                            ])
                            ->label('Requester Information'),

                        Fieldset::make('Approver Information')
                            ->schema([
                                TextInput::make('form_approver_name')
                                    ->label('Name'),
                                TextInput::make('form_approver_email')
                                    ->label('Email')
                                    ->email(),
                            ])
                            ->label('Approver Information'),

                        Select::make('deployed_to')
                            ->label('Deployed To')
                            ->options([
                                'dev' => 'Development',
                                'test' => 'Testing',
                                'prod' => 'Production',
                            ])
                            ->columnSpan(1)
                            ->nullable()
                            ->afterStateUpdated(fn(callable $set) => $set('deployed_at', now())),

                        DateTimePicker::make('deployed_at')
                            ->label('Deployment Date')
                            ->columnSpan(1),

                        Select::make('form_data_sources')
                            ->multiple()
                            ->preload()
                            ->columnSpan(1)
                            ->relationship('formDataSources', 'name'),

                        Textarea::make('comments')
                            ->label('Comments')
                            ->columnSpanFull()
                            ->maxLength(500),
                    ]),

                Builder::make('components')
                    ->label('Form Elements')
                    ->addActionLabel('Add to Form Elements')
                    ->addBetweenActionLabel('Insert between elements')
                    ->columnSpan(2)
                    ->collapsible()
                    ->collapsed(true)
                    ->reorderableWithButtons()
                    ->blockNumbers(false)
                    ->cloneable()
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        Session::put('elementCounter', self::getHighestID($get('components') ?? []) + 1);
                    })
                    ->blocks([
                        self::simplifiedFormFieldBlock(fn() => FormTemplateHelper::calculateElementID()),
                        self::simplifiedFieldGroupBlock(fn() => FormTemplateHelper::calculateElementID()),
                        self::simplifiedContainerBlock(fn() => FormTemplateHelper::calculateElementID()),
                    ]),

                Hidden::make('all_instance_ids')
                    ->default(fn(Get $get) => $get('all_instance_ids') ?? [])
                    ->dehydrated(fn() => true),

                Actions::make([
                    Action::make('Generate Form Template')
                        ->action(function (Get $get, Set $set) {
                            $formId = $get('id');
                            $jsonTemplate = FormTemplateHelper::generateJsonTemplate($formId);
                            $set('generated_text', $jsonTemplate);
                        })
                        ->hidden(fn($livewire) => !($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
                ]),

                Textarea::make('generated_text')
                    ->label('Generated Form Template')
                    ->columnSpan(2)
                    ->rows(15)
                    ->hidden(fn($livewire) => !($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
            ]);
    }

    protected static function simplifiedFormFieldBlock(Closure $calculateIDCallback): Block
    {
        return Block::make('form_field')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Form Field';
                }

                $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
                    ? $state['custom_instance_id']
                    : $state['instance_id'] ?? 'unknown';

                $label = 'Unknown Field';
                $dataType = 'unknown';

                if (isset($state['formField']) && isset($state['formField']['label'])) {
                    $label = $state['formField']['label'];

                    if (isset($state['formField']['data_type']) && isset($state['formField']['data_type']['name'])) {
                        $dataType = $state['formField']['data_type']['name'];
                    }
                } else {
                    $formFields = FormDataHelper::get('form_fields');
                    $fieldId = $state['form_field_id'] ?? null;
                    $field = $formFields->firstWhere('id', $fieldId);

                    if ($field) {
                        $label = $field->label;
                        $dataType = $field->dataType->name ?? 'unknown';
                    }
                }

                if (!empty($state['customize_label']) && $state['customize_label'] === 'customize' && !empty($state['custom_label'])) {
                    $label = $state['custom_label'];
                }

                return "$label | $dataType | ID: $id";
            })
            ->icon('heroicon-o-document-text')
            ->schema([
                Hidden::make('instance_id')
                    ->default($calculateIDCallback),

                Select::make('form_field_id')
                    ->label('Field')
                    ->options(function () {
                        $formFields = FormDataHelper::get('form_fields');
                        return $formFields->pluck('label', 'id')->toArray();
                    })
                    ->getSearchResultsUsing(function (string $search) {
                        $formFields = FormDataHelper::get('form_fields');
                        return $formFields
                            ->filter(function ($field) use ($search) {
                                return stripos($field->label, $search) !== false;
                            })
                            ->pluck('label', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                TextInput::make('custom_instance_id')
                    ->label('Custom ID')
                    ->alphaNum(),

                Hidden::make('customize_instance_id')
                    ->default(fn(Get $get) => !empty($get('custom_instance_id'))),

                Hidden::make('custom_label')
                    ->default(''),

                Hidden::make('customize_label')
                    ->default(''),
            ]);
    }

    protected static function simplifiedFieldGroupBlock(Closure $calculateIDCallback): Block
    {
        return Block::make('field_group')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Field Group';
                }

                $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
                    ? $state['custom_instance_id']
                    : $state['instance_id'] ?? 'unknown';

                $label = 'Unknown Group';

                if (isset($state['fieldGroup']) && isset($state['fieldGroup']['label'])) {
                    $label = $state['fieldGroup']['label'];
                } else {
                    $fieldGroups = FormDataHelper::get('field_groups');
                    $groupId = $state['field_group_id'] ?? null;
                    $group = $fieldGroups->firstWhere('id', $groupId);

                    if ($group) {
                        $label = $group->label;
                    }
                }

                if (!empty($state['customize_group_label']) && $state['customize_group_label'] === 'customize' && !empty($state['custom_group_label'])) {
                    $label = $state['custom_group_label'];
                }

                return "$label | group | ID: $id";
            })
            ->icon('heroicon-o-table-cells')
            ->schema([
                Hidden::make('instance_id')
                    ->default($calculateIDCallback),

                Select::make('field_group_id')
                    ->label('Field Group')
                    ->options(function () {
                        $fieldGroups = FormDataHelper::get('field_groups');
                        return $fieldGroups->pluck('label', 'id')->toArray();
                    })
                    ->getSearchResultsUsing(function (string $search) {
                        $fieldGroups = FormDataHelper::get('field_groups');

                        return $fieldGroups
                            ->filter(function ($group) use ($search) {
                                return stripos($group->label, $search) !== false;
                            })
                            ->pluck('label', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                TextInput::make('custom_instance_id')
                    ->label('Custom ID')
                    ->alphaNum(),

                Hidden::make('customize_instance_id')
                    ->default(fn(Get $get) => !empty($get('custom_instance_id'))),

                Hidden::make('custom_group_label')
                    ->default(''),

                Hidden::make('customize_group_label')
                    ->default(''),

                Hidden::make('form_fields')
                    ->default([]),
            ]);
    }

    protected static function simplifiedContainerBlock(Closure $calculateIDCallback): Block
    {
        return Block::make('container')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Container';
                }

                $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
                    ? $state['custom_instance_id']
                    : $state['instance_id'] ?? 'unknown';

                return "Container | ID: $id";
            })
            ->icon('heroicon-o-square-3-stack-3d')
            ->schema([
                Hidden::make('instance_id')
                    ->default($calculateIDCallback),

                TextInput::make('custom_instance_id')
                    ->label('Custom ID')
                    ->alphaNum(),

                Hidden::make('customize_instance_id')
                    ->default(fn(Get $get) => !empty($get('custom_instance_id'))),

                Hidden::make('components')
                    ->default([]),
            ]);
    }

    protected static function getHighestID(array $blocks): int
    {
        $maxID = 0;

        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['data'])) {
                continue;
            }

            if (isset($block['data']['instance_id'])) {
                $idString = $block['data']['instance_id'];
                if (is_string($idString)) {
                    $numericPart = str_replace('element', '', $idString);
                    if (is_numeric($numericPart) && $numericPart > 0) {
                        $id = (int) $numericPart;
                        $maxID = max($maxID, $id);
                    }
                }
            }

            if (isset($block['data']['components']) && is_array($block['data']['components'])) {
                $nestedMaxID = self::getHighestID($block['data']['components']);
                $maxID = max($maxID, $nestedMaxID);
            }

            if (isset($block['data']['form_fields']) && is_array($block['data']['form_fields'])) {
                $nestedMaxID = self::getHighestID($block['data']['form_fields']);
                $maxID = max($maxID, $nestedMaxID);
            }
        }

        return $maxID;
    }
}
