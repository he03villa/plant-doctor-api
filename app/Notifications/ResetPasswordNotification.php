<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $token,
        private string $platform = 'web'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $webUrl = $this->buildUrl(config('app.web_reset_url'), $notifiable->email);

        $url = $this->platform === 'mobile'
            ? $this->buildUrl(config('app.mobile_reset_url'), $notifiable->email)
            : $webUrl;

        return (new MailMessage)
            ->subject('Recupera tu contraseña')
            ->markdown('emails.reset-password', [
                'name' => $notifiable->name,
                'url' => $url,
                'webUrl' => $webUrl,
                'showWebFallback' => $this->platform === 'mobile',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }

    private function buildUrl(?string $base, string $email): string
    {
        $base = $base ?: config('app.web_reset_url');

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);
    }
}
