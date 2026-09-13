<?php

namespace App\Models;

use App\Data\ParcelStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelEvent extends Model
{
    protected $fillable = [
        'parcel_shipment_id',
        'status',
        'note',
        'actor_type',
        'actor_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParcelStatusEnum::class,
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ParcelShipment::class, 'parcel_shipment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
