<?php

namespace App\Channels;

use App\Notifications\OtpCodeNotification;
use App\Services\ZapwiseWhatsAppService;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(
        protected ZapwiseWhatsAppService $zapwise,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof OtpCodeNotification) {
            return;
        }

        $to = $this->recipient($notifiable);

        if ($to === '') {
            return;
        }

        $this->zapwise->sendText($to, $notification->toWhatsApp($notifiable));
    }

    protected function recipient(object $notifiable): string
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor(self::class)
                ?? $notifiable->routeNotificationFor('whatsapp');

            if (is_string($route) && $route !== '') {
                return $route;
            }
        }

        return (string) ($notifiable->phone ?? '');
    }
}
