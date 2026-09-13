<?php

namespace Tests\Feature;

use App\Data\TrustedPaymentStatusEnum;
use App\Data\TrustedPayoutStatusEnum;
use App\Models\RelayJob;
use App\Models\TransferNetwork;
use App\Models\TrustedPayment;
use App\Models\User;
use App\Services\TransactionService;
use App\Services\TrustedPaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrustedPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_create_pay_expedition_collected_triggers_payout_then_deliver(): void
    {
        $buyer = User::factory()->create([
            'phone' => '0711111155',
            'pin' => '1234',
            'name' => 'Acheteur Test',
        ]);
        $merchant = User::factory()->create([
            'phone' => '0722222255',
            'pin' => '1234',
            'name' => 'Commercant Test',
        ]);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $network->update(['receive_phone' => '70000001']);

        $quote = $this->getJson(
            '/api/v1/quotes/trusted-payments?merchandise_amount=10000&payment_network_id='.$network->id
        )->assertOk();

        $this->assertEquals(10000, $quote->json('data.merchandise_amount'));
        $this->assertEquals(200, $quote->json('data.platform_fee'));
        $this->assertEquals(10200, $quote->json('data.total_amount'));
        $this->assertEquals(10000, $quote->json('data.payout_amount'));

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/trusted-payments/merchant-lookup?phone=0722222255')
            ->assertOk()
            ->assertJsonPath('data.id', $merchant->id)
            ->assertJsonPath('data.name', 'Commercant Test');

        $created = $this->postJson('/api/v1/trusted-payments', [
            'pin' => '1234',
            'merchandise_amount' => 10000,
            'payment_network_id' => $network->id,
            'merchant_phone' => '0722222255',
            'buyer_deposit_phone' => '0711111155',
            'merchant_payout_phone' => '0722222255',
            'product_description' => 'iPhone reconditionné',
            'delivery_address' => 'Ouaga secteur 15',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.product_description', 'iPhone reconditionné')
            ->assertJsonPath('data.pricing.payout_amount', 10000);

        $uuid = $created->json('data.uuid');
        $payment = TrustedPayment::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame(TrustedPaymentStatusEnum::PendingPayment, $payment->status);

        app(TransactionService::class)->markPaymentReceived($payment->transaction);
        $payment->refresh();
        $this->assertSame(TrustedPaymentStatusEnum::FundsHeld, $payment->status);
        $this->assertNotNull($payment->funds_held_at);

        Sanctum::actingAs($merchant);
        $this->postJson('/api/v1/trusted-payments/'.$uuid.'/expedition', [
            'pickup_address' => 'Marché de Sankaryaré',
            'pickup_district' => 'Ouaga',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'expedition_requested');

        $service = app(TrustedPaymentService::class);
        $payment->refresh();
        $service->transition($payment, TrustedPaymentStatusEnum::CourierEnRoute, 'Coursier envoyé');
        $service->transition($payment->fresh(), TrustedPaymentStatusEnum::Collected, 'Colis récupéré chez le commerçant');

        $payment->refresh();
        $this->assertSame(TrustedPaymentStatusEnum::Collected, $payment->status);
        $this->assertSame(TrustedPayoutStatusEnum::Processing, $payment->payout_status);

        $job = RelayJob::query()
            ->where('transaction_id', $payment->transaction_id)
            ->where('type', 'transfer')
            ->latest('id')
            ->first();

        $this->assertNotNull($job);
        $this->assertSame('pending', $job->status);
        $this->assertSame('0722222255', $job->recipient_phone);
        $this->assertEquals(10000, (float) $job->amount);

        $service->handlePayoutJobResult($job, true);
        $payment->refresh();
        $this->assertSame(TrustedPayoutStatusEnum::Sent, $payment->payout_status);
        $this->assertNotNull($payment->payout_sent_at);

        $service->transition($payment->fresh(), TrustedPaymentStatusEnum::InTransit, 'En route');
        $service->transition($payment->fresh(), TrustedPaymentStatusEnum::Arrived, 'Arrivé');
        $service->transition($payment->fresh(), TrustedPaymentStatusEnum::Delivered, 'Remis à l’acheteur');

        $payment->refresh();
        $this->assertSame(TrustedPaymentStatusEnum::Delivered, $payment->status);
        $this->assertTrue($payment->transaction->fresh()->isServed());

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/trusted-payments/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.payout_status', 'sent');

        Sanctum::actingAs($merchant);
        $this->getJson('/api/v1/trusted-payments?role=merchant')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
