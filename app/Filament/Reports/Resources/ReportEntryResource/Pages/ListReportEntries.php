<?php

namespace App\Filament\Reports\Resources\ReportEntryResource\Pages;

use App\Filament\Reports\Resources\ReportEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReportEntries extends ListRecords
{
    protected static string $resource = ReportEntryResource::class;

    protected static ?string $title = 'Report Label Dictionary';

    protected ?string $subheading = 'A dictionary of all report labels, designed to standardize labels and streamline future report requirements for financial components.';


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('Create Dictionary Entry'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            'https://knowledge.social.gov.bc.ca/successor/financial_components/product-team/agreements-backlog/problem-solving/report-dictionary' => 'Link to our Problem Statement',
        ];
    }
}
