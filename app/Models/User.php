<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_secondary',
        'cnib_number',
        'cnib_photo',
        'password',
        'pin',
        'referral_code',
        'referred_by_user_id',
        'reward_balance',
        'role_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'reward_balance' => 'decimal:2',
        ];
    }

    public function hasPin(): bool
    {
        return filled($this->pin);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function publicEmail(): ?string
    {
        $email = (string) $this->email;

        if ($email === '' || str_ends_with($email, '@users.facilya.local')) {
            return null;
        }

        return $email;
    }

    public function isEmailAccount(): bool
    {
        return filled($this->publicEmail());
    }

    public function hasRealName(): bool
    {
        return filled($this->first_name) && filled($this->last_name);
    }

    /**
     * @return list<string>
     */
    public function profileMissing(): array
    {
        $missing = [];

        if (! $this->hasRealName()) {
            $missing[] = 'name';
        }

        if ($this->isEmailAccount() && blank($this->phone)) {
            $missing[] = 'phone';
        }

        if ($this->addresses->isEmpty()) {
            $missing[] = 'pickup_address';
        }

        return $missing;
    }

    public function isProfileComplete(): bool
    {
        return $this->profileMissing() === [];
    }

    public function cnibPhotoUrl(): ?string
    {
        if (! filled($this->cnib_photo)) {
            return null;
        }

        return asset('storage/'.$this->cnib_photo);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_user_id');
    }

    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referralAsReferee(): HasOne
    {
        return $this->hasOne(Referral::class, 'referee_id');
    }

    public function rewardLedgers(): HasMany
    {
        return $this->hasMany(RewardLedger::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $slug): bool
    {
        $this->loadMissing('role.permissions');

        return (bool) $this->role?->hasPermission($slug);
    }

    public function hasAnyPermission(string ...$slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $this->loadMissing('role');

        return (bool) $this->role?->can_access_panel;
    }
}
