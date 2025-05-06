<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReportBusinessArea;
use App\Models\Report;
use App\Models\ReportDictionaryLabel;
use App\Models\ReportLabelSource;
use App\Models\ReportEntry;
use League\Csv\Reader;

class ImportReportsFromCsv extends Command
{
    protected $signature = 'import:reports';
    protected $description = 'Import reports from a CSV file';

    public function handle()
    {
        $filePath = public_path('reports.csv');

        if (!file_exists($filePath)) {
            $this->error("CSV file not found: $filePath");
            return;
        }

        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        $followUpMapping = [
            'CGI' => 'cgi',
            'FASB' => 'fasb',
            'MIS' => 'mis',
            'MIS/FASB' => 'mis/fasb',
            'No' => 'no',
            'OPC' => 'opc',
            'Pending MIS' => 'pending_mis',
            'TBD' => 'tbd',
        ];

        foreach ($csv as $rowIndex => $row) {
            try {
                $businessAreaName = trim($row['Business Area']);
                $reportName = trim($row['Report Name']);
                $reportDescription = trim($row['Report Description']);
                $labelSourceName = trim($row['Label Source']);
                $dictionaryLabelName = trim($row['Dictionary Label']);

                $businessArea = ReportBusinessArea::firstOrCreate(
                    ['name' => $businessAreaName],
                    ['name' => $businessAreaName]
                );
                $businessAreaId = $businessArea->id;

                $report = Report::firstOrCreate(
                    ['name' => $reportName],
                    ['name' => $reportName]
                );
                $reportId = $report->id;
                $report->description = $reportDescription;

                $labelSource = ReportLabelSource::firstOrCreate(
                    ['name' => $labelSourceName],
                    ['name' => $labelSourceName]
                );
                $labelSourceId = $labelSource->id;

                $dictionaryLabel = ReportDictionaryLabel::firstOrCreate(
                    ['name' => $dictionaryLabelName],
                    ['name' => $dictionaryLabelName]
                );
                $dictionaryLabelId = $dictionaryLabel->id;

                $dataMatchingRate = strtolower(trim($row['Label Match Rating'] ?? ''));
                $validRates = ['low', 'medium', 'high', 'n/a'];
                $dataMatchingRate = in_array($dataMatchingRate, $validRates) ? $dataMatchingRate : 'n/a';

                $followUpRequiredValue = trim($row['Follow Up Required'] ?? '');

                $followUpRequired = $followUpMapping[$followUpRequiredValue] ?? 'tbd';

                $dataField = null;
                switch ($row['Label Source']) {
                    case 'ICM':
                        $dataField = 'ICM';
                        break;
                    case 'MIS':
                    case 'Report':
                        $dataField = 'Financial Component';
                        break;
                    case 'TBD':
                        $dataField = 'TBD';
                        break;
                }

                ReportEntry::create([
                    'business_area_id' => $businessAreaId,
                    'report_id' => $reportId,
                    'label_source_id' => $labelSourceId,
                    'icm_data_field_path' => $row['ICM Data Field Path'] ?? null,
                    'report_dictionary_label_id' => $dictionaryLabelId,
                    'data_matching_rate' => $dataMatchingRate,
                    'existing_label' => $row['Existing Label'],
                    'data_field' => $dataField,
                    'follow_up_required' => $followUpRequired,
                ]);
            } catch (\Exception $e) {
                $this->error($rowIndex . $e->getMessage());
            }
        }

        $this->info("CSV data imported successfully!");
    }
}
