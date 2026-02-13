<?php

namespace App\Filament\Fodig\Resources\AnonymousUploadResource\Pages;

use App\Filament\Fodig\Resources\AnonymousUploadResource;
use App\Jobs\SyncAnonymousSiebelColumnsJob;
use App\Models\Anonymizer\AnonymousUpload;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use App\Constants\Fodig\Anonymizer\SiebelColumns;
use App\Constants\Fodig\Anonymizer\SiebelMetadata;
use App\Http\Middleware\CheckRole;
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
            // Actions\Action::make('download_template')
            //     ->label('Download Extraction Logic')
            //     ->icon('heroicon-o-arrow-down-tray')
            //     ->color('gray')
            //     ->outlined()
            //     ->action(fn() => $this->downloadTemplate()),
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
                            Toggle::make('create_change_tickets')
                                ->label('Create change tickets after import')
                                ->helperText('When enabled, Klamm will create change tickets based on catalog differences detected by this upload.')
                                ->default(true),
                            Toggle::make('override_anonymization_rules')
                                ->label('Override anonymization rules from CSV')
                                ->helperText('Admin only. When enabled, blank ANON_RULE / ANON_NOTE values clear existing anonymization required/method mappings for matched columns.')
                                ->default(false)
                                ->visible(fn(): bool => CheckRole::hasRole(request(), 'admin')),
                        ]),
                    Wizard\Step::make('Partial Scope')
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
                        ]),
                    Wizard\Step::make('Upload CSV')
                        ->schema([
                            FileUpload::make('csv_file')
                                ->label('Siebel Metadata CSV')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                                ->maxSize(500 * 1024)
                                ->storeFiles(false)
                                ->required()
                                ->helperText('Upload the Siebel metadata export (CSV/TXT).'),
                        ]),
                ])
                ->modalSubmitActionLabel('Queue Import')
                ->action(fn(array $data) => $this->queueImport($data)),

            Actions\Action::make('copy_relationship_export_script')
                ->label('Copy Export Script')
                ->icon('heroicon-o-clipboard-document')
                ->color('info')
                ->outlined()
                ->action(function (): void {
                    $path = base_path('scripts/ScriptTemplates/anonymization-relationship-export.sql');

                    if (! is_file($path)) {
                        Notification::make()
                            ->danger()
                            ->title('Script Not Found')
                            ->body('Could not find the export template at: ' . $path)
                            ->send();
                        return;
                    }

                    $content = file_get_contents($path);

                    if ($content === false || $content === '') {
                        Notification::make()
                            ->danger()
                            ->title('Copy Failed')
                            ->body('Failed to read the export template from disk.')
                            ->persistent()
                            ->send();
                        return;
                    }

                    $this->js('navigator.clipboard.writeText(' . Js::from($content) . ');');

                    Notification::make()
                        ->success()
                        ->title('Copied to Clipboard')
                        ->body('The export SQL script has been copied to your clipboard.')
                        ->send();
                }),
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
                ->visible(fn() => app()->environment('local'))
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
                    'processed_bytes_label' => $processedBytes > 0 ? \App\Helpers\StringHelper::formatFileSize($processedBytes) : '—',
                    'total_bytes' => $totalBytes,
                    'total_bytes_label' => $totalBytes ? \App\Helpers\StringHelper::formatFileSize($totalBytes) : '—',
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

    // Validate, store, and queue a background job to import the CSV.
    // If the CSV uses an alternate Siebel-column format, transform into expected CSV before storing.
    private function queueImport(array $data): void
    {
        try {
            $file = $this->resolveUploadedFile($data['csv_file'] ?? null);
            $importType = $data['import_type'] ?? 'partial';
            $createChangeTickets = (bool) ($data['create_change_tickets'] ?? true);
            $overrideAnonymizationRules = (bool) ($data['override_anonymization_rules'] ?? false);
            $isSiebelColumns = (bool) ($data['is_siebel_columns_format'] ?? false);
            $scopeType = $data['partial_scope'] ?? null;
            $scopeName = ($data['scope_select_mode'] ?? 'select') === 'select'
                ? ($data['scope_existing'] ?? null)
                : ($data['scope_manual'] ?? null);

            $disk = config('filesystems.default', 'local');
            $directory = SiebelMetadata::IMPORT_DIRECTORY;
            $extension = $file->getClientOriginalExtension() ?: 'csv';
            $basename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'siebel-metadata';
            $filename = now()->format('Ymd_His') . '_' . $basename . '.' . $extension;

            $header = $this->tryReadHeader($file);
            $looksSiebelColumns = $header !== null && $this->isSiebelColumnsHeader($this->normalizeHeader($header));
            if ($isSiebelColumns || $looksSiebelColumns) {
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
                'create_change_tickets' => $createChangeTickets,
                'override_anonymization_rules' => $overrideAnonymizationRules,
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

    // provide a CSV template download with required and optional headers as an example of the expected format.
    private function downloadTemplate(): StreamedResponse
    {
        $headers = array_merge(SiebelMetadata::REQUIRED_HEADER_COLUMNS, SiebelMetadata::OPTIONAL_HEADER_COLUMNS);
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

    // Siebel-column format if it contains NAME and PARENT TABLE, plus PHYSICAL TYPE or LENGTH, and most columns look like the provided Siebel template.
    private function isSiebelColumnsHeader(array $normalizedHeader): bool
    {
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
        // temp validation: at least 8 of the known Siebel headers present
        $present = 0;
        foreach (SiebelColumns::SIEBEL_COLUMNS_HEADER_CANDIDATES as $name) {
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

    // transformSiebelColumnsCsv: take a Siebel-style columns CSV and rewrite it into the expected CSV format expected by downstream jobs.
    // future refinement needed once official structure of uploads is finalized.
    private function transformSiebelColumnsCsv(UploadedFile $file, ?string $scopeType, ?string $scopeName): string
    {
        // Build canonical CSV with REQUIRED + OPTIONAL headers using the Siebel-column format rows.
        $headers = array_merge(SiebelMetadata::REQUIRED_HEADER_COLUMNS, SiebelMetadata::OPTIONAL_HEADER_COLUMNS);
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

            // Use when no scope is provided.
            $databaseFinal = $database ?: SiebelMetadata::DEFAULT_DATABASE;
            $schemaFinal = $schema ?: SiebelMetadata::DEFAULT_SCHEMA;

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

    // Destructive. Intended for local/dev use only. Deletes all anonymized Siebel metadata, plus any stored upload files.
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
                DB::statement('TRUNCATE TABLE ' . SiebelMetadata::TRUNCATE_TABLES_CSV . ' RESTART IDENTITY CASCADE');
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

    // create a short summary string for user notifications after an upload is queued.
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
