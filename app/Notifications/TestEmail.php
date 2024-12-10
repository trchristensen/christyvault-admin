<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TestEmail extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test Email from Christy Vault')
            ->line('This is a test email from your application.')
            ->action('Visit Site', url('/'))
            ->line('Thank you for using our application!');
    }
}
