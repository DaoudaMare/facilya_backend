<?php

namespace App\Http\Resources;

use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelStatusEnum;
use App\Models\ParcelShipment;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParcelShipment
 */
class ParcelShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mode = $this->delivery_mode instanceof ParcelDeliveryModeEnum
            ? $this->delivery_mode
            : ParcelDeliveryModeEnum::tryFrom((string) $this->delivery_mode);

        $status = $this->status instanceof ParcelStatusEnum
            ? $this->status
            : ParcelStatusEnum::tryFrom((string) $this->status);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'delivery_mode' => $mode?->value,
            'delivery_mode_label' => $mode?->label(),
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'tracking_label' => $status?->trackingLabel(),
            'tracking_steps' => $status && $mode
                ? $status->trackingTimeline($mode)
                : [],
            'corridor' => $this->corridorLabel(),
            'departure' => $this->route?->departure,
            'arrival' => $this->route?->arrival,
            'company' => $this->travelCompany?->name ?? $this->route?->travelCompany?->name,
            'sender' => [
                'name' => $this->sender_name,
                'phone' => Phone::format((string) $this->sender_phone),
                'cnib_number' => $this->sender_cnib_number,
                'cnib_photo_url' => $this->cnibUrl($this->sender_cnib_photo),
                'address' => $this->pickup_address,
                'district' => $this->pickup_district,
            ],
            'recipient' => [
                'name' => $this->recipient_name,
                'phone' => Phone::format((string) $this->recipient_phone),
                'cnib_number' => $this->recipient_cnib_number,
                'cnib_photo_url' => $this->cnibUrl($this->recipient_cnib_photo),
                'address' => $this->recipient_address,
                'district' => $this->recipient_district,
            ],
            'parcel_description' => $this->parcel_description,
            'estimated_weight_kg' => $this->estimated_weight_kg !== null ? (float) $this->estimated_weight_kg : null,
            'declared_value' => $this->declared_value !== null ? (float) $this->declared_value : null,
            'pricing' => [
                'agency_fee' => (float) $this->agency_fee,
                'margin_amount' => (float) $this->margin_amount,
                'value_fee' => (float) ($this->value_fee ?? 0),
                'base_amount' => (float) $this->base_amount,
                'pickup_fee' => (float) $this->pickup_fee,
                'pickup_distance_km' => $this->pickup_distance_km !== null ? (float) $this->pickup_distance_km : null,
                'pickup_distance_fee' => (float) $this->pickup_distance_fee,
                'delivery_fee' => (float) $this->delivery_fee,
                'delivery_distance_km' => $this->delivery_distance_km !== null ? (float) $this->delivery_distance_km : null,
                'delivery_distance_fee' => (float) $this->delivery_distance_fee,
                'network_fee' => (float) $this->network_fee,
                'platform_fee' => (float) $this->platform_fee,
                'total_amount' => (float) $this->total_amount,
                'currency' => $this->currency,
            ],
            'codes' => [
                'pickup_code' => $this->pickup_code,
                'delivery_code' => $this->delivery_code,
                'station_pickup_code' => $this->station_pickup_code,
            ],
            'timestamps' => [
                'collected_at' => $this->collected_at?->toIso8601String(),
                'departed_at' => $this->departed_at?->toIso8601String(),
                'arrived_at' => $this->arrived_at?->toIso8601String(),
                'delivered_at' => $this->delivered_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
            ],
            'transaction' => TransactionResource::make($this->whenLoaded('transaction')),
            'events' => $this->whenLoaded('events', function () {
                return $this->events->map(function ($event) {
                    $status = $event->status instanceof ParcelStatusEnum
                        ? $event->status
                        : ParcelStatusEnum::tryFrom((string) $event->status);

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

    protected function cnibUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }
}
