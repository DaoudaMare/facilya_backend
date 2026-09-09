<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralSetting;
use App\Models\RewardLedger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function settings(): ReferralSetting
    {
        return ReferralSetting::current();
    }

    public function ensureReferralCode(User $user): User
    {
        if (filled($user->referral_code)) {
            return $user;
        }

        $user->forceFill([
            'referral_code' => $this->generateUniqueCode($user),
        ])->save();

        return $user->fresh() ?? $user;
    }

    public function attachOnSignup(User $referee, ?string $rawCode): ?Referral
    {
        $code = $this->normalizeCode($rawCode);

        if ($code === '') {
            return null;
        }

        if ($referee->referred_by_user_id || Referral::query()->where('referee_id', $referee->id)->exists()) {
            return null;
        }

        $settings = $this->settings();
        if (! $settings->is_active) {
            throw ValidationException::withMessages([
                'referral_code' => 'Le programme de parrainage est temporairement indisponible.',
            ]);
        }

        $referrer = User::query()
            ->whereRaw('UPPER(referral_code) = ?', [$code])
            ->first();

        if (! $referrer) {
            throw ValidationException::withMessages([
                'referral_code' => 'Code de parrainage invalide.',
            ]);
        }

        if ($referrer->id === $referee->id) {
            throw ValidationException::withMessages([
                'referral_code' => 'Vous ne pouvez pas utiliser votre propre code.',
            ]);
        }

        return DB::transaction(function () use ($referrer, $referee, $code): Referral {
            $referee->forceFill([
                'referred_by_user_id' => $referrer->id,
            ])->save();

            return Referral::query()->create([
                'referrer_id' => $referrer->id,
                'referee_id' => $referee->id,
                'code_used' => $code,
                'status' => 'active',
                'rewarded_transactions' => 0,
            ]);
        });
    }

    public function rewardIfEligible(Transaction $transaction): ?ReferralCommission
    {
        if (! $transaction->isPaid() || ! $transaction->isServed()) {
            return null;
        }

        if (! $transaction->user_id) {
            return null;
        }

        $settings = $this->settings();
        if (! $settings->is_active) {
            return null;
        }

        $base = number_format((float) $transaction->amount, 2, '.', '');
        if ((float) $base <= 0) {
            return null;
        }

        $percent = (float) $settings->commission_percent;
        if ($percent <= 0) {
            return null;
        }

        $commissionAmount = $this->computeCommission($base, $percent);
        if ((float) $commissionAmount <= 0) {
            return null;
        }

        $max = max(0, (int) $settings->max_rewarded_transactions);

        return DB::transaction(function () use ($transaction, $base, $percent, $commissionAmount, $max): ?ReferralCommission {
            if (ReferralCommission::query()->where('transaction_id', $transaction->id)->exists()) {
                return null;
            }

            $referral = Referral::query()
                ->where('referee_id', $transaction->user_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $referral || $referral->rewarded_transactions >= $max) {
                return null;
            }

            $sequence = $referral->rewarded_transactions + 1;

            $commission = ReferralCommission::query()->create([
                'referral_id' => $referral->id,
                'transaction_id' => $transaction->id,
                'referrer_id' => $referral->referrer_id,
                'referee_id' => $referral->referee_id,
                'base_amount' => $base,
                'commission_percent' => $percent,
                'commission_amount' => $commissionAmount,
                'sequence' => $sequence,
            ]);

            $referrer = User::query()->lockForUpdate()->findOrFail($referral->referrer_id);
            $newBalance = bcadd((string) $referrer->reward_balance, $commissionAmount, 2);
            $referrer->forceFill(['reward_balance' => $newBalance])->save();

            RewardLedger::query()->create([
                'user_id' => $referrer->id,
                'type' => RewardLedger::TYPE_REFERRAL_COMMISSION,
                'amount' => $commissionAmount,
                'balance_after' => $newBalance,
                'transaction_id' => $transaction->id,
                'referral_commission_id' => $commission->id,
                'note' => sprintf(
                    'Commission parrainage %s/%s (%.4f%% de %s XOF)',
                    $sequence,
                    $max,
                    $percent,
                    $base,
                ),
            ]);

            $referral->forceFill([
                'rewarded_transactions' => $sequence,
                'status' => $sequence >= $max ? 'completed' : 'active',
                'completed_at' => $sequence >= $max ? now() : null,
            ])->save();

            return $commission;
        });
    }

    public function computeCommission(string $baseAmount, float $percent): string
    {
        $raw = bcmul($baseAmount, bcdiv((string) $percent, '100', 8), 4);

        return number_format((float) round((float) $raw), 2, '.', '');
    }

    public function summaryFor(User $user): array
    {
        $user = $this->ensureReferralCode($user);
        $settings = $this->settings();

        $asReferrer = Referral::query()
            ->with(['referee:id,name,phone'])
            ->where('referrer_id', $user->id)
            ->latest()
            ->get();

        $earned = ReferralCommission::query()
            ->where('referrer_id', $user->id)
            ->sum('commission_amount');

        return [
            'referral_code' => $user->referral_code,
            'reward_balance' => number_format((float) $user->reward_balance, 0, '.', ''),
            'total_earned' => number_format((float) $earned, 0, '.', ''),
            'settings' => [
                'is_active' => $settings->is_active,
                'commission_percent' => (float) $settings->commission_percent,
                'max_rewarded_transactions' => (int) $settings->max_rewarded_transactions,
            ],
            'referrals' => $asReferrer->map(fn (Referral $referral): array => [
                'uuid' => $referral->uuid,
                'referee_name' => $referral->referee?->name,
                'referee_phone' => $referral->referee?->phone,
                'status' => $referral->status,
                'rewarded_transactions' => $referral->rewarded_transactions,
                'max_rewarded_transactions' => (int) $settings->max_rewarded_transactions,
                'created_at' => $referral->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    protected function generateUniqueCode(User $user): string
    {
        for ($i = 0; $i < 20; $i++) {
            $suffix = strtoupper(Str::random(4));
            $phone = preg_replace('/\D+/', '', (string) $user->phone) ?? '';
            $tail = strlen($phone) >= 4 ? substr($phone, -4) : $suffix;
            $code = 'FAC'.$tail.$suffix;

            if (! User::query()->where('referral_code', $code)->exists()) {
                return $code;
            }
        }

        return 'FAC'.strtoupper(Str::random(8));
    }

    protected function normalizeCode(?string $raw): string
    {
        return strtoupper(trim((string) $raw));
    }
}
