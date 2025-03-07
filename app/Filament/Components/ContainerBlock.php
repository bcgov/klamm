<?php

namespace App\Filament\Components;

use App\Helpers\FormTemplateHelper;
use App\Models\Style;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
                return 'Container | id: ' . ($state['custom_instance_id'] ?? $state['instance_id'] ?? '');
            })
            ->icon('heroicon-o-square-3-stack-3d')
            ->schema([
                Section::make('Container Properties')
                    ->collapsible()
                    ->compact()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Fieldset::make('Container ID')
                                    ->columns(1)
                                    ->columnSpan(2)
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
                                            ->reactive()
                                            ->distinct()
                                            ->visible(fn($get) => $get('customize_instance_id')),
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
                                TextInput::make('visibility')
                                    ->columnSpanFull()
                                    ->label('Visibility'),
                            ]),
                    ]),
                Builder::make('components')
                    ->label('Container Elements')
                    ->addBetweenActionLabel('Insert between elements')
                    ->collapsible()
                    ->collapsed(true)
                    ->blockNumbers(false)
                    ->columnSpan(2)
                    ->cloneable()
                    ->blocks([
                        FormFieldBlock::make(fn($get) => FormTemplateHelper::calculateElementID()),
                        FieldGroupBlock::make(fn($get) => FormTemplateHelper::calculateElementID()),
                    ]),
            ]);
    }
}
