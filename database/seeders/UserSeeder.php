<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Database\Seeder;

/**
 * Comptes de démo / admin.
 *
 * PIN mobile : 1234
 * Mot de passe Filament : password
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $referrals = app(ReferralService::class);

        foreach ($this->users() as $attributes) {
            $user = User::query()->updateOrCreate(
                ['phone' => $attributes['phone']],
                $attributes,
            );

            $referrals->ensureReferralCode($user);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function users(): array
    {
        return [
            [
                'name' => 'Admin Facilya',
                'email' => 'admin@facilya.local',
                'phone' => '0700000000',
                'password' => 'password',
                'pin' => '1234',
                'email_verified_at' => now(),
                'reward_balance' => 0,
            ],
            [
                'name' => 'Awa Ouédraogo',
                'email' => 'awa@facilya.local',
                'phone' => '0700000011',
                'password' => 'password',
                'pin' => '1234',
                'email_verified_at' => now(),
                'reward_balance' => 0,
            ],
            [
                'name' => 'Issa Traoré',
                'email' => 'issa@facilya.local',
                'phone' => '0700000022',
                'password' => 'password',
                'pin' => '1234',
                'email_verified_at' => now(),
                'reward_balance' => 0,
            ],
            [
                'name' => 'Fatou Sawadogo',
                'email' => 'fatou@facilya.local',
                'phone' => '0700000033',
                'password' => 'password',
                'pin' => '1234',
                'email_verified_at' => now(),
                'reward_balance' => 0,
            ],
        ];
    }
}
