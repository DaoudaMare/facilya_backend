<?php

namespace Tests\Feature;

use App\Data\TrustedPaymentStatusEnum;
use App\Data\TrustedPayoutStatusEnum;
use App\Filament\Livewire\RevealEscrowUnlockCode;
use App\Models\Role;
use App\Models\TransferNetwork;
use App\Models\TrustedPayment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EscrowUnlockCodePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_only_admin_has_unlock_code_permission(): void
    {
        $admin = User::query()->where('email', 'admin@facilya.local')->firstOrFail();
        $ops = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'ops')->value('id'),
        ]);

        $this->assertTrue(RevealEscrowUnlockCode::canViewUnlockCodes($admin));
        $this->assertFalse(RevealEscrowUnlockCode::canViewUnlockCodes($ops));
    }

    public function test_unlock_code_stays_masked_until_revealed(): void
    {
        $admin = User::query()->where('email', 'admin@facilya.local')->firstOrFail();
        $payment = $this->makeEscrow();

        Livewire::actingAs($admin)
            ->test(RevealEscrowUnlockCode::class, ['trustedPaymentId' => $payment->id])
            ->assertSee($payment->public_id, false)
            ->assertSee('••••••', false)
            ->assertDontSee($payment->validation_code, false)
            ->set('revealed', true)
            ->assertSee($payment->validation_code, false);
    }

    public function test_ops_cannot_see_reveal_button(): void
    {
        $ops = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'ops')->value('id'),
        ]);
        $payment = $this->makeEscrow();

        Livewire::actingAs($ops)
            ->test(RevealEscrowUnlockCode::class, ['trustedPaymentId' => $payment->id])
            ->assertSee('Permission', false)
            ->assertSee('••••••', false)
            ->assertDontSee($payment->validation_code, false);
    }

    protected function makeEscrow(): TrustedPayment
    {
        $buyer = User::factory()->create(['phone' => '0711111101']);
        $merchant = User::factory()->create(['phone' => '0722222202']);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();

        return TrustedPayment::query()->create([
            'reference' => 'CF-TEST-UNLOCK',
            'public_id' => '654321',
            'validation_code' => '112233',
            'buyer_user_id' => $buyer->id,
            'merchant_user_id' => $merchant->id,
            'status' => TrustedPaymentStatusEnum::FundsHeld,
            'payout_status' => TrustedPayoutStatusEnum::Pending,
            'merchandise_amount' => 5000,
            'network_fee' => 0,
            'platform_fee' => 0,
            'total_amount' => 5000,
            'payout_amount' => 5000,
            'buyer_deposit_phone' => '0711111101',
            'merchant_payout_phone' => '0722222202',
            'payment_network_id' => $network->id,
            'product_description' => 'Test unlock',
        ]);
    }
}
