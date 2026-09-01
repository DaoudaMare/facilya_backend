<?php

namespace App\Models;

use App\Data\PaymentStatusEnum;
use App\Data\ServiceStatusEnum;
use App\Data\TransactionTypeEnum;
use App\Services\TransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference',
        'user_id',
        'type',
        'payment_status',
        'service_status',
        'amount',
        'network_fee',
        'platform_fee',
        'currency',
        'description',
        'payment_network_id',
        'payment_reference',
        'service_reference',
        'metadata',
        'paid_at',
        'payment_expires_at',
        'served_at',
        'payment_failure_reason',
        'service_failure_reason',
        'travel_company_trip_id',
        'travel_company_route_id',
        'travel_date',
        'passenger_name',
        'passenger_phone',
        'passenger_count',
        'source_network_id',
        'destination_network_id',
        'sender_phone',
        'recipient_phone',
        'recipient_name',
    ];

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction): void {
            app(TransactionService::class)->prepareDefaults($transaction);
        });

        static::saving(function (Transaction $transaction): void {
            app(TransactionService::class)->hydrate($transaction);
        });
    }

    protected function casts(): array
    {
        return [
            'type' => TransactionTypeEnum::class,
            'payment_status' => PaymentStatusEnum::class,
            'service_status' => ServiceStatusEnum::class,
            'amount' => 'decimal:2',
            'network_fee' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'served_at' => 'datetime',
            'travel_date' => 'date',
            'passenger_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(TravelCompanyTrip::class, 'travel_company_trip_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TravelCompanyRoute::class, 'travel_company_route_id');
    }

    public function paymentNetwork(): BelongsTo
    {
        return $this->belongsTo(TransferNetwork::class, 'payment_network_id');
    }

    public function sourceNetwork(): BelongsTo
    {
        return $this->belongsTo(TransferNetwork::class, 'source_network_id');
    }

    public function destinationNetwork(): BelongsTo
    {
        return $this->belongsTo(TransferNetwork::class, 'destination_network_id');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(RelayDeposit::class);
    }

    public function relayJobs(): HasMany
    {
        return $this->hasMany(RelayJob::class);
    }

    public function payingNetwork(): ?TransferNetwork
    {
        return $this->isTicketPurchase() ? $this->paymentNetwork : $this->sourceNetwork;
    }

    public function payerPhone(): ?string
    {
        return $this->isTicketPurchase() ? $this->passenger_phone : $this->sender_phone;
    }

    public static function newTicketPurchase(array $attributes): static
    {
        return app(TransactionService::class)->createTicketPurchase($attributes);
    }

    public static function newNetworkTransfer(array $attributes): static
    {
        return app(TransactionService::class)->createNetworkTransfer($attributes);
    }

    public function isTicketPurchase(): bool
    {
        return $this->type === TransactionTypeEnum::TICKET_PURCHASE;
    }

    public function isNetworkTransfer(): bool
    {
        return $this->type === TransactionTypeEnum::NETWORK_TRANSFER;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatusEnum::RECEIVED;
    }

    public function isServed(): bool
    {
        return $this->service_status === ServiceStatusEnum::DELIVERED;
    }

    public function isFullyCompleted(): bool
    {
        return $this->isPaid() && $this->isServed();
    }

    public function totalFees(): string
    {
        return bcadd((string) $this->network_fee, (string) $this->platform_fee, 2);
    }

    public function totalAmount(): string
    {
        return bcadd((string) $this->amount, $this->totalFees(), 2);
    }

    public function markPaymentProcessing(): bool
    {
        app(TransactionService::class)->markPaymentProcessing($this);

        return true;
    }

    public function markPaymentReceived(?string $paymentReference = null): bool
    {
        app(TransactionService::class)->markPaymentReceived($this, $paymentReference);

        return true;
    }

    public function markPaymentFailed(string $reason): bool
    {
        app(TransactionService::class)->markPaymentFailed($this, $reason);

        return true;
    }

    public function markServiceProcessing(): bool
    {
        app(TransactionService::class)->markServiceProcessing($this);

        return true;
    }

    public function markServiceDelivered(?string $serviceReference = null): bool
    {
        app(TransactionService::class)->markServiceDelivered($this, $serviceReference);

        return true;
    }

    public function markServiceFailed(string $reason): bool
    {
        app(TransactionService::class)->markServiceFailed($this, $reason);

        return true;
    }

    public function scopeTicketPurchases(Builder $query): Builder
    {
        return $query->where('type', TransactionTypeEnum::TICKET_PURCHASE);
    }

    public function scopeNetworkTransfers(Builder $query): Builder
    {
        return $query->where('type', TransactionTypeEnum::NETWORK_TRANSFER);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', PaymentStatusEnum::RECEIVED);
    }

    public function scopeAwaitingService(Builder $query): Builder
    {
        return $query
            ->where('payment_status', PaymentStatusEnum::RECEIVED)
            ->where('service_status', ServiceStatusEnum::PENDING);
    }

    public function scopeFullyCompleted(Builder $query): Builder
    {
        return $query
            ->where('payment_status', PaymentStatusEnum::RECEIVED)
            ->where('service_status', ServiceStatusEnum::DELIVERED);
    }

    public function applyQuotedFees(): void
    {
        app(TransactionService::class)->applyQuotedFees($this);
    }
}
