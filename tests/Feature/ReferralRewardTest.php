<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralSetting;
use App\Models\Transaction;
use App\Models\TransferNetwork;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\TransactionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralRewardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_settings_default_to_half_percent_and_ten_transactions(): void
    {
        $settings = ReferralSetting::current();

        $this->assertTrue($settings->is_active);
        $this->assertSame(0.5, (float) $settings->commission_percent);
        $this->assertSame(10, (int) $settings->max_rewarded_transactions);
    }

    public function test_commission_is_half_percent_of_amount_rounded(): void
    {
        $service = app(ReferralService::class);

        $this->assertSame('50.00', $service->computeCommission('10000.00', 0.5));
        $this->assertSame('1.00', $service->computeCommission('100.00', 0.5));
    }

    public function test_new_user_can_be_attached_with_referral_code(): void
    {
        $parrain = User::factory()->create([
            'phone' => '0711111111',
            'referral_code' => 'FACPARRAIN1',
            'pin' => '1234',
        ]);

        $filleul = User::factory()->create([
            'phone' => '0722222222',
            'pin' => '1234',
        ]);

        $referral = app(ReferralService::class)->attachOnSignup($filleul, 'facparrain1');

        $this->assertNotNull($referral);
        $this->assertSame($parrain->id, $filleul->fresh()->referred_by_user_id);
        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $parrain->id,
            'referee_id' => $filleul->id,
            'code_used' => 'FACPARRAIN1',
            'status' => 'active',
        ]);
    }

    public function test_parrain_earns_commission_on_delivered_transfer(): void
    {
        [$parrain, $filleul] = $this->linkedUsers();

        $transaction = $this->createDeliveredTransfer($filleul, 10000);

        $this->assertDatabaseHas('referral_commissions', [
            'referrer_id' => $parrain->id,
            'referee_id' => $filleul->id,
            'transaction_id' => $transaction->id,
            'commission_amount' => '50.00',
            'sequence' => 1,
        ]);

        $this->assertSame('50.00', (string) $parrain->fresh()->reward_balance);
        $this->assertSame(1, (int) Referral::query()->where('referee_id', $filleul->id)->value('rewarded_transactions'));
        $this->assertSame(0.0, (float) $filleul->fresh()->reward_balance);
    }

    public function test_commission_stops_after_configured_max_transactions(): void
    {
        ReferralSetting::current()->update([
            'max_rewarded_transactions' => 2,
            'commission_percent' => 0.5,
        ]);

        [$parrain, $filleul] = $this->linkedUsers();

        $this->createDeliveredTransfer($filleul, 10000);
        $this->createDeliveredTransfer($filleul, 10000);
        $this->createDeliveredTransfer($filleul, 10000);

        $this->assertSame(2, ReferralCommission::query()->where('referrer_id', $parrain->id)->count());
        $this->assertSame('100.00', (string) $parrain->fresh()->reward_balance);
        $this->assertSame('completed', Referral::query()->where('referee_id', $filleul->id)->value('status'));
    }

    public function test_referral_summary_endpoint(): void
    {
        [$parrain] = $this->linkedUsers();
        Sanctum::actingAs($parrain);

        $this->getJson('/api/v1/me/referral')
            ->assertOk()
            ->assertJsonPath('data.referral_code', $parrain->referral_code)
            ->assertJsonPath('data.settings.commission_percent', 0.5)
            ->assertJsonPath('data.settings.max_rewarded_transactions', 10)
            ->assertJsonCount(1, 'data.referrals');
    }

    /**
     * @return array{0: User, 1: User}
     */
    protected function linkedUsers(): array
    {
        $parrain = User::factory()->create([
            'phone' => '0710000001',
            'referral_code' => 'FACTEST0001',
            'pin' => '1234',
            'reward_balance' => 0,
        ]);

        $filleul = User::factory()->create([
            'phone' => '0710000002',
            'pin' => '1234',
            'reward_balance' => 0,
        ]);

        app(ReferralService::class)->attachOnSignup($filleul, 'FACTEST0001');

        return [$parrain->fresh(), $filleul->fresh()];
    }

    protected function createDeliveredTransfer(User $user, int $amount): Transaction
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => $amount,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => $user->phone,
            'recipient_phone' => '0766666666',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $service = app(TransactionService::class);
        $service->markPaymentReceived($transaction, 'TEST-PAY');
        $service->markServiceDelivered($transaction->fresh(), 'TEST-SVC');

        return $transaction->fresh();
    }
}
