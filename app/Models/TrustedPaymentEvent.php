<?php

namespace App\Models;

use App\Data\TrustedPaymentStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedPaymentEvent extends Model
{
    protected $fillable = [
        'trusted_payment_id',
        'status',
        'note',
        'actor_type',
        'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrustedPaymentStatusEnum::class,
        ];
    }

    public function trustedPayment(): BelongsTo
    {
        return $this->belongsTo(TrustedPayment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
