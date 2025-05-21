<?php

namespace App\Filament\Components;

use App\Models\FormVersion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class FormVersionMetadata
{
    public static function schema(): array
    {
        return [
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
        ];
    }
}
