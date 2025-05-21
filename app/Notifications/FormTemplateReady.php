<?php

namespace App\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FormTemplateReady extends Notification implements ShouldQueue
{
    use Queueable;

    protected $formTitle;
    protected $downloadUrl;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $formTitle, string $downloadUrl)
    {
        $this->formTitle = $formTitle;
        $this->downloadUrl = $downloadUrl;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Form Template Ready')
            ->line("Your form template for \"{$this->formTitle}\" is ready for download.")
            ->action('Download Template', $this->downloadUrl);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Form Template Ready',
            'body' => "Your form template for \"{$this->formTitle}\" is ready for download.",
            'actions' => [
                [
                    'name' => 'download',
                    'label' => 'Download Template',
                    'url' => $this->downloadUrl,
                    'shouldOpenInNewTab' => true,
                ],
            ],
        ];
    }

    /**
     * Get the Filament representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Form Template Ready')
            ->body("Your form template for \"{$this->formTitle}\" is ready for download.")
            ->actions([
                Action::make('download')
                    ->label('Download Template')
                    ->url($this->downloadUrl)
                    ->openUrlInNewTab(),
            ])
            ->getDatabaseMessage();
    }
}
