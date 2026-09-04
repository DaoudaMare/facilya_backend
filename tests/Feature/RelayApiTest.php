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

    public function test_deposit_of_service_amount_does_not_match_total_with_fees(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();
        $orange->update([
            'receive_phone' => '70004444',
            'payment_ussd' => '*144*2*1*{numero}*{montant}#',
        ]);

        $user = User::factory()->create([
            'phone' => '0710000100',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0710000100',
            'recipient_phone' => '0760000100',
        ])->assertCreated();

        $this->assertSame(102.0, (float) $created->json('data.total_amount'));
        $this->assertSame('*144*2*1*70004444*102#', $created->json('data.payment_ussd'));

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $this->assertSame('102.00', $transaction->totalAmount());

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => 100,
            'provider_transaction_id' => 'PP-SHORT-100',
            'sender_phone' => '0710000100',
        ])
            ->assertCreated()
            ->assertJsonPath('data.matched', false);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'pending',
        ]);

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => 102,
            'provider_transaction_id' => 'PP-FULL-102',
            'sender_phone' => '0710000100',
        ])
            ->assertCreated()
            ->assertJsonPath('data.matched', true);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'received',
        ]);
    }

    public function test_deposit_matches_when_sms_omits_local_zero(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();
        $orange->update(['receive_phone' => '70005555']);

        $user = User::factory()->create([
            'phone' => '07684843',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '07684843',
            'recipient_phone' => '0710000200',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => (int) round((float) $transaction->totalAmount()),
            'provider_transaction_id' => 'PP-NOZERO-1',
            'sender_phone' => '7684843',
        ])
            ->assertCreated()
            ->assertJsonPath('data.matched', true);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'received',
            'service_status' => 'processing',
        ]);

        $this->getJson('/api/relay/jobs/next?wait=0')
            ->assertOk()
            ->assertJsonPath('data.recipient_phone', '0710000200');
    }

    public function test_late_sms_still_matches_after_payment_window(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $user = User::factory()->create([
            'phone' => '0710000300',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0710000300',
            'recipient_phone' => '0760000300',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $transaction->forceFill([
            'payment_expires_at' => now()->subMinutes(40),
        ])->save();

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => (int) round((float) $transaction->totalAmount()),
            'provider_transaction_id' => 'PP-LATE-1',
            'sender_phone' => '0710000300',
        ])
            ->assertCreated()
            ->assertJsonPath('data.matched', true);

        $this->assertDatabaseHas('relay_jobs', [
            'transaction_id' => $transaction->id,
            'status' => 'pending',
        ]);
    }

    public function test_unique_pending_transfer_matches_even_if_sms_phone_differs(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $user = User::factory()->create([
            'phone' => '07684843',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '07684843',
            'recipient_phone' => '061279815',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => (int) round((float) $transaction->totalAmount()),
            'provider_transaction_id' => 'PP260904.0915.60763281',
            'sender_phone' => '07018784',
            'sender_name' => 'DAOUDA',
            'raw_body' => 'Vous avez recu 102.0 FCFA du 07018784,DAOUDA. Trans ID: PP260904.0915.60763281.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.matched', true);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'received',
        ]);
    }

    public function test_ambiguous_pending_transfers_are_not_matched_by_amount_alone(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $userA = User::factory()->create(['phone' => '07684843', 'pin' => '1234']);
        $userB = User::factory()->create(['phone' => '07555555', 'pin' => '1234']);

        Sanctum::actingAs($userA);
        $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '07684843',
            'recipient_phone' => '061279815',
        ])->assertCreated();

        Sanctum::actingAs($userB);
        $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '07555555',
            'recipient_phone' => '062222222',
        ])->assertCreated();

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => 102,
            'provider_transaction_id' => 'PP-AMBIGUOUS-1',
            'sender_phone' => '07018784',
        ])
            ->assertCreated()
            ->assertJsonPath('data.matched', false);
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

    public function test_relay_can_cancel_a_pending_transfer(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $user = User::factory()->create([
            'phone' => '0755555555',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0755555555',
            'recipient_phone' => '0766666666',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/transactions/'.$transaction->uuid.'/cancel', [
            'note' => 'Client n’a pas payé',
        ])
            ->assertOk()
            ->assertJsonPath('data.uuid', $transaction->uuid)
            ->assertJsonPath('data.payment_status', 'cancelled');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'cancelled',
            'service_status' => 'cancelled',
        ]);
    }

    public function test_relay_cannot_cancel_a_paid_transfer(): void
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
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0755555555',
            'recipient_phone' => '0766666666',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/transactions/'.$transaction->uuid.'/confirm')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $this->postJson('/api/relay/transactions/'.$transaction->uuid.'/cancel')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['uuid']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'received',
        ]);
    }

    public function test_retry_stores_sms_and_links_matching_unused_deposit(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $user = User::factory()->create([
            'phone' => '0755555555',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0755555555',
            'recipient_phone' => '0766666666',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $total = (int) round((float) $transaction->totalAmount());

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/transactions/'.$transaction->uuid.'/retry', [
            'deposits' => [[
                'network' => 'orange',
                'amount' => $total,
                'provider_transaction_id' => 'PP260904.0915.RETRY1',
                'sender_phone' => '0755555555',
                'sender_name' => 'Client',
                'received_at' => now()->addMinute()->toIso8601String(),
                'raw_body' => 'Vous avez recu '.$total.' FCFA du 0755555555,CLIENT. Trans ID: PP260904.0915.RETRY1',
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.retry_matched', true)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.deposits_stored', 1)
            ->assertJsonPath('data.deposit.provider_transaction_id', 'PP260904.0915.RETRY1');

        $this->assertDatabaseHas('relay_deposits', [
            'provider_transaction_id' => 'PP260904.0915.RETRY1',
            'transaction_id' => $transaction->id,
            'raw_body' => 'Vous avez recu '.$total.' FCFA du 0755555555,CLIENT. Trans ID: PP260904.0915.RETRY1',
        ]);
    }

    public function test_retry_ignores_deposit_received_before_transaction(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $user = User::factory()->create([
            'phone' => '0755555555',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0755555555',
            'recipient_phone' => '0766666666',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $total = (int) round((float) $transaction->totalAmount());

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/transactions/'.$transaction->uuid.'/retry', [
            'deposits' => [[
                'network' => 'orange',
                'amount' => $total,
                'provider_transaction_id' => 'PP-OLD-1',
                'sender_phone' => '0755555555',
                'received_at' => now()->subHour()->toIso8601String(),
                'raw_body' => 'Ancien SMS',
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.retry_matched', false)
            ->assertJsonPath('data.payment_status', 'pending');

        $this->assertDatabaseHas('relay_deposits', [
            'provider_transaction_id' => 'PP-OLD-1',
            'transaction_id' => null,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'pending',
        ]);
    }

    public function test_retry_does_not_reuse_sms_already_linked(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $user = User::factory()->create([
            'phone' => '0755555555',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0755555555',
            'recipient_phone' => '0766666666',
        ])->assertCreated();

        $tx1 = Transaction::query()->findOrFail($first->json('data.id'));
        $total = (int) round((float) $tx1->totalAmount());

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/deposits', [
            'network' => 'orange',
            'amount' => $total,
            'provider_transaction_id' => 'PP-USED-1',
            'sender_phone' => '0755555555',
            'received_at' => now()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.matched', true);

        Sanctum::actingAs($user);

        $second = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0755555555',
            'recipient_phone' => '0767777777',
        ])->assertCreated();

        $tx2 = Transaction::query()->findOrFail($second->json('data.id'));

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/transactions/'.$tx2->uuid.'/retry', [
            'deposits' => [[
                'network' => 'orange',
                'amount' => $total,
                'provider_transaction_id' => 'PP-USED-1',
                'sender_phone' => '0755555555',
                'received_at' => now()->toIso8601String(),
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.retry_matched', false);

        $this->assertDatabaseHas('transactions', [
            'id' => $tx2->id,
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('relay_deposits', [
            'provider_transaction_id' => 'PP-USED-1',
            'transaction_id' => $tx1->id,
        ]);
    }

    public function test_retry_requires_matching_phone(): void
    {
        $orange = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $moov = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $user = User::factory()->create([
            'phone' => '0755555555',
            'pin' => '1234',
        ]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 100,
            'source_network_id' => $orange->id,
            'destination_network_id' => $moov->id,
            'sender_phone' => '0755555555',
            'recipient_phone' => '0766666666',
        ])->assertCreated();

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $total = (int) round((float) $transaction->totalAmount());

        Sanctum::actingAs($this->relayDevice());

        $this->postJson('/api/relay/transactions/'.$transaction->uuid.'/retry', [
            'deposits' => [[
                'network' => 'orange',
                'amount' => $total,
                'provider_transaction_id' => 'PP-OTHER-PHONE',
                'sender_phone' => '07018784',
                'received_at' => now()->addMinute()->toIso8601String(),
                'raw_body' => 'Vous avez recu '.$total.' FCFA du 07018784,DAOUDA.',
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.retry_matched', false)
            ->assertJsonPath('data.deposits_stored', 1);

        $this->assertDatabaseHas('relay_deposits', [
            'provider_transaction_id' => 'PP-OTHER-PHONE',
            'transaction_id' => null,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'pending',
        ]);
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
