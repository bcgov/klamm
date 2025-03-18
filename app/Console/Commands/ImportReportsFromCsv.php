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
    protected $signature = 'import:reports {--skip-missing : Skip rows with missing references} {--debug : Show debug information}';
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

        $skipMissing = $this->option('skip-missing');
        $debug = $this->option('debug');
        $rowCount = 0;
        $skippedCount = 0;

        if ($debug) {
            $allBusinessAreas = ReportBusinessArea::all()->pluck('name', 'id')->toArray();
            $allReports = Report::all()->pluck('name', 'id')->toArray();
            $allLabelSources = ReportLabelSource::all()->pluck('name', 'id')->toArray();
            $allDictionaryLabels = ReportDictionaryLabel::all()->pluck('name', 'id')->toArray();

            $this->info("Available Business Areas: " . implode(", ", array_values($allBusinessAreas)));
            $this->info("Available Reports: " . implode(", ", array_values($allReports)));
            $this->info("Available Label Sources: " . implode(", ", array_values($allLabelSources)));
            $this->info("Available Dictionary Labels: " . implode(", ", array_values($allDictionaryLabels)));
        }

        foreach ($csv as $rowIndex => $row) {
            try {
                $businessAreaName = trim($row['Business Area']);
                $reportName = trim($row['Report Name']);
                $labelSourceName = trim($row['Label Source']);
                $dictionaryLabelName = trim($row['Dictionary Label']);

                if ($debug) {
                    $this->line("Processing row " . ($rowIndex + 2) . ":");
                    $this->line("  Business Area: '$businessAreaName'");
                    $this->line("  Report Name: '$reportName'");
                    $this->line("  Label Source: '$labelSourceName'");
                    $this->line("  Dictionary Label: '$dictionaryLabelName'");
                }

                $businessArea = ReportBusinessArea::where('name', $businessAreaName)->first();
                if ($debug && !$businessArea) {
                    $this->warn("  > Business Area not found: '$businessAreaName'");
                    $similar = ReportBusinessArea::whereRaw("LOWER(name) = ?", [strtolower($businessAreaName)])->first();
                    if ($similar) {
                        $this->info("  > Found similar Business Area: '{$similar->name}'");
                    }
                }

                $report = Report::where('name', $reportName)->first();
                if ($debug && !$report) {
                    $this->warn("  > Report not found: '$reportName'");
                    $similar = Report::whereRaw("LOWER(name) = ?", [strtolower($reportName)])->first();
                    if ($similar) {
                        $this->info("  > Found similar Report: '{$similar->name}'");
                    }
                }

                $labelSource = ReportLabelSource::where('name', $labelSourceName)->first();
                if ($debug && !$labelSource) {
                    $this->warn("  > Label Source not found: '$labelSourceName'");
                    $similar = ReportLabelSource::whereRaw("LOWER(name) = ?", [strtolower($labelSourceName)])->first();
                    if ($similar) {
                        $this->info("  > Found similar Label Source: '{$similar->name}'");
                    }
                }

                $dictionaryLabel = ReportDictionaryLabel::where('name', $dictionaryLabelName)->first();
                if ($debug && !$dictionaryLabel) {
                    $this->warn("  > Dictionary Label not found: '$dictionaryLabelName'");
                    $similar = ReportDictionaryLabel::whereRaw("LOWER(name) = ?", [strtolower($dictionaryLabelName)])->first();
                    if ($similar) {
                        $this->info("  > Found similar Dictionary Label: '{$similar->name}'");
                    }
                }

                if (!$businessArea || !$report || !$labelSource) {
                    if ($skipMissing) {
                        $missing = [];
                        if (!$businessArea) $missing[] = "Business Area: '{$businessAreaName}'";
                        if (!$report) $missing[] = "Report: '{$reportName}'";
                        if (!$labelSource) $missing[] = "Label Source: '{$labelSourceName}'";

                        $this->warn("Row " . ($rowIndex + 2) . " skipped: Missing " . implode(', ', $missing));
                        $skippedCount++;
                        continue;
                    } else {
                        throw new \Exception("Missing required reference(s) for row " . ($rowIndex + 2));
                    }
                }

                $dataMatchingRate = strtolower(trim($row['Label Match Rating'] ?? ''));
                if ($dataMatchingRate === 'hard') {
                    $dataMatchingRate = 'complex';
                }
                $validRates = ['easy', 'medium', 'complex'];
                $dataMatchingRate = in_array($dataMatchingRate, $validRates) ? $dataMatchingRate : null;

                if ($debug) {
                    $this->info("  > Creating entry with:");
                    $this->info("    - Business Area ID: {$businessArea->id} ({$businessArea->name})");
                    $this->info("    - Report ID: {$report->id} ({$report->name})");
                    $this->info("    - Label Source ID: {$labelSource->id} ({$labelSource->name})");
                }

                $reportEntry = ReportEntry::create([
                    'business_area_id' => $businessArea->id,
                    'report_id' => $report->id,
                    'label_source_id' => $labelSource->id,
                    'report_dictionary_label_id' => $dictionaryLabel->id,
                    'existing_label' => $row['Existing Label'],
                    'data_field' => $row['Source Data Field'] ?? null,
                    'icm_data_field_path' => $row['ICM Data Field Path'] ?? null,
                    'data_matching_rate' => $dataMatchingRate,
                    'note' => $row['Note'] ?? null,
                ]);

                if ($debug) {
                    $this->info("  > Created entry ID: {$reportEntry->id}");
                    $this->info("    - Stored Report ID: {$reportEntry->report_id}");
                    if ($reportEntry->report_id != $report->id) {
                        $this->warn("    - WARNING: Report ID mismatch! Expected {$report->id} but got {$reportEntry->report_id}");
                    }
                }

                $rowCount++;
                if ($debug) {
                    $this->info("  > Row " . ($rowIndex + 2) . " imported successfully");
                }
            } catch (\Exception $e) {
                $this->error("Error on row " . ($rowIndex + 2) . ": " . $e->getMessage());
                if (!$skipMissing) {
                    return;
                }
                $skippedCount++;
            }
        }

        $this->info("CSV data imported successfully! Imported $rowCount rows, skipped $skippedCount rows.");
    }
}
