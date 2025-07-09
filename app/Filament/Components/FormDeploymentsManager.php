<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use App\Models\FormDeployment;
use App\Models\FormBuilding\FormVersion;
use Filament\Support\Enums\Alignment;
use Filament\Resources\Pages\ViewRecord;
use Carbon\Carbon;

class FormDeploymentsManager
{
    public static function schema()
    {
        return Section::make('Deployments')
            ->description('Manage form version deployments across environments')
            ->icon('heroicon-o-rocket-launch')
            ->collapsible()
            ->collapsed()
            ->schema([
                Grid::make(3)
                    ->schema([
                        // Test Environment
                        Section::make('Test Environment')
                            ->icon('heroicon-o-beaker')
                            ->compact()
                            ->schema([
                                Placeholder::make('test_deployment_info')
                                    ->label('')
                                    ->content(function ($record) {
                                        if (!$record) return 'No deployment details yet';

                                        $deployment = FormDeployment::getDeploymentForFormAndEnvironment($record->id, 'test');
                                        if (!$deployment) {
                                            return 'No deployment details yet';
                                        }

                                        return "Version {$deployment->formVersion->version_number} deployed at {$deployment->deployed_at->format('M j, Y g:i A')}";
                                    })
                                    ->extraAttributes(['class' => 'text-sm']),
                                Actions::make([
                                    Action::make('deploy_to_test')
                                        ->label('Deploy')
                                        ->icon('heroicon-o-rocket-launch')
                                        ->color('warning')
                                        ->size('sm')
                                        ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
                                        ->form([
                                            Select::make('form_version_id')
                                                ->label('Form Version')
                                                ->options(function ($record) {
                                                    if (!$record) return [];
                                                    return $record->formVersions()
                                                        ->whereIn('status', ['approved', 'published'])
                                                        ->get()
                                                        ->pluck('version_number', 'id')
                                                        ->map(fn($version) => "Version {$version}")
                                                        ->toArray();
                                                })
                                                ->required(),
                                            DateTimePicker::make('deployed_at')
                                                ->label('Deployment Date & Time')
                                                ->default(now())
                                                ->required(),
                                        ])
                                        ->action(function (array $data, $record) {
                                            FormDeployment::deployToEnvironment(
                                                $data['form_version_id'],
                                                'test',
                                                Carbon::parse($data['deployed_at'])
                                            );
                                        })
                                        ->successNotificationTitle('Successfully deployed to Test environment'),
                                ])
                                    ->alignment(Alignment::Left),
                            ]),

                        // Dev Environment
                        Section::make('Development Environment')
                            ->icon('heroicon-o-cog')
                            ->compact()
                            ->schema([
                                Placeholder::make('dev_deployment_info')
                                    ->label('')
                                    ->content(function ($record) {
                                        if (!$record) return 'No deployment details yet';

                                        $deployment = FormDeployment::getDeploymentForFormAndEnvironment($record->id, 'dev');
                                        if (!$deployment) {
                                            return 'No deployment details yet';
                                        }

                                        return "Version {$deployment->formVersion->version_number} deployed at {$deployment->deployed_at->format('M j, Y g:i A')}";
                                    })
                                    ->extraAttributes(['class' => 'text-sm']),
                                Actions::make([
                                    Action::make('deploy_to_dev')
                                        ->label('Deploy')
                                        ->icon('heroicon-o-rocket-launch')
                                        ->color('info')
                                        ->size('sm')
                                        ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
                                        ->form([
                                            Select::make('form_version_id')
                                                ->label('Form Version')
                                                ->options(function ($record) {
                                                    if (!$record) return [];
                                                    return $record->formVersions()
                                                        ->whereIn('status', ['approved', 'published'])
                                                        ->get()
                                                        ->pluck('version_number', 'id')
                                                        ->map(fn($version) => "Version {$version}")
                                                        ->toArray();
                                                })
                                                ->required(),
                                            DateTimePicker::make('deployed_at')
                                                ->label('Deployment Date & Time')
                                                ->default(now())
                                                ->required(),
                                        ])
                                        ->action(function (array $data, $record) {
                                            FormDeployment::deployToEnvironment(
                                                $data['form_version_id'],
                                                'dev',
                                                Carbon::parse($data['deployed_at'])
                                            );
                                        })
                                        ->successNotificationTitle('Successfully deployed to Development environment'),
                                ])
                                    ->alignment(Alignment::Left),
                            ]),

                        // Production Environment
                        Section::make('Production Environment')
                            ->icon('heroicon-o-globe-alt')
                            ->compact()
                            ->schema([
                                Placeholder::make('prod_deployment_info')
                                    ->label('')
                                    ->content(function ($record) {
                                        if (!$record) return 'No deployment details yet';

                                        $deployment = FormDeployment::getDeploymentForFormAndEnvironment($record->id, 'prod');
                                        if (!$deployment) {
                                            return 'No deployment details yet';
                                        }

                                        return "Version {$deployment->formVersion->version_number} deployed at {$deployment->deployed_at->format('M j, Y g:i A')}";
                                    })
                                    ->extraAttributes(['class' => 'text-sm']),
                                Actions::make([
                                    Action::make('deploy_to_prod')
                                        ->label('Deploy')
                                        ->icon('heroicon-o-rocket-launch')
                                        ->color('success')
                                        ->size('sm')
                                        ->visible(fn($livewire) => !($livewire instanceof ViewRecord))
                                        ->form([
                                            Select::make('form_version_id')
                                                ->label('Form Version')
                                                ->options(function ($record) {
                                                    if (!$record) return [];
                                                    return $record->formVersions()
                                                        ->whereIn('status', ['approved', 'published'])
                                                        ->get()
                                                        ->pluck('version_number', 'id')
                                                        ->map(fn($version) => "Version {$version}")
                                                        ->toArray();
                                                })
                                                ->required(),
                                            DateTimePicker::make('deployed_at')
                                                ->label('Deployment Date & Time')
                                                ->default(now())
                                                ->required(),
                                        ])
                                        ->action(function (array $data, $record) {
                                            FormDeployment::deployToEnvironment(
                                                $data['form_version_id'],
                                                'prod',
                                                Carbon::parse($data['deployed_at'])
                                            );
                                        })
                                        ->successNotificationTitle('Successfully deployed to Production environment')
                                        ->requiresConfirmation()
                                        ->modalHeading('Deploy to Production')
                                        ->modalDescription('Are you sure you want to deploy this version to Production? This will replace any existing deployment in Production.')
                                        ->modalSubmitActionLabel('Deploy to Production'),
                                ])
                                    ->alignment(Alignment::Left),
                            ]),
                    ]),
            ]);
    }
}
