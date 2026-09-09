<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReferralCommission extends Model
{
    protected $fillable = [
        'uuid',
        'referral_id',
        'transaction_id',
        'referrer_id',
        'referee_id',
        'base_amount',
        'commission_percent',
        'commission_amount',
        'sequence',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReferralCommission $commission): void {
            $commission->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_percent' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'sequence' => 'integer',
        ];
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }
}
