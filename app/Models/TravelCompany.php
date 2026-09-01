<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelCompany extends Model
{
    protected $fillable = [
        'name',
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

    public function routes(): HasMany
    {
        return $this->hasMany(TravelCompanyRoute::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(TravelCompanyStation::class);
    }
}
