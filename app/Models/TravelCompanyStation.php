<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelCompanyStation extends Model
{
    protected $fillable = [
        'travel_company_id',
        'station_name',
        'email',
        'phone',
        'address',
        'google_maps_link',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function travelCompany(): BelongsTo
    {
        return $this->belongsTo(TravelCompany::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(TravelCompanyTrip::class);
    }
}
