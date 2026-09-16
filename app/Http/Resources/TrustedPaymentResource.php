<?php

namespace App\Http\Resources;

use App\Data\TrustedPaymentStatusEnum;
use App\Data\TrustedPayoutStatusEnum;
use App\Models\TrustedPayment;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrustedPayment
 */
class TrustedPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof TrustedPaymentStatusEnum
            ? $this->status
            : TrustedPaymentStatusEnum::tryFrom((string) $this->status);

        $payoutStatus = $this->payout_status instanceof TrustedPayoutStatusEnum
            ? $this->payout_status
            : TrustedPayoutStatusEnum::tryFrom((string) $this->payout_status);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'tracking_label' => $status?->trackingLabel(),
            'tracking_steps' => $status?->trackingTimeline() ?? [],
            'payout_status' => $payoutStatus?->value,
            'payout_status_label' => $payoutStatus?->label(),
            'product_description' => $this->product_description,
            'declared_value' => $this->declared_value !== null ? (float) $this->declared_value : null,
            'buyer' => [
                'id' => $this->buyer?->id,
                'name' => $this->buyer?->name,
                'phone' => Phone::format((string) ($this->buyer?->phone ?? '')),
            ],
            'merchant' => [
                'id' => $this->merchant?->id,
                'name' => $this->merchant?->name,
                'phone' => Phone::format((string) ($this->merchant?->phone ?? '')),
            ],
            'buyer_deposit_phone' => Phone::format((string) $this->buyer_deposit_phone),
            'merchant_payout_phone' => Phone::format((string) $this->merchant_payout_phone),
            'pickup_address' => $this->pickup_address,
            'pickup_district' => $this->pickup_district,
            'delivery_address' => $this->delivery_address,
            'delivery_district' => $this->delivery_district,
            'pricing' => [
                'merchandise_amount' => (float) $this->merchandise_amount,
                'network_fee' => (float) $this->network_fee,
                'platform_fee' => (float) $this->platform_fee,
                'total_amount' => (float) $this->total_amount,
                'payout_amount' => (float) $this->payout_amount,
                'currency' => $this->currency,
            ],
            'payout_reference' => $this->payout_reference,
            'parcel_shipment' => $this->when(
                $this->relationLoaded('parcelShipment') && $this->parcelShipment,
                fn () => [
                    'uuid' => $this->parcelShipment->uuid,
                    'reference' => $this->parcelShipment->reference,
                ],
            ),
            'timestamps' => [
                'funds_held_at' => $this->funds_held_at?->toIso8601String(),
                'expedition_requested_at' => $this->expedition_requested_at?->toIso8601String(),
                'collected_at' => $this->collected_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
                'payout_sent_at' => $this->payout_sent_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
            ],
            'transaction' => TransactionResource::make($this->whenLoaded('transaction')),
            'events' => $this->whenLoaded('events', function () {
                return $this->events->map(function ($event) {
                    $status = $event->status instanceof TrustedPaymentStatusEnum
                        ? $event->status
                        : TrustedPaymentStatusEnum::tryFrom((string) $event->status);

                    return [
                        'status' => $status?->value,
                        'status_label' => $status?->label(),
                        'note' => $event->note,
                        'actor_type' => $event->actor_type,
                        'created_at' => $event->created_at?->toIso8601String(),
                    ];
                })->values();
            }),
        ];
    }
}
