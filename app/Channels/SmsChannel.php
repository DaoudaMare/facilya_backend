<?php

namespace App\Channels;

use App\Notifications\OtpCodeNotification;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof OtpCodeNotification) {
            return;
        }

        $to = $this->recipient($notifiable);
        $message = $notification->toSms($notifiable);

        if ($to === '') {
            return;
        }

        $driver = config('services.sms.driver', 'log');

        if ($driver === 'http') {
            $this->sendHttp($to, $message);

            return;
        }

        Log::info('OTP SMS', [
            'to' => $to,
            'message' => $message,
        ]);
    }

    protected function sendHttp(string $to, string $message): void
    {
        $url = config('services.sms.url');

        if (! is_string($url) || $url === '') {
            throw ValidationException::withMessages([
                'channel' => 'L’envoi SMS n’est pas configuré.',
            ]);
        }

        try {
            Http::withToken((string) config('services.sms.token'))
                ->acceptJson()
                ->post($url, [
                    'to' => $to,
                    'message' => $message,
                ])
                ->throw();
        } catch (RequestException $exception) {
            Log::error('OTP SMS HTTP failed', [
                'to' => $to,
                'status' => $exception->response?->status(),
            ]);

            throw ValidationException::withMessages([
                'channel' => 'Impossible d’envoyer le SMS pour le moment.',
            ]);
        }
    }

    protected function recipient(object $notifiable): string
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor(self::class)
                ?? $notifiable->routeNotificationFor('sms');

            if (is_string($route) && $route !== '') {
                return $route;
            }
        }

        return (string) ($notifiable->phone ?? '');
    }
}
