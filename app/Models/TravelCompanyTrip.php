<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelCompanyTrip extends Model
{
    protected $fillable = [
        'travel_company_route_id',
        'travel_company_station_id',
        'departure_hour',
        'arrival_hour',
        'available_seats',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'available_seats' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TravelCompanyRoute::class, 'travel_company_route_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(TravelCompanyStation::class, 'travel_company_station_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function formattedDeparture(): string
    {
        return $this->formatHour($this->departure_hour);
    }

    public function formattedArrival(): string
    {
        return $this->formatHour($this->arrival_hour);
    }

    public function durationLabel(): string
    {
        if (! $this->arrival_hour) {
            return '—';
        }

        $departure = Carbon::parse((string) $this->departure_hour);
        $arrival = Carbon::parse((string) $this->arrival_hour);

        if ($arrival->lessThan($departure)) {
            $arrival->addDay();
        }

        $minutes = $departure->diffInMinutes($arrival);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return sprintf('%dh%02d', $hours, $rest);
    }

    protected function formatHour(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return substr((string) $value, 0, 5);
    }
}
