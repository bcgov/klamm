<?php

namespace App\Filament\Fodig\Resources\AnonymousUploadResource\Pages;

use App\Filament\Fodig\Resources\AnonymousUploadResource;
use App\Jobs\SyncAnonymousSiebelColumnsJob;
use App\Models\Anonymizer\AnonymousUpload;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImportSiebelMetadata extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = AnonymousUploadResource::class;
    protected static string $view = 'filament.fodig.resources.anonymous-upload-resource.pages.import-siebel-metadata';

    protected static ?string $title = 'Import Siebel Metadata';

    private const PREVIEW_ROWS = 5;
    private const PREVIEW_MAX_BYTES = 2_000_000; // Stop preview parsing after ~2 MB to keep requests snappy.
    private const PREVIEW_SKIP_BYTES = 10_000_000; // Skip parsing entirely when the upload exceeds ~10 MB.

    public const REQUIRED_HEADER_COLUMNS = [
        'DATABASE_NAME',
        'SCHEMA_NAME',
        'OBJECT_TYPE',
        'TABLE_NAME',
        'COLUMN_NAME',
        'COLUMN_ID',
        'DATA_TYPE',
    ];

    public const OPTIONAL_HEADER_COLUMNS = [
        'DATA_LENGTH',
        'DATA_PRECISION',
        'DATA_SCALE',
        'NULLABLE',
        'CHAR_LENGTH',
        'TABLE_COMMENT',
        'COLUMN_COMMENT',
        'RELATED_COLUMNS',
    ];

    // Alternate Siebel-column format headers (all optional per request).
    private const SIEBEL_COLUMNS_HEADER_CANDIDATES = [
        'NAME',
        'CHANGED',
        'PARENT TABLE',
        'PROJECT',
        'REPOSITORY NAME',
        'USER NAME',
        'ALIAS',
        'TYPE',
        'PRIMARY KEY',
        'USER KEY SEQUENCE',
        'NULLABLE',
        'TRANSLATE',
        'TRANSLATION TABLE NAME',
        'REQUIRED',
        'FOREIGN KEY TABLE',
        'USE FUNCTION KEY',
        'PHYSICAL TYPE',
        'LENGTH',
        'PRECISION',
        'SCALE',
        'DEFAULT',
        'LOV TYPE',
        'LOV BOUNDED',
        'SEQUENCE OBJECT',
        'FORCE CASE',
        'CASCADE CLEAR',
        'PRIMARY CHILD COLUMN',
        'PRIMARY INTER TABLE',
        'TRANSACTION LOG CODE',
        'VALID CONDITION',
        'DENORMALIZATION PATH',
        'PRIMARY CHILD TABLE',
        'PRIMARY CHILD COLUMN',
        'STATUS',
        'PRIMARY CHILD JOIN COLUMN',
        'PRIMARY JOIN COLUMN',
        'EIM PROCESSING COLUMN FLAG',
        'FK COLUMN 1:M REL NAME',
        'FK COLUMN M:1 REL NAME',
        'SEQUENCE',
        'ASCII ONLY',
        'INACTIVE',
        'COMMENTS',
        'NO MATCH VALUE',
        'SYSTEM FIELD MAPPING',
        'PARTITION SEQUENCE NUMBER',
        'DEFAULT INSENSITIVITY',
        'COMPUTATION EXPRESSION',
        'ENCRYPT KEY SPECIFIER',
        'MODULE'
    ];

    public array $recentUploads = [];
    public array $notifiedStatuses = [];

    public function mount(): void
    {
        $this->notifiedStatuses = AnonymousUpload::query()->pluck('status', 'id')->toArray();
        $this->refreshUploads();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_list')
                ->label('Back to Uploads')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn() => AnonymousUploadResource::getUrl('index')),
            Actions\Action::make('download_template')
                ->label('Download Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->outlined()
                ->action(fn() => $this->downloadTemplate()),
            Actions\Action::make('import_metadata')
                ->label('Import Metadata')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->outlined()
                ->modalWidth('3xl')
                ->steps([
                    Wizard\Step::make('Import Type')
                        ->schema([
                            Radio::make('import_type')
                                ->label('Import Type')
                                ->options([
                                    'full' => 'Full Import',
                                    'partial' => 'Partial Import',
                                ])
                                ->descriptions([
                                    'full' => 'Replaces all metadata. Records not in the CSV will be soft-deleted.',
                                    'partial' => 'Updates only the records in the CSV. Existing records remain unchanged.',
                                ])
                                ->required()
                                ->default('partial')
                                ->inline(false),
                        ]),
                    Wizard\Step::make('Partial Scope')
                        // Keep this step rendered to avoid Livewire autofocus issues when steps toggle visibility.
                        // Disable it for full imports so it's non-interactive but still present in DOM.
                        ->disabled(fn(Get $get) => ($get('import_type') ?? 'partial') === 'full')
                        ->columns(2)
                        ->schema([
                            Radio::make('partial_scope')
                                ->label('Apply updates to')
                                ->options([
                                    'database' => 'Database',
                                    'schema' => 'Schema',
                                    'table' => 'Table',
                                ])
                                ->default('schema')
                                ->inline(false),
                            Radio::make('scope_select_mode')
                                ->label('Scope selection')
                                ->options([
                                    'select' => 'Select existing',
                                    'manual' => 'Enter manually',
                                ])
                                ->default('select')
                                ->inline(false),
                            \Filament\Forms\Components\Select::make('scope_existing')
                                ->label('Existing name')
                                ->options(function (Get $get) {
                                    $scope = $get('partial_scope') ?? 'schema';
                                    return $this->getExistingScopeOptions($scope);
                                })
                                ->visible(fn(Get $get) => ($get('scope_select_mode') ?? 'select') === 'select')
                                ->searchable()
                                ->preload(),
                            \Filament\Forms\Components\TextInput::make('scope_manual')
                                ->label('Manual name')
                                ->visible(fn(Get $get) => ($get('scope_select_mode') ?? 'select') === 'manual')
                                ->maxLength(255),
                            \Filament\Forms\Components\Toggle::make('is_siebel_columns_format')
                                ->label('CSV uses Siebel "Parent Table/Name" column format')
                                ->helperText('Enable if your CSV looks like the Siebel Column export (all columns optional).')
                                ->default(false),
                            Placeholder::make('scope_hint')
                                ->content(function (Get $get) {
                                    $isAlt = (bool) ($get('is_siebel_columns_format') ?? false);
                                    $header = $get('preview')['header'] ?? [];
                                    $looksAlt = $header ? $this->isSiebelColumnsHeader($this->normalizeHeader($header)) : false;
                                    if ($isAlt || $looksAlt) {
                                        return new HtmlString('<div class="text-xs text-amber-700 bg-amber-50 rounded px-3 py-2">When using the Siebel column format, select or enter a Schema (or Database) so the import assigns the upload to that scope.</div>');
                                    }
                                    return new HtmlString('');
                                }),
                        ]),
                    Wizard\Step::make('Upload CSV')
                        ->schema([
                            FileUpload::make('csv_file')
                                ->label('Siebel Metadata CSV')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                                ->maxSize(500 * 1024)
                                ->storeFiles(false)
                                ->required()
                                ->reactive()
                                ->helperText('Upload the Siebel metadata export (CSV/TXT). Large files preview the first few rows only.')
                                ->afterStateUpdated(fn(Set $set, $state) => $this->handlePreviewState($set, $state)),
                        ]),
                    Wizard\Step::make('Review & Queue')
                        ->disabled(fn(Get $get) => !($get('file_name')))
                        ->visible(fn(Get $get) => (bool) $get('csv_file'))
                        ->schema([
                            Placeholder::make('preview')
                                ->label('')
                                ->content(fn(Get $get) => $this->previewMarkup(
                                    $get('file_name'),
                                    $get('preview') ?? [],
                                    $get('import_type')
                                )),
                        ]),
                ])
                ->modalSubmitActionLabel('Queue Import')
                ->action(fn(array $data) => $this->queueImport($data)),
            Actions\Action::make('refresh_uploads')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('secondary')
                ->outlined()
                ->action(fn() => $this->refreshUploads()),
            Actions\Action::make('reset_metadata')
                ->label('Reset Metadata')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Deletes all anonymized Siebel metadata, staging data, and stored uploads.')
                ->action(fn() => $this->resetAnonymousSiebelData()),
        ];
    }

    public function refreshUploads(): void
    {
        $uploads = AnonymousUpload::query()
            ->latest()
            ->limit(10)
            ->get();

        $this->recentUploads = $uploads
            ->map(function (AnonymousUpload $upload) {
                $progressPercent = $upload->progress_percent;

                if ($progressPercent === null && $upload->status === 'completed') {
                    $progressPercent = 100;
                }

                $processedBytes = $upload->processed_bytes ?? 0;
                $totalBytes = $upload->total_bytes ?? null;

                return [
                    'id' => $upload->id,
                    'original_name' => $upload->original_name ?: $upload->file_name,
                    'status' => $upload->status,
                    'status_detail' => $upload->status_detail,
                    'inserted' => $upload->inserted ?? 0,
                    'updated' => $upload->updated ?? 0,
                    'deleted' => $upload->deleted ?? 0,
                    'processed_rows' => $upload->processed_rows ?? 0,
                    'processed_rows_label' => number_format($upload->processed_rows ?? 0),
                    'processed_bytes' => $processedBytes,
                    'processed_bytes_label' => $processedBytes > 0 ? $this->formatFileSize($processedBytes) : '—',
                    'total_bytes' => $totalBytes,
                    'total_bytes_label' => $totalBytes ? $this->formatFileSize($totalBytes) : '—',
                    'progress_percent' => $progressPercent,
                    'progress_percent_label' => $progressPercent !== null ? $progressPercent . '%' : '—',
                    'progress_updated_at' => optional($upload->progress_updated_at)->toDateTimeString(),
                    'progress_updated_at_human' => optional($upload->progress_updated_at)->diffForHumans(),
                    'error' => $upload->error,
                    'created_at' => optional($upload->created_at)->toDateTimeString(),
                    'created_at_human' => optional($upload->created_at)->diffForHumans(),
                ];
            })
            ->all();

        foreach ($this->recentUploads as $upload) {
            $id = $upload['id'];
            $status = $upload['status'];
            $previousStatus = $this->notifiedStatuses[$id] ?? null;

            if (in_array($status, ['completed', 'failed'], true) && $previousStatus !== $status) {
                $notification = Notification::make()
                    ->title($status === 'completed' ? 'Import Completed' : 'Import Failed')
                    ->body($this->buildCompletionMessage($upload));

                if ($status === 'completed') {
                    $notification->success();
                } else {
                    $notification->danger();
                }

                $notification->send();
            }

            $this->notifiedStatuses[$id] = $status;
        }
    }

    private function handlePreviewState(Set $set, mixed $state): void
    {
        if ($state === null || $state === '') {
            $set('file_name', null);
            $set('preview', null);
            return;
        }

        try {
            $file = $this->resolveUploadedFile($state);
        } catch (Throwable $exception) {
            report($exception);
            $set('file_name', null);
            $set('preview', null);
            return;
        }

        $set('file_name', $file->getClientOriginalName());
        $set('preview', $this->buildCsvPreview($file));
    }

    private function buildCsvPreview(UploadedFile $file): array
    {
        $header = [];
        $rows = [];
        $rowCount = 0;
        $bytesRead = 0;
        $truncated = false;
        $skipped = false;
        $missing = [];

        if ($file->getSize() >= self::PREVIEW_SKIP_BYTES) {
            $skipped = true;
        } elseif (($handle = fopen($file->getRealPath(), 'rb')) !== false) {
            $header = fgetcsv($handle) ?: [];
            $header = $this->normalizeHeader($header);
            $missing = $this->missingRequiredColumns($header);
            $dataStart = ftell($handle) ?: 0;

            while (($row = fgetcsv($handle)) !== false) {
                ++$rowCount;

                if (count($rows) < self::PREVIEW_ROWS) {
                    $rows[] = $row;
                }

                if (! $truncated) {
                    $currentOffset = ftell($handle) ?: $dataStart;
                    $bytesRead = max(0, $currentOffset - $dataStart);

                    if ($bytesRead >= self::PREVIEW_MAX_BYTES) {
                        $truncated = true;
                        break;
                    }
                }
            }

            fclose($handle);
        }

        return [
            'header' => $header,
            'rows' => $rows,
            'row_count' => $truncated ? null : $rowCount,
            'size' => $file->getSize(),
            'truncated' => $truncated,
            'skipped' => $skipped,
            'missing_required' => $missing,
        ];
    }

    private function previewMarkup(?string $fileName, array $preview, ?string $importType = null): HtmlString
    {
        if (! $fileName || $preview === []) {
            return new HtmlString('<p class="text-sm text-gray-500">Upload a CSV to review its contents before queueing the import.</p>');
        }

        $header = $preview['header'] ?? [];
        $rows = $preview['rows'] ?? [];
        $rowCount = $preview['row_count'] ?? null;
        $size = $preview['size'] ?? 0;
        $columns = count($header);
        $truncated = (bool) ($preview['truncated'] ?? false);
        $skipped = (bool) ($preview['skipped'] ?? false);
        $missing = $preview['missing_required'] ?? [];

        $html = '<div class="space-y-4 text-sm">';

        if ($importType) {
            $importTypeLabel = $importType === 'full' ? 'Full Import' : 'Partial Import';
            $importTypeColor = $importType === 'full' ? 'rose' : 'blue';
            $html .= '<div class="rounded-md bg-' . $importTypeColor . '-50 px-3 py-2 text-xs text-' . $importTypeColor . '-800 font-semibold">Import Type: ' . e($importTypeLabel) . '</div>';

            if ($importType === 'full') {
                $html .= '<div class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">⚠️ Full import will soft-delete any existing records not present in this CSV.</div>';
            }
        }

        $html .= '<div><span class="font-semibold">File:</span> ' . e($fileName) . '</div>';
        $html .= '<div class="flex flex-wrap gap-4 text-xs text-gray-600">';
        $html .= '<span>Total columns: ' . $columns . '</span>';
        $html .= '<span>Preview rows: ' . count($rows) . '</span>';
        $html .= '<span>Detected rows: ' . ($rowCount === null ? 'Unavailable for large files' : $rowCount) . '</span>';
        $html .= '<span>Size: ' . e($this->formatFileSize($size)) . '</span>';
        $html .= '</div>';

        if ($truncated) {
            $html .= '<div class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">Preview truncated for faster processing. The full CSV will still be saved and processed when you queue the import.</div>';
        } elseif ($skipped) {
            $html .= '<div class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">Preview skipped because the file is very large. The full CSV will still be saved and processed when you queue the import.</div>';
        }

        if ($missing) {
            $html .= '<div class="rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-800">Missing required columns: ' . e(implode(', ', $missing)) . '. Please update the export and re-upload before queueing the import.</div>';
        }

        if ($columns > 0) {
            $html .= '<div class="overflow-x-auto border border-gray-200 rounded-lg">';
            $html .= '<table class="min-w-full text-left text-xs">';
            $html .= '<thead class="bg-gray-50"><tr>';
            foreach ($header as $heading) {
                $html .= '<th class="px-3 py-2 font-semibold text-gray-700">' . e($heading) . '</th>';
            }
            $html .= '</tr></thead><tbody class="divide-y divide-gray-100">';

            if ($rows === []) {
                $html .= '<tr><td colspan="' . $columns . '" class="px-3 py-2 text-gray-500">No data rows detected.</td></tr>';
            } else {
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    for ($i = 0; $i < $columns; $i++) {
                        $cell = $row[$i] ?? '';
                        $html .= '<td class="px-3 py-2 text-gray-700">' . e(Str::limit($cell, 120)) . '</td>';
                    }
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody></table></div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    private function queueImport(array $data): void
    {
        try {
            $file = $this->resolveUploadedFile($data['csv_file'] ?? null);
            $importType = $data['import_type'] ?? 'partial';
            $isSiebelColumns = (bool) ($data['is_siebel_columns_format'] ?? false);
            $scopeType = $data['partial_scope'] ?? null;
            $scopeName = ($data['scope_select_mode'] ?? 'select') === 'select'
                ? ($data['scope_existing'] ?? null)
                : ($data['scope_manual'] ?? null);

            $disk = config('filesystems.default', 'local');
            $directory = 'anonymous-siebel/imports';
            $extension = $file->getClientOriginalExtension() ?: 'csv';
            $basename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'siebel-metadata';
            $filename = now()->format('Ymd_His') . '_' . $basename . '.' . $extension;

            // If alternate Siebel-column format, transform into the canonical header CSV expected by downstream jobs.
            // Auto-detect Siebel-column format based on header even if the toggle wasn't set.
            $header = $this->tryReadHeader($file);
            $looksSiebelColumns = $header !== null && $this->isSiebelColumnsHeader($this->normalizeHeader($header));
            if ($isSiebelColumns || $looksSiebelColumns) {
                // For partial imports, require an explicit scope; for full imports, bypass scope enforcement.
                $transformScopeType = null;
                $transformScopeName = null;
                if ($importType === 'partial') {
                    if (! $scopeName || ! in_array($scopeType, ['schema', 'database', 'table'], true)) {
                        throw new RuntimeException('For Siebel-column CSVs, select a scope (Database/Schema/Table) and provide a name.');
                    }
                    $transformScopeType = $scopeType;
                    $transformScopeName = $scopeName;
                }

                $csvTransformed = $this->transformSiebelColumnsCsv($file, $transformScopeType, $transformScopeName);
                $storedPath = $directory . '/' . $filename;
                Storage::disk($disk)->put($storedPath, $csvTransformed);
            } else {
                $storedPath = $file->storeAs($directory, $filename, $disk);
            }

            if (method_exists($file, 'delete')) {
                $file->delete();
            }

            try {
                $fileSize = Storage::disk($disk)->size($storedPath);
            } catch (Throwable $exception) {
                report($exception);
                $fileSize = null;
            }

            $upload = AnonymousUpload::create([
                'file_disk' => $disk,
                'file_name' => $filename,
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'import_type' => $importType,
                'scope_type' => $scopeType,
                'scope_name' => $scopeName,
                'status' => 'queued',
                'status_detail' => $isSiebelColumns
                    || $looksSiebelColumns ? 'Queued (Siebel column format transformed)'
                    : 'Queued for processing',
                'inserted' => 0,
                'updated' => 0,
                'deleted' => 0,
                'total_bytes' => $fileSize,
                'processed_bytes' => 0,
                'processed_rows' => 0,
                'progress_updated_at' => now(),
                'error' => null,
            ]);

            SyncAnonymousSiebelColumnsJob::dispatch($upload->id);

            $this->refreshUploads();

            Notification::make()
                ->success()
                ->title('Import Queued')
                ->body('The CSV has been queued for processing. Use refresh to monitor progress.')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }
    }

    private function downloadTemplate(): StreamedResponse
    {
        $headers = array_merge(self::REQUIRED_HEADER_COLUMNS, self::OPTIONAL_HEADER_COLUMNS);
        $rows = [
            ['DB_A', 'SCHEMA_CORE', 'TABLE', 'S_CONTACT', 'LAST_NAME', 1, 'VARCHAR2', 100, null, null, 'Y', null, 'Stores last names', 'Siebel contact table', 'OUTBOUND -> CORE.S_ORG_EXT.ACCNT_NAME via ACCNT_CON'],
            ['DB_A', 'SCHEMA_CORE', 'TABLE', 'S_CONTACT', 'EMAIL_ADDR', 2, 'VARCHAR2', 255, null, null, 'Y', null, 'Primary email address', 'Siebel contact table', ''],
        ];

        $csv = implode(',', $headers) . PHP_EOL;
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($value) => '"' . str_replace('"', '""', (string) $value) . '"', $row)) . PHP_EOL;
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'siebel_metadata_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizeHeader(array $header): array
    {
        $normalized = [];

        foreach ($header as $value) {
            $value = strtoupper(trim((string) $value));

            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    private function missingRequiredColumns(array $normalizedHeader): array
    {
        // Accept either canonical headers or the Siebel-column format; when the latter is detected, no required columns.
        if ($this->isSiebelColumnsHeader($normalizedHeader)) {
            return [];
        }
        return array_values(array_diff(self::REQUIRED_HEADER_COLUMNS, $normalizedHeader));
    }

    private function isSiebelColumnsHeader(array $normalizedHeader): bool
    {
        // Consider it Siebel-column format if it contains NAME and PARENT TABLE, plus PHYSICAL TYPE or LENGTH,
        // and most columns look like the provided Siebel template.
        $set = array_flip($normalizedHeader);
        $required = ['NAME', 'PARENT TABLE'];
        foreach ($required as $r) {
            if (! isset($set[$r])) {
                return false;
            }
        }
        if (! (isset($set['PHYSICAL TYPE']) || isset($set['LENGTH']) || isset($set['FOREIGN KEY TABLE']))) {
            return false;
        }
        // Heuristic: at least 8 of the known Siebel headers present
        $present = 0;
        foreach (self::SIEBEL_COLUMNS_HEADER_CANDIDATES as $name) {
            if (isset($set[$name])) {
                $present++;
            }
        }
        return $present >= 8;
    }

    private function tryReadHeader(UploadedFile $file): ?array
    {
        try {
            $h = fopen($file->getRealPath(), 'rb');
            if ($h === false) {
                return null;
            }
            $header = fgetcsv($h) ?: [];
            fclose($h);
            return $header;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function transformSiebelColumnsCsv(UploadedFile $file, ?string $scopeType, ?string $scopeName): string
    {
        // Build canonical CSV with REQUIRED + OPTIONAL headers using the Siebel-column format rows.
        $headers = array_merge(self::REQUIRED_HEADER_COLUMNS, self::OPTIONAL_HEADER_COLUMNS);
        $out = fopen('php://temp', 'w+');
        fputcsv($out, $headers);

        $in = fopen($file->getRealPath(), 'rb');
        if ($in === false) {
            throw new RuntimeException('Unable to read uploaded CSV for transformation.');
        }

        $rawHeader = fgetcsv($in) ?: [];
        $hdr = $this->normalizeHeader($rawHeader);
        $idx = fn(string $name) => array_search($name, $hdr, true);

        // Determine scope values.
        $database = null;
        $schema = null;
        $tableScope = null;
        switch ($scopeType) {
            case 'database':
                $database = $scopeName;
                break;
            case 'schema':
                $schema = $scopeName;
                break;
            case 'table':
                $tableScope = $scopeName;
                break;
        }

        while (($row = fgetcsv($in)) !== false) {
            // Map fields from Siebel-column format
            $parentTable = trim((string) $this->getByIndex($row, $idx('PARENT TABLE')));
            $columnName = trim((string) $this->getByIndex($row, $idx('NAME')));
            $dataType = trim((string) ($this->getByIndex($row, $idx('PHYSICAL TYPE')) ?? ''));
            $length = $this->getByIndex($row, $idx('LENGTH'));
            $precision = $this->getByIndex($row, $idx('PRECISION'));
            $scale = $this->getByIndex($row, $idx('SCALE'));
            $nullable = trim((string) ($this->getByIndex($row, $idx('NULLABLE')) ?? ''));
            $fkTable = trim((string) ($this->getByIndex($row, $idx('FOREIGN KEY TABLE')) ?? ''));
            $comments = $this->getByIndex($row, $idx('COMMENTS'));

            // Canonical fields
            // Fallbacks ensure staging can resolve database/schema maps.
            // Use sensible defaults when no scope is provided.
            $databaseFinal = $database ?: 'Siebel';
            $schemaFinal = $schema ?: 'Siebel';

            $canonical = [
                'DATABASE_NAME' => $databaseFinal,
                'SCHEMA_NAME' => $schemaFinal,
                'OBJECT_TYPE' => 'TABLE',
                'TABLE_NAME' => $parentTable ?: ($tableScope ?? ''),
                'COLUMN_NAME' => $columnName ?: '',
                'COLUMN_ID' => null,
                'DATA_TYPE' => $dataType ?: '',
                'DATA_LENGTH' => $length ?: null,
                'DATA_PRECISION' => $precision ?: null,
                'DATA_SCALE' => $scale ?: null,
                'NULLABLE' => $nullable ?: null,
                'CHAR_LENGTH' => null,
                'TABLE_COMMENT' => null,
                'COLUMN_COMMENT' => $comments ?: null,
                'RELATED_COLUMNS' => $fkTable ?: null,
            ];

            // Write in header order
            $ordered = [];
            foreach ($headers as $h) {
                $ordered[] = $canonical[$h] ?? null;
            }
            fputcsv($out, $ordered);
        }

        fclose($in);
        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);
        return $csv;
    }

    private function getByIndex(array $row, $index): ?string
    {
        if ($index === false || $index === null) {
            return null;
        }
        $val = $row[$index] ?? null;
        return $val === null ? null : (string) $val;
    }

    private function getExistingScopeOptions(string $scope): array
    {
        try {
            // Use Eloquent models to ensure correct column names and soft-delete handling.
            switch ($scope) {
                case 'database':
                    return \App\Models\Anonymizer\AnonymousSiebelDatabase::query()
                        ->orderBy('database_name')
                        ->pluck('database_name', 'database_name')
                        ->all();
                case 'schema':
                    return \App\Models\Anonymizer\AnonymousSiebelSchema::query()
                        ->orderBy('schema_name')
                        ->pluck('schema_name', 'schema_name')
                        ->all();
                case 'table':
                    return \App\Models\Anonymizer\AnonymousSiebelTable::query()
                        ->orderBy('table_name')
                        ->pluck('table_name', 'table_name')
                        ->all();
                default:
                    return [];
            }
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    private function resolveUploadedFile(mixed $state): UploadedFile
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        if (is_string($state) && Str::isJson($state)) {
            $state = json_decode($state, true);
        }

        if (is_array($state)) {
            if (isset($state[0]) && is_array($state[0])) {
                $state = $state[0];
            }

            $path = $state['path'] ?? null;

            if (is_string($path) && $path !== '') {
                $disk = $state['disk'] ?? config('livewire.temporary_file_upload.disk', config('filesystems.default', 'local'));
                $storage = Storage::disk($disk);

                if (! $storage->exists($path)) {
                    throw new RuntimeException('Uploaded temporary file is missing.');
                }

                if (! method_exists($storage, 'path')) {
                    throw new RuntimeException("The [$disk] disk does not provide a local path for temporary uploads.");
                }

                $absolutePath = $storage->path($path);

                return new UploadedFile(
                    $absolutePath,
                    $state['filename'] ?? $state['original_name'] ?? $state['name'] ?? basename($absolutePath),
                    $state['mime_type'] ?? $state['type'] ?? null,
                    UPLOAD_ERR_OK,
                    true
                );
            }
        }

        if (is_string($state) && $state !== '') {
            try {
                return TemporaryUploadedFile::createFromLivewire($state);
            } catch (Throwable $exception) {
                throw new RuntimeException('Unable to hydrate uploaded file from Livewire payload.', 0, $exception);
            }
        }

        throw new RuntimeException('No CSV file was provided.');
    }

    private function resetAnonymousSiebelData(): void
    {
        try {
            $uploads = AnonymousUpload::all();

            foreach ($uploads as $upload) {
                $disk = $upload->file_disk ?: config('filesystems.default', 'local');

                if ($upload->path && Storage::disk($disk)->exists($upload->path)) {
                    Storage::disk($disk)->delete($upload->path);
                }
            }

            DB::transaction(function () {
                DB::statement('TRUNCATE TABLE anonymous_siebel_column_dependencies, anonymous_siebel_columns, anonymous_siebel_tables, anonymous_siebel_schemas, anonymous_siebel_databases, anonymous_siebel_data_types, anonymous_siebel_stagings, anonymization_uploads RESTART IDENTITY CASCADE');
            });

            $this->refreshUploads();

            Notification::make()
                ->success()
                ->title('Metadata Reset')
                ->body('All anonymized Siebel metadata and uploads have been cleared.')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Reset Failed')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }
    }

    private function formatFileSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }

    private function buildCompletionMessage(array $upload): string
    {
        $parts = [
            'Inserted: ' . number_format($upload['inserted'] ?? 0),
            'Updated: ' . number_format($upload['updated'] ?? 0),
            'Deleted: ' . number_format($upload['deleted'] ?? 0),
        ];

        return ($upload['original_name'] ?? 'Import') . ' — ' . implode(' • ', $parts);
    }
}
