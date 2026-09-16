<?php

namespace App\Models;

use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelScopeEnum;
use App\Data\ParcelStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ParcelShipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference',
        'user_id',
        'transaction_id',
        'trusted_payment_id',
        'travel_company_id',
        'travel_company_route_id',
        'scope',
        'origin_city',
        'destination_city',
        'delivery_mode',
        'status',
        'sender_name',
        'sender_phone',
        'sender_cnib_number',
        'sender_cnib_photo',
        'pickup_address',
        'pickup_district',
        'recipient_name',
        'recipient_phone',
        'recipient_cnib_number',
        'recipient_cnib_photo',
        'recipient_address',
        'recipient_district',
        'parcel_description',
        'parcel_photo',
        'estimated_weight_kg',
        'declared_value',
        'agency_fee',
        'margin_amount',
        'value_fee',
        'base_amount',
        'pickup_fee',
        'pickup_distance_km',
        'pickup_distance_fee',
        'delivery_fee',
        'delivery_distance_km',
        'delivery_distance_fee',
        'network_fee',
        'platform_fee',
        'total_amount',
        'currency',
        'pickup_code',
        'delivery_code',
        'station_pickup_code',
        'collected_at',
        'departed_at',
        'arrived_at',
        'delivered_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ParcelShipment $shipment): void {
            $shipment->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'delivery_mode' => ParcelDeliveryModeEnum::class,
            'scope' => ParcelScopeEnum::class,
            'status' => ParcelStatusEnum::class,
            'estimated_weight_kg' => 'decimal:2',
            'declared_value' => 'decimal:2',
            'agency_fee' => 'decimal:2',
            'margin_amount' => 'decimal:2',
            'value_fee' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'pickup_fee' => 'decimal:2',
            'pickup_distance_km' => 'decimal:2',
            'pickup_distance_fee' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'delivery_distance_km' => 'decimal:2',
            'delivery_distance_fee' => 'decimal:2',
            'network_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'collected_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function trustedPayment(): BelongsTo
    {
        return $this->belongsTo(TrustedPayment::class);
    }

    public function travelCompany(): BelongsTo
    {
        return $this->belongsTo(TravelCompany::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TravelCompanyRoute::class, 'travel_company_route_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ParcelEvent::class)->latest('id');
    }

    public function isDoorDelivery(): bool
    {
        return $this->delivery_mode === ParcelDeliveryModeEnum::DoorDelivery;
    }

    public function isLocal(): bool
    {
        return $this->scope === ParcelScopeEnum::Local;
    }

    /**
     * @return list<ParcelStatusEnum>
     */
    public function allowedNextStatuses(): array
    {
        $status = $this->status instanceof ParcelStatusEnum
            ? $this->status
            : ParcelStatusEnum::tryFrom((string) $this->status);

        $mode = $this->delivery_mode instanceof ParcelDeliveryModeEnum
            ? $this->delivery_mode
            : ParcelDeliveryModeEnum::tryFrom((string) $this->delivery_mode);

        $scope = $this->scope instanceof ParcelScopeEnum
            ? $this->scope
            : ParcelScopeEnum::tryFrom((string) ($this->scope ?? ParcelScopeEnum::Intercity->value));

        if (! $status || ! $mode) {
            return [];
        }

        return $status->allowedNext($mode, $scope ?? ParcelScopeEnum::Intercity);
    }

    public function corridorLabel(): string
    {
        if ($this->isLocal()) {
            $city = $this->origin_city ?: $this->destination_city ?: 'Ville';

            return $city.' (local)';
        }

        $departure = $this->origin_city ?: ($this->route?->departure ?? 'Départ');
        $arrival = $this->destination_city ?: ($this->route?->arrival ?? 'Arrivée');

        return sprintf('%s → %s', $departure, $arrival);
    }
}
