<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteUserNotification extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public string $token;

    /**
     * Create a new notification instance.
     * We pass the token directly into the constructor.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
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
        // This is the core of the fix.
        // We manually build the URL to point to our Vue frontend.
        $url = $this->buildSetPasswordUrl($notifiable);

        return (new MailMessage)
                    ->subject('You have been invited to join!')
                    ->line('You are receiving this email because you have been invited to join the team.')
                    ->action('Set Your Password', $url)
                    ->line('This password set link will expire in 60 minutes.')
                    ->line('If you did not expect this invitation, no further action is required.');
    }

    /**
     * Builds the password reset URL.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function buildSetPasswordUrl(object $notifiable): string
    {
        return config('app.frontend_url') . 
               '/set-password/' . 
               $this->token . 
               '?email=' . urlencode($notifiable->getEmailForPasswordReset());
    }
}