<?php

namespace App\Filament\Components\Modals;

use App\Helpers\FormDataHelper;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;

class FieldGroupDetailsModal
{
    public static function getSchema(): array
    {
        return [
            Select::make('field_group_id')
                ->label('Field Group')
                ->options(function () {
                    $fieldGroups = FormDataHelper::get('field_groups');
                    return $fieldGroups->pluck('label', 'id');
                })
                ->searchable()
                ->preload()
                ->disabled(),

            Section::make('Group Details')
                ->schema([
                    Fieldset::make('Properties')
                        ->schema([
                            Placeholder::make('instance_id')
                                ->label('Instance ID')
                                ->content(fn(Get $get) => $get('instance_id')),

                            Placeholder::make('field_group_label')
                                ->label('Label')
                                ->content(function (Get $get) {
                                    $fieldGroups = FormDataHelper::get('field_groups');
                                    $group = $fieldGroups->firstWhere('id', $get('field_group_id'));
                                    return $group ? $group->label : 'No label';
                                }),
                        ]),
                ]),
        ];
    }
}
