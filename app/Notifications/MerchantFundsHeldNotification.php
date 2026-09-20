<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Channels\WhatsAppChannel;
use App\Models\TrustedPayment;
use App\Support\Phone;
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
        $this->payment->loadMissing('buyer');
        $amount = number_format((float) $this->payment->merchandise_amount, 0, ',', ' ');
        $client = trim((string) ($this->payment->buyer?->name ?? 'Client Facilya'));
        $phone = Phone::format((string) ($this->payment->buyer?->phone ?: $this->payment->buyer_deposit_phone));
        $description = trim((string) $this->payment->product_description);

        return implode("\n", [
            'Facilya : fonds bloqués sur un paiement confiant.',
            'Identifiant : '.$this->payment->public_id,
            'Montant : '.$amount.' F CFA',
            'Client : '.$client,
            'Tél. client : '.$phone,
            'Produit / service : '.$description,
            'Conservez l’identifiant. Le code de validation n’est pas transmis.',
        ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->toSms($notifiable);
    }
}
