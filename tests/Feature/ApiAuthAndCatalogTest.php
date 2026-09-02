<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransferNetwork;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthAndCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_sms_otp_login_pin_and_catalog(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/otp/request', [
            'channel' => 'sms',
            'phone' => '0700000000',
        ])
            ->assertOk()
            ->assertJsonPath('data.channel', 'sms')
            ->assertJsonMissingPath('data.debug_otp');

        Notification::assertSentOnDemand(OtpCodeNotification::class);

        $otp = Cache::get('auth.otp.sms.0700000000');
        $this->assertNotEmpty($otp);

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'channel' => 'sms',
            'phone' => '07 00 00 00 00',
            'code' => $otp,
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.needs_pin', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $token = $verify->json('data.token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/pin', ['pin' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.user.needs_pin', false);

        $this->getJson('/api/v1/networks')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->getJson('/api/v1/travel/trips?departure=Ouagadougou&arrival=Bobo-Dioulasso')
            ->assertOk();

        $source = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $destination = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();

        $this->getJson('/api/v1/quotes/transfers?amount=5000&source_network_id='.$source->id.'&destination_network_id='.$destination->id)
            ->assertOk()
            ->assertJsonPath('data.amount', '5000.00');
    }

    public function test_email_otp_creates_account(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/otp/request', [
            'channel' => 'email',
            'email' => 'client@example.com',
        ])->assertOk()->assertJsonPath('data.channel', 'email');

        Notification::assertSentOnDemand(OtpCodeNotification::class);

        $otp = Cache::get('auth.otp.email.client@example.com');

        $this->postJson('/api/v1/auth/otp/verify', [
            'channel' => 'email',
            'email' => 'client@example.com',
            'code' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'client@example.com');
    }

    public function test_transfer_requires_auth_and_pin(): void
    {
        $this->postJson('/api/v1/transfers', [])->assertUnauthorized();

        $user = User::factory()->create([
            'phone' => '0711111111',
            'pin' => '1234',
        ]);

        $source = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $destination = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();
        $source->update(['receive_phone' => '70000001']);

        Sanctum::actingAs($user);

        $source->update(['payment_ussd' => '*144*1*1*{numero}*{montant}#']);

        $created = $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 5000,
            'source_network_id' => $source->id,
            'destination_network_id' => $destination->id,
            'sender_phone' => '0711111111',
            'recipient_phone' => '0722222222',
            'recipient_name' => 'Adama',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'network_transfer')
            ->assertJsonPath('data.ui_status', 'pending')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_receive_phone', '70000001');

        $transaction = Transaction::query()->findOrFail($created->json('data.id'));
        $total = (int) round((float) $transaction->totalAmount());

        $created->assertJsonPath('data.payment_ussd', '*144*1*1*70000001*'.$total.'#');
    }

    public function test_network_capabilities_are_enforced(): void
    {
        $user = User::factory()->create([
            'phone' => '0711111112',
            'pin' => '1234',
        ]);

        $sendOnly = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $receiveOnly = TransferNetwork::query()->where('code', 'MOOV')->firstOrFail();
        $sendOnly->update(['can_send' => true, 'can_receive' => false]);
        $receiveOnly->update(['can_send' => false, 'can_receive' => true]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/quotes/transfers?amount=5000&source_network_id='.$receiveOnly->id.'&destination_network_id='.$sendOnly->id)
            ->assertUnprocessable();

        $this->postJson('/api/v1/transfers', [
            'pin' => '1234',
            'amount' => 5000,
            'source_network_id' => $receiveOnly->id,
            'destination_network_id' => $sendOnly->id,
            'sender_phone' => '0711111112',
            'recipient_phone' => '0722222222',
        ])->assertUnprocessable();
    }

    public function test_invalid_otp_is_rejected(): void
    {
        Notification::fake();
        Cache::flush();

        $this->postJson('/api/v1/auth/otp/request', [
            'channel' => 'sms',
            'phone' => '0700000001',
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'channel' => 'sms',
            'phone' => '0700000001',
            'code' => '000000',
        ])->assertUnprocessable();
    }

    public function test_whatsapp_otp_is_sent_via_zapwize(): void
    {
        Http::fake([
            'api.zapwize.com/*' => Http::response(['success' => true, 'value' => ['queued' => true]], 200),
        ]);

        $this->postJson('/api/v1/auth/otp/request', [
            'channel' => 'whatsapp',
            'phone' => '70111111',
        ])
            ->assertOk()
            ->assertJsonPath('data.channel', 'whatsapp')
            ->assertJsonMissingPath('data.debug_otp');

        $otp = Cache::get('auth.otp.whatsapp.70111111');
        $this->assertNotEmpty($otp);

        Http::assertSent(function ($request) use ($otp) {
            $payload = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://api.zapwize.com/v1/whatsapp/message'
                && $request->hasHeader('Authorization', 'Bearer testing')
                && str_starts_with((string) $request->header('Content-Type')[0], 'application/json')
                && ($payload['chatid'] ?? null) === '22670111111'
                && str_contains((string) data_get($payload, 'content.text'), (string) $otp);
        });

        $this->postJson('/api/v1/auth/otp/verify', [
            'channel' => 'whatsapp',
            'phone' => '70 11 11 11',
            'code' => $otp,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.phone', '70111111');
    }
}
