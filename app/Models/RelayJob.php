<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RelayJob extends Model
{
    protected $fillable = [
        'uuid',
        'transaction_id',
        'relay_device_id',
        'type',
        'status',
        'network',
        'recipient_phone',
        'recipient_name',
        'amount',
        'currency',
        'provider_reference',
        'failure_reason',
        'message',
        'claimed_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (RelayJob $job): void {
            $job->uuid ??= (string) Str::uuid();
            $job->network = strtolower((string) $job->network);
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(RelayDevice::class, 'relay_device_id');
    }
}
