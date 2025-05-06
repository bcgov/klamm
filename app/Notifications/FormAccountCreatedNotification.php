<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class FormAccountCreatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = URL::temporarySignedRoute(
            'filament.forms.auth.password-reset.reset',
            now()->addDays(3),
            [
                'email' => $notifiable->getEmailForPasswordReset(),
                'token' => app('auth.password.broker')->createToken($notifiable),
            ]
        );

        return (new MailMessage)
            ->subject('Welcome to ' . config('app.name'))
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You are receiving this email because you are involved in the Forms Modernization program for the Social Sector.')
            ->line('To manage our web and PDF form approval and publishing workflow we are using an interface called Klamm.')
            ->line('Please click the button below to set your password and access your account:')
            ->action('Set Your Password', $resetUrl)
            ->line('This link will expire in 3 days.')
            ->line('Thank you!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
