<?php

namespace App\Models;

use App\Data\TravelTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelCompanyRoute extends Model
{
    protected $fillable = [
        'travel_company_id',
        'departure',
        'arrival',
        'travel_type',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'travel_type' => TravelTypeEnum::class,
            'price' => 'decimal:2',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function label(): string
    {
        return sprintf(
            '%s — %s → %s (%s)',
            $this->travelCompany?->name,
            $this->departure,
            $this->arrival,
            $this->travel_type?->label() ?? $this->travel_type,
        );
    }
}
