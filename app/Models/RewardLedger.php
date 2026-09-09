<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RewardLedger extends Model
{
    public const TYPE_REFERRAL_COMMISSION = 'referral_commission';

    public const TYPE_FEE_DISCOUNT = 'fee_discount';

    public const TYPE_CLAWBACK = 'clawback';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'amount',
        'balance_after',
        'transaction_id',
        'referral_commission_id',
        'note',
    ];

    protected static function booted(): void
    {
        static::creating(function (RewardLedger $ledger): void {
            $ledger->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
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

    public function commission(): BelongsTo
    {
        return $this->belongsTo(ReferralCommission::class, 'referral_commission_id');
    }
}
