<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class RelayDevice extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'uuid',
        'name',
        'network',
        'phone_number',
        'fulfill_networks',
        'last_seen_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (RelayDevice $device): void {
            $device->uuid ??= (string) Str::uuid();
            $device->network = strtolower((string) $device->network);
        });
    }

    protected function casts(): array
    {
        return [
            'fulfill_networks' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function fulfillNetworkCodes(): array
    {
        $codes = collect($this->fulfill_networks ?? [])
            ->map(fn ($code) => strtolower(trim((string) $code)))
            ->filter()
            ->values()
            ->all();

        if ($codes === [] && filled($this->network)) {
            return [strtolower((string) $this->network)];
        }

        return $codes;
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(RelayDeposit::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(RelayJob::class);
    }
}
