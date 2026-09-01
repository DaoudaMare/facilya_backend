<?php

namespace App\Http\Resources;

use App\Data\PaymentStatusEnum;
use App\Data\ServiceStatusEnum;
use App\Data\TransactionTypeEnum;
use App\Models\Transaction;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'type' => $this->type instanceof TransactionTypeEnum ? $this->type->value : $this->type,
            'payment_status' => $this->payment_status instanceof PaymentStatusEnum
                ? $this->payment_status->value
                : $this->payment_status,
            'service_status' => $this->service_status instanceof ServiceStatusEnum
                ? $this->service_status->value
                : $this->service_status,
            'amount' => (float) $this->amount,
            'network_fee' => (float) $this->network_fee,
            'platform_fee' => (float) $this->platform_fee,
            'total_fee' => (float) $this->totalFees(),
            'total_amount' => (float) $this->totalAmount(),
            'currency' => $this->currency,
            'title' => $this->displayTitle(),
            'subtitle' => $this->displaySubtitle(),
            'ui_status' => $this->uiStatus(),
            'payment_expires_at' => $this->payment_expires_at?->toIso8601String(),
            'payment_receive_phone' => $this->payingNetwork()?->receive_phone,
            'payment_ussd' => $this->payingNetwork()?->paymentUssdCode($this->totalAmount()),
            'created_at' => $this->created_at?->toIso8601String(),
            'travel_date' => $this->travel_date?->toDateString(),
            'passenger_name' => $this->passenger_name,
            'passenger_count' => $this->passenger_count,
            'sender_phone' => $this->sender_phone,
            'recipient_phone' => $this->recipient_phone,
            'recipient_name' => $this->recipient_name,
            'source_network' => TransferNetworkResource::make($this->whenLoaded('sourceNetwork')),
            'destination_network' => TransferNetworkResource::make($this->whenLoaded('destinationNetwork')),
            'payment_network' => TransferNetworkResource::make($this->whenLoaded('paymentNetwork')),
        ];
    }

    protected function displayTitle(): string
    {
        if ($this->isNetworkTransfer()) {
            $from = $this->sourceNetwork?->name ?? 'Réseau';
            $to = $this->destinationNetwork?->name ?? 'Réseau';

            return sprintf('%s → %s', $from, $to);
        }

        $departure = $this->route?->departure ?? 'Départ';
        $arrival = $this->route?->arrival ?? 'Arrivée';

        return sprintf('%s → %s', $departure, $arrival);
    }

    protected function displaySubtitle(): string
    {
        if ($this->isNetworkTransfer()) {
            $who = $this->recipient_name ?: Phone::format((string) $this->recipient_phone);

            return trim($who.' · '.$this->created_at?->format('d/m, H\\hi'));
        }

        $company = $this->route?->travelCompany?->name;
        $date = $this->travel_date?->format('d/m');
        $pax = max(1, (int) $this->passenger_count);

        return trim(implode(' · ', array_filter([
            $company,
            $date,
            $pax.' pax',
        ])));
    }

    protected function uiStatus(): string
    {
        if ($this->payment_status === PaymentStatusEnum::FAILED
            || $this->service_status === ServiceStatusEnum::FAILED) {
            return 'fail';
        }

        if ($this->isFullyCompleted()) {
            return 'success';
        }

        return 'pending';
    }
}
