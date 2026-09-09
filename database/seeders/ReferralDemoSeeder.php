<?php

namespace Database\Seeders;

use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de test pour le parrainage.
 *
 * Parrain : 0700000001 / code FAC0001TEST
 * Filleul : 0700000002 (lié au parrain)
 * PIN : 1234
 */
class ReferralDemoSeeder extends Seeder
{
    public function run(): void
    {
        ReferralSetting::current()->fill([
            'is_active' => true,
            'commission_percent' => 0.5,
            'max_rewarded_transactions' => 10,
        ])->save();

        $referrals = app(ReferralService::class);

        $parrain = User::query()->updateOrCreate(
            ['phone' => '0700000001'],
            [
                'name' => 'Parrain Demo',
                'email' => '0700000001@users.facilya.local',
                'password' => Hash::make('password'),
                'pin' => '1234',
                'email_verified_at' => now(),
                'referral_code' => 'FAC0001TEST',
                'reward_balance' => 0,
            ],
        );
        $parrain = $referrals->ensureReferralCode($parrain);
        if ($parrain->referral_code !== 'FAC0001TEST') {
            $parrain->forceFill(['referral_code' => 'FAC0001TEST'])->save();
        }

        $filleul = User::query()->updateOrCreate(
            ['phone' => '0700000002'],
            [
                'name' => 'Filleul Demo',
                'email' => '0700000002@users.facilya.local',
                'password' => Hash::make('password'),
                'pin' => '1234',
                'email_verified_at' => now(),
            ],
        );
        $filleul = $referrals->ensureReferralCode($filleul);

        if (! $filleul->referred_by_user_id) {
            $filleul->forceFill(['referred_by_user_id' => $parrain->id])->save();
        }

        Referral::query()->updateOrCreate(
            ['referee_id' => $filleul->id],
            [
                'referrer_id' => $parrain->id,
                'code_used' => 'FAC0001TEST',
                'status' => 'active',
                'rewarded_transactions' => 0,
                'completed_at' => null,
            ],
        );

        // Second parrain sans filleul (pour tester le partage de code).
        $solo = User::query()->updateOrCreate(
            ['phone' => '0700000003'],
            [
                'name' => 'Solo Demo',
                'email' => '0700000003@users.facilya.local',
                'password' => Hash::make('password'),
                'pin' => '1234',
                'email_verified_at' => now(),
                'referral_code' => 'FAC0003SOLO',
                'reward_balance' => 150,
            ],
        );
        $referrals->ensureReferralCode($solo);
    }
}
