<?php

namespace App\Filament\Reports\Resources\ReportEntryResource\Pages;

use App\Filament\Reports\Resources\ReportEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Colors\Color;
use App\Filament\Imports\ReportEntryImporter;
use Filament\Tables\Actions\ImportAction;

class ListReportEntries extends ListRecords
{
    protected static string $resource = ReportEntryResource::class;

    protected static ?string $title = 'Report Label Dictionary';

    protected function getHeaderActions(): array
    {
        return [
            // ImportAction::make('Import CSV')
            //     ->icon('heroicon-o-arrow-up-on-square')
            //     ->color(Color::hex('#013366'))
            //     ->outlined()
            //     ->label('Upload Report Labels')
            //     ->importer(ReportEntryImporter::class),
            Actions\CreateAction::make()
                ->icon('heroicon-o-arrow-down-on-square')
                ->outlined()
                ->color(Color::hex('#013366'))
                ->label('Import Label(s)'),
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->color(Color::hex('#013366'))
                ->label('Create a Label'),
        ];
    }
}
