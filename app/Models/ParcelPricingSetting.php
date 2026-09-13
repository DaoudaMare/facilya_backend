<?php

namespace App\Models;

use App\Data\ParcelFeeModeEnum;
use Illuminate\Database\Eloquent\Model;

class ParcelPricingSetting extends Model
{
    protected $fillable = [
        'agency_mode',
        'agency_value',
        'margin_mode',
        'margin_value',
        'pickup_mode',
        'pickup_value',
        'pickup_per_km',
        'delivery_mode',
        'delivery_value',
        'delivery_per_km',
        'value_fee_percent',
    ];

    protected function casts(): array
    {
        return [
            'agency_mode' => ParcelFeeModeEnum::class,
            'agency_value' => 'decimal:4',
            'margin_mode' => ParcelFeeModeEnum::class,
            'margin_value' => 'decimal:4',
            'pickup_mode' => ParcelFeeModeEnum::class,
            'pickup_value' => 'decimal:4',
            'pickup_per_km' => 'decimal:4',
            'delivery_mode' => ParcelFeeModeEnum::class,
            'delivery_value' => 'decimal:4',
            'delivery_per_km' => 'decimal:4',
            'value_fee_percent' => 'decimal:4',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'agency_mode' => ParcelFeeModeEnum::Fixed,
            'agency_value' => 0,
            'margin_mode' => ParcelFeeModeEnum::Fixed,
            'margin_value' => 0,
            'pickup_mode' => ParcelFeeModeEnum::Fixed,
            'pickup_value' => 0,
            'pickup_per_km' => 0,
            'delivery_mode' => ParcelFeeModeEnum::Fixed,
            'delivery_value' => 0,
            'delivery_per_km' => 0,
            'value_fee_percent' => 0,
        ]);
    }
}
