<?php

namespace Tests\Feature;

use App\Models\RelayDevice;
use App\Models\Transaction;
use App\Models\TransferNetwork;
use App\Models\TravelCompanyTrip;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RelayApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_deposit_matches_transfer_and_creates_fulfillment_job(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();
        $orange->update(['receive_phone' => '70001111']);

        $user = User::factory()->create([
            'phone' => '0712345678',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 5000,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0712345678',
            'recipient_phone' => '0765432100',
            'recipient_name' => 'Awa',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $total = (int) round((float) $transaction->totalAmount());

        $device = $this->relayDevice();
        Sanctum::actingAs($device);

        $this->postJson('/api/relay/heartbeat', [
            'fulfill_networks' => ['orange', 'moov'],
        ])
            ->assertOk()
            ->assertJsonPath('data.uuid', $device->uuid);

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => $total,
            'provider_transaction_id' => 'PP-ORANGE-1',
            'sender_phone' => '2260712345678',
            'sender_name' => 'Client',
            'received_at' => now()->toIso8601String(),
            'raw_body' => 'Vous avez recu '.$total.' F de 0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.matched', true);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'received',
            'service_status' => 'processing',
        ]);

        $next = $this->getJson('/api/relay/jobs/next?wait=0')->assertOk();

        $this->assertSame('moov', $next->json('data.network'));
        $this->assertSame('0765432100', $next->json('data.recipient_phone'));

        $this->postJson('/api/relay/jobs/'.$next->json('data.uuid').'/result', [
            'status' => 'succeeded',
            'provider_reference' => 'USSD-99',
        ])->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'received',
            'service_status' => 'delivered',
        ]);
    }

    public function test_deposit_matches_ticket_without_ussd_job(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $orange->update(['receive_phone' => '70002222']);

        $user = User::factory()->create([
            'phone' => '0700112233',
            'pin' => '1234',
        ]);

        $trip = TravelCompanyTrip::query()->firstOrFail();

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/tickets', [
            'pin' => '1234',
            'travel_company_trip_id' => $trip->id,
            'travel_date' => now()->toDateString(),
            'passenger_name' => 'Fatou',
            'passenger_phone' => '0700112233',
            'passenger_count' => 1,
            'payment_network_id' => $orange->id,
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $this->assertSame('pending', $created->json('data.ui_status'));

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => (int) round((float) $transaction->totalAmount()),
            'provider_transaction_id' => 'PP-TICKET-1',
            'sender_phone' => '0700112233',
        ])
            ->assertCreated()
            ->assertJsonPath('data.matched', true);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'received',
            'service_status' => 'delivered',
        ]);

        $this->getJson('/api/relay/jobs/next?wait=0')->assertNoContent();
    }

    public function test_manual_confirm_marks_payment_and_creates_job(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();
        $orange->update(['receive_phone' => '70003333']);

        $user = User::factory()->create([
            'phone' => '0755555555',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 5000,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0755555555',
            'recipient_phone' => '0766666666',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/transactions/'.$transaction->uuid.'/confirm', [
            'note' => 'Vu sur le compte Orange',
        ])
            ->assertOk()
            ->assertJsonPath('data.uuid', $transaction->uuid)
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'received',
            'service_status' => 'processing',
        ]);

        $this->getJson('/api/relay/jobs/next?wait=0')
            ->assertOk()
            ->assertJsonPath('data.recipient_phone', '0766666666');
    }

    public function test_user_token_cannot_access_relay(): void
    {
        $user = User::factory()->create(['pin' => '1234']);
        Sanctum::actingAs($user);

        $this->postJson('/api/relay/heartbeat')->assertForbidden();
    }

    protected function relayDevice(): RelayDevice
    {
        return RelayDevice::query()->create([
            'name' => 'Samsung test',
            'network' => 'orange',
            'phone_number' => '70001111',
            'fulfill_networks' => ['orange', 'moov'],
        ]);
    }
}
