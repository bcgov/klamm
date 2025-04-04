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

        foreach ($csv as $rowIndex => $row) {
            try {
                $businessAreaName = trim($row['Business Area']);
                $reportName = trim($row['Report Name']);
                $labelSourceName = trim($row['Label Source']);
                $dictionaryLabelName = trim($row['Dictionary Label']);

                $businessArea = ReportBusinessArea::where('name', $businessAreaName)->first();

                $report = Report::where('name', $reportName)->first();

                $labelSource = ReportLabelSource::where('name', $labelSourceName)->first();

                $dictionaryLabel = ReportDictionaryLabel::where('name', $dictionaryLabelName)->first();

                $dictionaryLabelId = $dictionaryLabel ? $dictionaryLabel->id : null;

                //$dataMatchingRate = strtolower(trim($row['Label Match Rating'] ?? ''));
                //$validRates = ['low', 'medium', 'high', 'NA'];
                //$dataMatchingRate = in_array($dataMatchingRate, $validRates) ? $dataMatchingRate : null;

                ReportEntry::create([
                    'business_area_id' => $businessArea->id,
                    'report_id' => $report->id,
                    'label_source_id' => $labelSource->id,
                    'report_dictionary_label_id' => $dictionaryLabelId,
                    'existing_label' => $row['Existing Label'],
                    //'data_field' => $row['Source Data Field'] ?? null, // MAYBE MISSING
                    //'icm_data_field_path' => $row['ICM Data Field Path'] ?? null,
                    //'data_matching_rate' => $dataMatchingRate,
                    //'follow_up_required' => $followUpRequired,
                ]);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }

        $this->info("CSV data imported successfully!");
    }
}
