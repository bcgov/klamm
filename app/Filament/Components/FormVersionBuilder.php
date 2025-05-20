<?php

namespace App\Filament\Components;

use App\Helpers\FormTemplateHelper;
use App\Helpers\FormDataHelper;
use App\Filament\Components\ContainerBlock;
use App\Filament\Components\FieldGroupBlock;
use App\Filament\Components\FormFieldBlock;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;
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


class FormVersionBuilder
{
    public static function schema($livewire = null)
    {

        FormDataHelper::loadMinimal();

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
                Builder::make('components')
                    ->label('Form Elements')
                    ->addActionLabel('Add to Form Elements')
                    ->addBetweenActionLabel('Insert between elements')
                    ->columnSpan(2)
                    ->blockNumbers(false)
                    ->cloneable()
                    ->blockPreviews(fn($livewire) => !($livewire instanceof \Filament\Resources\Pages\ViewRecord))
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        Session::put('elementCounter', self::getHighestID($get('components')) + 1);
                    })
                    ->blocks([
                        FormFieldBlock::make(fn() => FormTemplateHelper::calculateElementID())
                            ->mutateDehydratedStateUsing(function ($state) {
                                // Preload data when block is expanded
                                if (!empty($state['form_field_id'])) {
                                    FormDataHelper::preloadBlockData('field', [$state['form_field_id']]);
                                }
                                return $state;
                            })
                            ->afterStateHydrated(function (Set $set, $state) {
                                if (!empty($state['form_field_id'])) {
                                    FormDataHelper::preloadComponentData('field', $state['form_field_id']);
                                }
                            }),
                        FieldGroupBlock::make(fn() => FormTemplateHelper::calculateElementID())
                            ->mutateDehydratedStateUsing(function ($state) {
                                // Preload data when block is expanded
                                if (!empty($state['field_group_id'])) {
                                    FormDataHelper::preloadBlockData('group', [$state['field_group_id']]);
                                }
                                return $state;
                            })
                            ->afterStateHydrated(function (Set $set, $state) {
                                if (!empty($state['field_group_id'])) {
                                    FormDataHelper::preloadComponentData('group', $state['field_group_id']);
                                }
                            }),
                        ContainerBlock::make(fn() => FormTemplateHelper::calculateElementID()),
                    ]),
                Hidden::make('all_instance_ids')
                    ->default(fn(Get $get) => $get('all_instance_ids') ?? [])
                    ->dehydrated(fn() => true),
            ]);
    }

    protected static function getHighestID(array $blocks): int
    {
        $maxID = 0;
        foreach ($blocks as $block) {
            if (isset($block['data']['instance_id'])) {
                $idString = $block['data']['instance_id'];
                $numericPart = str_replace('element', '', $idString);
                if (is_numeric($numericPart) && $numericPart > 0) {
                    $id = (int) $numericPart;
                    $maxID = max($maxID, $id);
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
