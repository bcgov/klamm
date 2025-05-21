<?php

namespace App\Filament\Components;

use App\Models\FormInstanceField;
use App\Helpers\FormTemplateHelper;
use App\Helpers\FormDataHelper;
use App\Helpers\FormVersionHelper;
use App\Filament\Components\FormVersionMetadata;
use App\Filament\Components\Modals\FormFieldDetailsModal;
use App\Filament\Components\Modals\FieldGroupDetailsModal;
use App\Filament\Components\Modals\ContainerDetailsModal;
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
use Filament\Notifications\Actions\Action as NotificationAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Filament\Forms\Components\Select;

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
                        ->modalHeading('Form Field Details')
                        ->modalIcon('heroicon-o-document-text')
                        ->modalSubmitActionLabel('Save Form Field Details')
                        ->form(function (array $state) {
                            return [
                                Select::make('form_field_id')
                                    ->label('Form Field')
                                    ->options(function () {
                                        $fields = FormDataHelper::get('form_fields');
                                        return $fields->mapWithKeys(fn($field) => [
                                            $field->id => "{$field->label} | {$field->dataType?->name}"
                                        ]);
                                    })
                                    ->default($state['form_field_id'] ?? null)
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->preload(),

                                Hidden::make('id')
                                    ->default($state['id'] ?? null),
                            ];
                        })
                        ->action(function (array $data, $livewire) {
                            $formInstanceFieldId = $data['id'] ?? null;
                            if (!$formInstanceFieldId) {
                                return;
                            }

                            $formInstanceField = FormInstanceField::where('id', $formInstanceFieldId)->first();
                            if (!$formInstanceField) {
                                return;
                            }

                            $formInstanceField->form_field_id = $data['form_field_id'];
                            $formInstanceField->save();

                            Notification::make()
                                ->title('Form field updated')
                                ->success()
                                ->send();
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
                        ->modalHeading('Field Group Details')
                        ->modalIcon('heroicon-o-table-cells')
                        ->modalSubmitActionLabel('Save Field Group Details')
                        ->form(fn() => FieldGroupDetailsModal::getSchema()),
                ]),
            ]);
    }

    protected static function simplifiedContainerBlock(): Block
    {
        return Block::make('container')
            ->label(fn(?array $state) => FormVersionHelper::getContainerLabel($state))
            ->schema([
                Actions::make([
                    Action::make('details')
                        ->label('Details')
                        ->icon('heroicon-o-information-circle')
                        ->button()
                        ->modalHeading('Container Details')
                        ->modalIcon('heroicon-o-cube')
                        ->modalSubmitActionLabel('Close')
                        ->form(fn() => ContainerDetailsModal::getSchema()),
                ]),
            ]);
    }
}
