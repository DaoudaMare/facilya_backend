<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Referral extends Model
{
    protected $fillable = [
        'uuid',
        'referrer_id',
        'referee_id',
        'code_used',
        'status',
        'rewarded_transactions',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Referral $referral): void {
            $referral->uuid ??= (string) Str::uuid();
            $referral->code_used = strtoupper(trim((string) $referral->code_used));
        });
    }

    protected function casts(): array
    {
        return [
            'rewarded_transactions' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
