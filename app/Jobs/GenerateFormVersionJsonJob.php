<?php

namespace App\Jobs;

use App\Models\FormBuilding\CheckboxGroupFormElement;
use App\Models\FormBuilding\FormVersion;
use App\Models\FormBuilding\RadioInputFormElement;
use App\Models\FormBuilding\SelectInputFormElement;
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
    ) {
    }

    public function handle(): void
    {
        try {
            $jsonService = new FormVersionJsonService();

            $formVersion = FormVersion::with([
                'form',
                'formElements.elementable' => function ($morphTo) {
                    $morphTo->morphWith([
                        SelectInputFormElement::class => ['options'],
                        RadioInputFormElement::class => ['options'],
                        CheckboxGroupFormElement::class => ['options'],
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
                    // update updated_at when downloading JSON
                    $exportedAt = now('UTC');
                    $jsonData = $jsonService->generateJson($formVersion, $exportedAt);
                    break;
                default:
                    throw new \Exception("Unsupported format version: {$this->version}");
            }

            // Create filename with form title and version
            $form_id = $this->formVersion->form->form_id;
            $versionNumber = $this->formVersion->version_number;
            $formTitle = $this->formVersion->form->form_title ?? 'Unknown Form';
            $sanitizedTitle = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $formTitle);
            $filename = "form_{$form_id}_v{$versionNumber}_{$sanitizedTitle}.json";

            // Store the JSON file
            $filePath = "{$filename}";
            Storage::disk('templates')->put($filePath, json_encode($jsonData, JSON_PRETTY_PRINT));

            // Create a download URL using our custom download route
            $downloadUrl = route('download.form-json', ['filename' => $filename]);

            // Send notification to user that file is ready
            Notification::make()
                ->success()
                ->title('JSON Export Complete')
                ->body("Your export {$this->formVersion->form->form_id} \"{$formTitle}\" has been generated successfully.")
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
