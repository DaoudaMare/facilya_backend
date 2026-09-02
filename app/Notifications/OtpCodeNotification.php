<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Channels\WhatsAppChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    public function __construct(
        public string $code,
        public string $channel,
    ) {}

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        return match ($this->channel) {
            'email' => ['mail'],
            'whatsapp' => [WhatsAppChannel::class],
            default => [SmsChannel::class],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code Facilya')
            ->view('emails.otp', ['code' => $this->code])
            ->text('emails.otp-text', ['code' => $this->code]);
    }

    public function toSms(object $notifiable): string
    {
        return "Facilya : votre code de verification est {$this->code}. Valable 10 min.";
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->toSms($notifiable);
    }
}
