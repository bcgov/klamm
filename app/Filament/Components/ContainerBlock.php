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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ContainerBlock
{
    public static function make(Closure $calculateIDCallback): Block
    {
        return Block::make('container')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Container';
                }
                return 'Container | id: ' . ($state['customize_instance_id'] && !empty($state['custom_instance_id']) ? $state['custom_instance_id'] : $state['instance_id']);
            })
            ->icon('heroicon-o-square-3-stack-3d')
            ->preview('filament.forms.resources.form-resource.components.block-previews.blank')
            ->schema([
                Section::make('Container Properties')
                    ->collapsible()
                    ->collapsed(true)
                    ->compact()
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Fieldset::make('Container ID')
                                    ->columns(1)
                                    ->columnSpan(2)
                                    ->schema([
                                        Placeholder::make('instance_id_placeholder') // used to view value in builder
                                            ->label("Default")
                                            ->dehydrated(false)
                                            ->content(fn($get) => $get('instance_id')), // Set the sequential default value
                                        Hidden::make('instance_id') // used to populate value in template
                                            ->hidden()
                                            ->dehydrated(false)
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
                                Toggle::make('clear_button')
                                    ->label('Clear Button')
                                    ->live()
                                    ->columnSpanFull(),
                                Textarea::make('visibility')
                                    ->columnSpanFull()
                                    ->label('Visibility'),
                            ]),
                    ]),
                Section::make('Container Elements')
                    ->collapsible()
                    ->collapsed(true)
                    ->compact()
                    ->columnSpan(2)
                    ->schema([
                        Builder::make('components')
                            ->label(false)
                            ->addActionLabel('Add to Container Elements')
                            ->addBetweenActionLabel('Insert between elements')
                            ->cloneable()
                            ->cloneAction(UniqueIDsHelper::cloneElement())
                            ->collapsible()
                            ->collapsed(true)
                            ->blockNumbers(false)
                            ->columnSpan(2)
                            ->blocks([
                                FormFieldBlock::make(fn($get) => UniqueIDsHelper::calculateElementID()),
                                FieldGroupBlock::make(fn($get) => UniqueIDsHelper::calculateElementID()),
                            ]),
                    ])
            ]);
    }
}
