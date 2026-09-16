<?php

namespace Database\Seeders;

use App\Models\Role;
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
        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
        $clientRoleId = Role::query()->where('slug', 'client')->value('id');

        foreach ($this->users() as $attributes) {
            $slug = $attributes['role_slug'] ?? 'client';
            unset($attributes['role_slug']);

            $attributes['role_id'] = $slug === 'admin' ? $adminRoleId : $clientRoleId;

            $user = User::query()->updateOrCreate(
                ['email' => $attributes['email']],
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
                'first_name' => 'Admin',
                'last_name' => 'Facilya',
                'email' => 'admin@facilya.local',
                'phone' => '0700000000',
                'password' => 'password',
                'pin' => '1234',
                'email_verified_at' => now(),
                'reward_balance' => 0,
                'role_slug' => 'admin',
            ],
            [
                'name' => 'Awa Ouédraogo',
                'first_name' => 'Awa',
                'last_name' => 'Ouédraogo',
                'email' => 'awa@facilya.local',
                'phone' => '0700000011',
                'password' => 'password',
                'pin' => '1234',
                'email_verified_at' => now(),
                'reward_balance' => 0,
                'role_slug' => 'client',
            ],
            [
                'name' => 'Issa Traoré',
                'first_name' => 'Issa',
                'last_name' => 'Traoré',
                'email' => 'issa@facilya.local',
                'phone' => '0700000022',
                'password' => 'password',
                'pin' => '1234',
                'email_verified_at' => now(),
                'reward_balance' => 0,
                'role_slug' => 'client',
            ],
            [
                'name' => 'Fatou Sawadogo',
                'first_name' => 'Fatou',
                'last_name' => 'Sawadogo',
                'email' => 'fatou@facilya.local',
                'phone' => '0700000033',
                'password' => 'password',
                'pin' => '1234',
                'email_verified_at' => now(),
                'reward_balance' => 0,
                'role_slug' => 'client',
            ],
        ];
    }
}
