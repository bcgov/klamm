<?php

namespace App\Filament\Components\Modals;

use App\Models\FormInstanceField;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Notifications\Notification;

class FormFieldDetailsModal
{
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
                ->afterStateUpdated(function (Get $get, $state, $livewire) {
                    // Reset the form when changing the form field
                    $livewire->setFormFieldData('customize_label', 'default');
                })
                ->preload(),

            Placeholder::make('default_label')
                ->label('Default Label')
                ->content(function (Get $get) {
                    $fieldId = $get('form_field_id');
                    if (!$fieldId) {
                        return 'Select a form field to see its default label';
                    }

                    $fields = \App\Helpers\FormDataHelper::get('form_fields');
                    $field = $fields->firstWhere('id', $fieldId);

                    return $field ? $field->label : 'Unknown field';
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
        $formInstanceField->save();

        Notification::make()
            ->title('Form field updated')
            ->success()
            ->send();
    }
}
