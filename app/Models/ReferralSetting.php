<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSetting extends Model
{
    protected $fillable = [
        'is_active',
        'commission_percent',
        'max_rewarded_transactions',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_percent' => 'decimal:4',
            'max_rewarded_transactions' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'is_active' => true,
            'commission_percent' => 0.5,
            'max_rewarded_transactions' => 10,
        ]);
    }
}
