<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Channels\WhatsAppChannel;
use App\Models\TrustedPayment;
use Illuminate\Notifications\Notification;

class MerchantFundsHeldNotification extends Notification
{
    public function __construct(
        public TrustedPayment $payment,
    ) {}

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class, SmsChannel::class];
    }

    public function toSms(object $notifiable): string
    {
        $amount = number_format((float) $this->payment->merchandise_amount, 0, ',', ' ');

        return sprintf(
            'Facilya : un paiement confiant a été bloqué. Identifiant %s · %s F CFA. Conservez cet identifiant. Le code de validation n’est pas transmis.',
            $this->payment->reference,
            $amount,
        );
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->toSms($notifiable);
    }
}
