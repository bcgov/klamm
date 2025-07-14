<?php

namespace App\Jobs;

use App\Models\FormBuilding\FormVersion;
use App\Services\FormVersionJsonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use App\Models\User;

class GenerateFormVersionJsonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout

    public function __construct(
        public FormVersion $formVersion,
        public int $userId,
        public int $version = 2
    ) {}

    public function handle(): void
    {
        try {
            $jsonService = new FormVersionJsonService();

            $formVersion = FormVersion::with([
                'form',
                'formElements.elementable' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\FormBuilding\SelectInputFormElement::class => ['options'],
                        \App\Models\FormBuilding\RadioInputFormElement::class => ['options'],
                    ]);
                },
                'formElements.dataBindings.formDataSource',
                'formDataSources' => function ($query) {
                    $query->orderBy('form_versions_form_data_sources.order');
                },
                'webStyleSheet',
                'pdfStyleSheet',
                'webFormScript',
                'pdfFormScript'
            ])->find($this->formVersion->id);

            switch ($this->version) {
                case 1:
                    $jsonData = $jsonService->generatePreMigrationJson($formVersion);
                    break;
                case 2:
                    $jsonData = $jsonService->generateJson($formVersion);
                    break;
                default:
                    throw new \Exception("Unsupported format version: {$this->version}");
            }

            // Create filename with form title and version
            $formTitle = $this->formVersion->form->form_title ?? 'Unknown Form';
            $sanitizedTitle = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $formTitle);
            $filename = "form_{$sanitizedTitle}_v{$this->formVersion->version_number}_{$this->formVersion->id}_formatversion_{$this->version}.json";

            // Store the JSON file
            $filePath = "{$filename}";
            Storage::disk('templates')->put($filePath, json_encode($jsonData, JSON_PRETTY_PRINT));

            // Create a download URL using our custom download route
            $downloadUrl = route('download.form-json', ['filename' => $filename]);

            // Send notification to user that file is ready
            Notification::make()
                ->success()
                ->title('JSON Export Complete')
                ->body("Your form JSON file has been generated successfully.")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('download')
                        ->label('Download JSON')
                        ->url($downloadUrl, shouldOpenInNewTab: true)
                        ->icon('heroicon-o-arrow-down-tray')
                ])
                ->persistent()
                ->sendToDatabase(User::find($this->userId));
        } catch (\Exception $e) {
            // Send error notification
            Notification::make()
                ->danger()
                ->title('JSON Export Failed')
                ->body("Failed to generate JSON file: " . $e->getMessage())
                ->persistent()
                ->sendToDatabase(User::find($this->userId));

            // Re-throw to mark job as failed
            throw $e;
        }
    }
}
