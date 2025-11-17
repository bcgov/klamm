<?php

namespace App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource\Pages;

use App\Filament\Fodig\Resources\AnonymousSiebelDatabaseResource;
use App\Jobs\SyncAnonymousSiebelColumnsJob;
use App\Models\Anonymizer\AnonymousUpload;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
use Throwable;

class ImportSiebelMetadata extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = AnonymousSiebelDatabaseResource::class;
    protected static string $view = 'filament.fodig.resources.anonymous-siebel-database-resource.pages.import-siebel-metadata';

    private const PREVIEW_ROWS = 5;

    public array $recentUploads = [];

    // protected function getHeading(): string
    // {
    //     return 'Import Siebel Metadata';
    // }

    // protected function getSubheading(): ?string
    // {
    //     return 'Upload anonymized Siebel metadata CSV files and queue the sync job.';
    // }

    public function mount(): void
    {
        $this->refreshUploads();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_metadata')
                ->label('Import Metadata')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->outlined()
                ->modalWidth('3xl')
                ->steps([
                    Wizard\Step::make('Upload CSV')
                        ->schema([
                            FileUpload::make('csv_file')
                                ->label('Siebel Metadata CSV')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                                ->maxSize(500 * 1024)
                                ->storeFiles(false)
                                ->required()
                                ->reactive()
                                ->helperText('Upload the Siebel metadata export (CSV/TXT).')
                                ->afterStateUpdated(fn(Set $set, $state) => $this->handlePreviewState($set, $state)),
                        ]),
                    Wizard\Step::make('Review & Queue')
                        ->schema([
                            Placeholder::make('preview')
                                ->label('')
                                ->content(fn(Get $get) => $this->previewMarkup(
                                    $get('file_name'),
                                    $get('preview') ?? []
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
        $this->recentUploads = AnonymousUpload::query()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn(AnonymousUpload $upload) => [
                'id' => $upload->id,
                'original_name' => $upload->original_name ?: $upload->file_name,
                'status' => $upload->status,
                'inserted' => $upload->inserted ?? 0,
                'updated' => $upload->updated ?? 0,
                'deleted' => $upload->deleted ?? 0,
                'error' => $upload->error,
                'created_at' => optional($upload->created_at)->toDateTimeString(),
                'created_at_human' => optional($upload->created_at)->diffForHumans(),
            ])
            ->all();
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

        if (($handle = fopen($file->getRealPath(), 'rb')) !== false) {
            $header = fgetcsv($handle) ?: [];
            while (($row = fgetcsv($handle)) !== false) {
                ++$rowCount;
                if (count($rows) < self::PREVIEW_ROWS) {
                    $rows[] = $row;
                }
            }
            fclose($handle);
        }

        return [
            'header' => $header,
            'rows' => $rows,
            'row_count' => $rowCount,
            'size' => $file->getSize(),
        ];
    }

    private function previewMarkup(?string $fileName, array $preview): HtmlString
    {
        if (! $fileName || $preview === []) {
            return new HtmlString('<p class="text-sm text-gray-500">Upload a CSV to review its contents before queueing the import.</p>');
        }

        $header = $preview['header'] ?? [];
        $rows = $preview['rows'] ?? [];
        $rowCount = $preview['row_count'] ?? count($rows);
        $size = $preview['size'] ?? 0;
        $columns = count($header);

        $html = '<div class="space-y-4 text-sm">';
        $html .= '<div><span class="font-semibold">File:</span> ' . e($fileName) . '</div>';
        $html .= '<div class="flex flex-wrap gap-4 text-xs text-gray-600">';
        $html .= '<span>Total columns: ' . $columns . '</span>';
        $html .= '<span>Preview rows: ' . count($rows) . '</span>';
        $html .= '<span>Detected rows: ' . $rowCount . '</span>';
        $html .= '<span>Size: ' . e($this->formatFileSize($size)) . '</span>';
        $html .= '</div>';

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

            $disk = config('filesystems.default', 'local');
            $directory = 'anonymous-siebel/imports';
            $extension = $file->getClientOriginalExtension() ?: 'csv';
            $basename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'siebel-metadata';
            $filename = now()->format('Ymd_His') . '_' . $basename . '.' . $extension;

            $storedPath = $file->storeAs($directory, $filename, $disk);

            if (method_exists($file, 'delete')) {
                $file->delete();
            }

            $upload = AnonymousUpload::create([
                'file_disk' => $disk,
                'file_name' => $filename,
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'status' => 'queued',
                'inserted' => 0,
                'updated' => 0,
                'deleted' => 0,
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

    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
