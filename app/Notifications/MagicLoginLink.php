<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLoginLink extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly int $expiryMinutes,
        public readonly array $metadata = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('passwordless-login::messages.email_subject'))
            ->greeting(__('passwordless-login::messages.email_greeting'))
            ->line(__('passwordless-login::messages.email_intro'))
            ->action(__('passwordless-login::messages.email_action'), $this->url)
            ->line(__('passwordless-login::messages.email_expiry_notice', [
                'minutes' => $this->expiryMinutes,
            ]))
            ->line(__('passwordless-login::messages.email_outro'))
            ->salutation(__('passwordless-login::messages.email_salutation').",\n".config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'url' => $this->url,
            'expiry_minutes' => $this->expiryMinutes,
            'metadata' => $this->metadata,
            'type' => 'magic_link',
        ];
    }
}
