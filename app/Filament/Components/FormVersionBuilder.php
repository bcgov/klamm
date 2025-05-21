<?php

namespace App\Filament\Components;

use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;
use App\Models\Container;
use App\Helpers\FormTemplateHelper;
use App\Helpers\FormDataHelper;
use App\Helpers\FormVersionHelper;
use App\Filament\Components\FormVersionMetadata;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Session;
use Filament\Forms\Components\Select;
use App\Filament\Components\Modals\FormFieldDetailsModal;
use App\Filament\Components\Modals\FieldGroupDetailsModal;
use App\Filament\Components\Modals\ContainerDetailsModal;
use Filament\Support\Enums\MaxWidth;

class FormVersionBuilder
{
    public static function schema(): Grid
    {
        FormDataHelper::load();

        return Grid::make()
            ->schema([
                ...FormVersionMetadata::schema(),

                Builder::make('components')
                    ->label('Form Elements')
                    ->addActionLabel('Add to Form Elements')
                    ->addBetweenActionLabel('Insert between elements')
                    ->columnSpan(2)
                    ->collapsible(false)
                    ->reorderableWithButtons()
                    ->reorderableWithDragAndDrop(false)
                    ->blockNumbers(false)
                    ->cloneable()
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        Session::put('elementCounter', FormVersionHelper::getHighestID($get('components') ?? []) + 1);
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

    protected static function simplifiedFormFieldBlock(): Block
    {
        return Block::make('form_field')
            ->label(fn(?array $state) => FormVersionHelper::getFormFieldLabel($state))
            ->icon('heroicon-o-document-text')
            ->schema([
                Actions::make([
                    Action::make('details')
                        ->label('Details')
                        ->icon('heroicon-o-information-circle')
                        ->button()
                        ->outlined()
                        ->modalHeading('Form Field Details')
                        ->modalIcon('heroicon-o-document-text')
                        ->modalWidth(MaxWidth::FiveExtraLarge)
                        ->modalSubmitActionLabel('Save Form Field Details')
                        ->form(function (array $state) {
                            return FormFieldDetailsModal::form($state);
                        })
                        ->action(function (array $data, $livewire) {
                            FormFieldDetailsModal::action($data);
                        }),
                ]),
            ]);
    }

    protected static function simplifiedFieldGroupBlock(): Block
    {
        return Block::make('field_group')
            ->label(fn(?array $state) => FormVersionHelper::getFieldGroupLabel($state))
            ->icon('heroicon-o-table-cells')
            ->schema([
                Actions::make([
                    Action::make('details')
                        ->label('Details')
                        ->icon('heroicon-o-information-circle')
                        ->button()
                        ->outlined()
                        ->modalHeading('Field Group Details')
                        ->modalIcon('heroicon-o-table-cells')
                        ->modalWidth(MaxWidth::FiveExtraLarge)
                        ->modalSubmitActionLabel('Save Field Group Details')
                        ->form(function (array $state) {
                            return FieldGroupDetailsModal::form($state);
                        })
                        ->action(function (array $data, $livewire) {
                            FieldGroupDetailsModal::action($data);
                        }),
                ]),
            ]);
    }

    protected static function simplifiedContainerBlock(): Block
    {
        return Block::make('container')
            ->label(fn(?array $state) => FormVersionHelper::getContainerLabel($state))
            ->icon('heroicon-o-cube')
            ->schema([
                Actions::make([
                    Action::make('details')
                        ->label('Details')
                        ->icon('heroicon-o-information-circle')
                        ->button()
                        ->outlined()
                        ->modalHeading('Container Details')
                        ->modalIcon('heroicon-o-cube')
                        ->modalWidth(MaxWidth::FiveExtraLarge)
                        ->modalSubmitActionLabel('Save Container Details')
                        ->form(function (array $state) {
                            return ContainerDetailsModal::form($state);
                        })
                        ->action(function (array $data, $livewire) {
                            ContainerDetailsModal::action($data);
                        }),
                ]),
            ]);
    }
}
