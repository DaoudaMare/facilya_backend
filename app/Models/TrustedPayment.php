<?php

namespace App\Models;

use App\Data\TrustedPaymentStatusEnum;
use App\Data\TrustedPayoutStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TrustedPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference',
        'buyer_user_id',
        'merchant_user_id',
        'transaction_id',
        'status',
        'payout_status',
        'merchandise_amount',
        'network_fee',
        'platform_fee',
        'total_amount',
        'payout_amount',
        'currency',
        'buyer_deposit_phone',
        'merchant_payout_phone',
        'payment_network_id',
        'payout_network_id',
        'product_description',
        'declared_value',
        'pickup_address',
        'pickup_district',
        'delivery_address',
        'delivery_district',
        'payout_reference',
        'payout_relay_job_id',
        'funds_held_at',
        'expedition_requested_at',
        'collected_at',
        'delivered_at',
        'payout_sent_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (TrustedPayment $payment): void {
            $payment->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TrustedPaymentStatusEnum::class,
            'payout_status' => TrustedPayoutStatusEnum::class,
            'merchandise_amount' => 'decimal:2',
            'network_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payout_amount' => 'decimal:2',
            'declared_value' => 'decimal:2',
            'funds_held_at' => 'datetime',
            'expedition_requested_at' => 'datetime',
            'collected_at' => 'datetime',
            'delivered_at' => 'datetime',
            'payout_sent_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_user_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paymentNetwork(): BelongsTo
    {
        return $this->belongsTo(TransferNetwork::class, 'payment_network_id');
    }

    public function payoutNetwork(): BelongsTo
    {
        return $this->belongsTo(TransferNetwork::class, 'payout_network_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TrustedPaymentEvent::class)->latest('id');
    }

    public function parcelShipment(): HasOne
    {
        return $this->hasOne(ParcelShipment::class);
    }
}
