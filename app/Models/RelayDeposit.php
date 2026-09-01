<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RelayDeposit extends Model
{
    protected $fillable = [
        'uuid',
        'relay_device_id',
        'transaction_id',
        'network',
        'amount',
        'provider_transaction_id',
        'sender_phone',
        'sender_name',
        'received_at',
        'raw_body',
        'matched_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (RelayDeposit $deposit): void {
            $deposit->uuid ??= (string) Str::uuid();
            $deposit->network = strtolower((string) $deposit->network);
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'matched_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RelayDevice::class, 'relay_device_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
