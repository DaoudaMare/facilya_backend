<?php

namespace Tests\Feature;

use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelFeeModeEnum;
use App\Data\ParcelScopeEnum;
use App\Data\ParcelStatusEnum;
use App\Models\ParcelPricingSetting;
use App\Models\ParcelShipment;
use App\Models\TravelCompanyRoute;
use App\Models\TransferNetwork;
use App\Models\User;
use App\Services\ParcelShipmentService;
use App\Services\TransactionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParcelShipmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
        \Illuminate\Support\Facades\Notification::fake();
        Http::fake([
            'https://api.mapbox.com/directions/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 3000,
                    'duration' => 600,
                    'weight' => 1,
                    'legs' => [],
                ]],
            ], 200),
            'https://api.mapbox.com/geocoding/*' => Http::response([
                'features' => [[
                    'center' => [-1.5197, 12.3714],
                ]],
            ], 200),
        ]);

        ParcelPricingSetting::current()->update([
            'agency_mode' => ParcelFeeModeEnum::Fixed,
            'agency_value' => 1000,
            'margin_mode' => ParcelFeeModeEnum::Fixed,
            'margin_value' => 500,
            'pickup_mode' => ParcelFeeModeEnum::Fixed,
            'pickup_value' => 200,
            'pickup_per_km' => 100,
            'delivery_mode' => ParcelFeeModeEnum::Fixed,
            'delivery_value' => 200,
            'delivery_per_km' => 100,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function identityPayload(): array
    {
        return [
            'sender_name' => 'Amadou Traore',
            'sender_cnib_number' => 'B12345678',
            'sender_cnib_photo' => UploadedFile::fake()->image('sender-cnib.jpg'),
            'recipient_name' => 'Awa Ouédraogo',
            'recipient_cnib_number' => 'B87654321',
            'recipient_cnib_photo' => UploadedFile::fake()->image('recipient-cnib.jpg'),
            'estimated_weight_kg' => 2,
            'parcel_photo' => UploadedFile::fake()->image('parcel.jpg'),
        ];
    }

    protected function pickupMapsUrl(): string
    {
        return 'https://www.google.com/maps/place/Ouaga/@12.3681,-1.5275,17z';
    }

    public function test_quote_and_place_parcel_station_pickup(): void
    {
        $user = User::factory()->create([
            'phone' => '0711111133',
            'pin' => '1234',
        ]);
        $route = TravelCompanyRoute::query()->where('is_active', true)->firstOrFail();
        $route->update(['accepts_parcels' => true]);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $network->update(['receive_phone' => '70000001']);

        $this->getJson(
            '/api/v1/quotes/parcels?travel_company_route_id='.$route->id.'&delivery_mode=station_pickup'
        )->assertUnprocessable();

        $quote = $this->getJson(
            '/api/v1/quotes/parcels?travel_company_route_id='.$route->id
            .'&delivery_mode=station_pickup'
            .'&payment_network_id='.$network->id
            .'&pickup_address='.urlencode($this->pickupMapsUrl())
            .'&declared_value=50000'
        )->assertOk();

        $this->assertEquals(1000, $quote->json('data.agency_fee'));
        $this->assertEquals(500, $quote->json('data.margin_amount'));
        $this->assertEquals(500, $quote->json('data.pickup.total'));
        $this->assertEquals(3, $quote->json('data.pickup.distance_km'));
        $this->assertEquals(0, $quote->json('data.delivery.total'));

        Sanctum::actingAs($user);

        $created = $this->post('/api/v1/parcels', [
            'pin' => '1234',
            'travel_company_route_id' => $route->id,
            'delivery_mode' => 'station_pickup',
            'payment_network_id' => $network->id,
            'sender_phone' => '0711111133',
            'pickup_address' => $this->pickupMapsUrl(),
            'pickup_district' => 'Ouaga',
            'recipient_phone' => '0722222233',
            'parcel_description' => 'Carton documents',
            'declared_value' => 50000,
            'estimated_weight_kg' => 2,
            'parcel_photo' => UploadedFile::fake()->image('parcel.jpg'),
            ...$this->identityPayload(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.delivery_mode', 'station_pickup')
            ->assertJsonPath('data.sender.cnib_number', 'B12345678')
            ->assertJsonPath('data.recipient.cnib_number', 'B87654321');

        $uuid = $created->json('data.uuid');
        $this->assertNotEmpty($uuid);
        $this->assertNotEmpty($created->json('data.codes.station_pickup_code'));
        $this->assertNull($created->json('data.codes.delivery_code'));
        $this->assertNotEmpty($created->json('data.sender.cnib_photo_url'));

        $this->getJson('/api/v1/parcels/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->getJson('/api/v1/parcels')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_parcel_addresses_must_be_google_maps_links(): void
    {
        $user = User::factory()->create([
            'phone' => '0711111155',
            'pin' => '1234',
        ]);
        $route = TravelCompanyRoute::query()->where('is_active', true)->firstOrFail();
        $route->update(['accepts_parcels' => true]);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $network->update(['receive_phone' => '70000001']);

        Sanctum::actingAs($user);

        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/parcels', [
                'pin' => '1234',
                'travel_company_route_id' => $route->id,
                'delivery_mode' => 'station_pickup',
                'payment_network_id' => $network->id,
                'sender_phone' => '0711111155',
                'pickup_address' => 'Ouaga, secteur 15',
                'recipient_phone' => '0722222255',
            'parcel_description' => 'Carton documents',
            'declared_value' => 50000,
            'estimated_weight_kg' => 2,
            'parcel_photo' => UploadedFile::fake()->image('parcel.jpg'),
            ...$this->identityPayload(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_address']);
    }

    public function test_door_delivery_tracking_statuses_and_cnib(): void
    {
        $user = User::factory()->create([
            'phone' => '0711111144',
            'pin' => '1234',
        ]);
        $route = TravelCompanyRoute::query()->where('is_active', true)->firstOrFail();
        $route->update(['accepts_parcels' => true]);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $network->update(['receive_phone' => '70000001']);

        Sanctum::actingAs($user);

        $created = $this->post('/api/v1/parcels', [
            'pin' => '1234',
            'travel_company_route_id' => $route->id,
            'delivery_mode' => 'door_delivery',
            'payment_network_id' => $network->id,
            'sender_phone' => '0711111144',
            'pickup_address' => $this->pickupMapsUrl(),
            'recipient_phone' => '0722222244',
            'recipient_address' => 'https://www.google.com/maps/place/Bobo',
            'delivery_distance_km' => 4,
            'parcel_description' => 'Colis fragile',
            'declared_value' => 25000,
            'estimated_weight_kg' => 10,
            'parcel_photo' => UploadedFile::fake()->image('parcel-door.jpg'),
            ...$this->identityPayload(),
        ])->assertCreated();

        $shipment = ParcelShipment::query()->where('uuid', $created->json('data.uuid'))->firstOrFail();
        $this->assertSame(ParcelDeliveryModeEnum::DoorDelivery, $shipment->delivery_mode);
        $this->assertNotEmpty($shipment->sender_cnib_photo);
        $this->assertNotEmpty($shipment->recipient_cnib_photo);

        app(TransactionService::class)->markPaymentReceived($shipment->transaction);
        app(ParcelShipmentService::class)->confirmAfterPayment($shipment->transaction->fresh());
        $shipment->refresh();
        $this->assertSame(ParcelStatusEnum::Confirmed, $shipment->status);

        $parcels = app(ParcelShipmentService::class);
        $parcels->transition($shipment, ParcelStatusEnum::CourierEnRoute, 'Coursier envoyé');
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::Collected, 'Collecté chez l’expéditeur');
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::Shipped, 'Expédié');
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::ArrivedStation, 'Arrivé gare');
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::OutForDelivery, 'En livraison');
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::Delivered, 'Livré à domicile');

        $shipment->refresh();
        $this->assertSame(ParcelStatusEnum::Delivered, $shipment->status);
        $this->assertSame('Remis au destinataire', $shipment->status->trackingLabel());
        $this->assertTrue($shipment->transaction->fresh()->isServed());
    }

    public function test_local_delivery_skips_bus_and_uses_out_for_delivery(): void
    {
        $user = User::factory()->create([
            'phone' => '0711111166',
            'pin' => '1234',
        ]);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $network->update(['receive_phone' => '70000001']);

        $quote = $this->getJson(
            '/api/v1/quotes/parcels?scope=local'
            .'&origin_city=Ouagadougou'
            .'&delivery_mode=door_delivery'
            .'&payment_network_id='.$network->id
            .'&pickup_address='.urlencode($this->pickupMapsUrl())
            .'&delivery_distance_km=4'
            .'&declared_value=20000'
        )->assertOk();

        $this->assertEquals(0, $quote->json('data.agency_fee'));
        $this->assertEquals(500, $quote->json('data.margin_amount'));
        $this->assertGreaterThan(0, $quote->json('data.delivery.total'));

        Sanctum::actingAs($user);

        $created = $this->post('/api/v1/parcels', [
            'pin' => '1234',
            'scope' => 'local',
            'origin_city' => 'Ouagadougou',
            'delivery_mode' => 'door_delivery',
            'payment_network_id' => $network->id,
            'sender_phone' => '0711111166',
            'pickup_address' => $this->pickupMapsUrl(),
            'recipient_phone' => '0722222266',
            'recipient_address' => 'https://maps.app.goo.gl/localDrop',
            'delivery_distance_km' => 4,
            'parcel_description' => 'Sac courses',
            'declared_value' => 20000,
            ...$this->identityPayload(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.scope', 'local')
            ->assertJsonPath('data.delivery_mode', 'door_delivery')
            ->assertJsonPath('data.departure', 'Ouagadougou');

        $steps = collect($created->json('data.tracking_steps'))->pluck('key')->all();
        $this->assertSame([
            'confirmed',
            'courier_en_route',
            'collected',
            'out_for_delivery',
            'delivered',
        ], $steps);

        $shipment = ParcelShipment::query()->where('uuid', $created->json('data.uuid'))->firstOrFail();
        $this->assertSame(ParcelScopeEnum::Local, $shipment->scope);
        $this->assertNull($shipment->travel_company_route_id);
        $this->assertNotEmpty($shipment->parcel_photo);

        app(TransactionService::class)->markPaymentReceived($shipment->transaction);
        $parcels = app(ParcelShipmentService::class);
        $parcels->confirmAfterPayment($shipment->transaction->fresh());
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::CourierEnRoute);
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::Collected);
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::OutForDelivery);
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::Delivered);

        $this->assertSame(ParcelStatusEnum::Delivered, $shipment->fresh()->status);
    }

    public function test_parcel_reuses_profile_cnib_when_sender_photo_omitted(): void
    {
        $profilePhoto = UploadedFile::fake()->image('profile-cnib.jpg');
        $stored = $profilePhoto->store('users/cnib', 'public');

        $user = User::factory()->create([
            'phone' => '0711111177',
            'pin' => '1234',
            'cnib_number' => 'B11223344',
            'cnib_photo' => $stored,
        ]);
        $route = TravelCompanyRoute::query()->where('is_active', true)->firstOrFail();
        $route->update(['accepts_parcels' => true]);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $network->update(['receive_phone' => '70000001']);

        Sanctum::actingAs($user);

        $payload = $this->identityPayload();
        unset($payload['sender_cnib_photo'], $payload['sender_cnib_number']);

        $created = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/parcels', [
                'pin' => '1234',
                'travel_company_route_id' => $route->id,
                'delivery_mode' => 'station_pickup',
                'payment_network_id' => $network->id,
                'sender_phone' => '0711111177',
                'pickup_address' => $this->pickupMapsUrl(),
                'recipient_phone' => '0722222277',
                'parcel_description' => 'Carton documents',
                'declared_value' => 50000,
                ...$payload,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender.cnib_number', 'B11223344');

        $this->assertNotEmpty($created->json('data.sender.cnib_photo_url'));
        $shipment = ParcelShipment::query()->where('uuid', $created->json('data.uuid'))->firstOrFail();
        $this->assertNotSame($stored, $shipment->sender_cnib_photo);
        $this->assertTrue(Storage::disk('public')->exists($shipment->sender_cnib_photo));
    }

    public function test_link_escrow_to_expedition_unlocks_on_pickup_code(): void
    {
        $buyer = User::factory()->create([
            'phone' => '0711111188',
            'pin' => '1234',
            'name' => 'Client Escrow',
            'cnib_number' => 'B11112222',
        ]);
        Storage::disk('public')->put('users/cnib/buyer.jpg', 'fake');
        $buyer->update(['cnib_photo' => 'users/cnib/buyer.jpg']);

        $merchant = User::factory()->create(['phone' => '0722222288', 'pin' => '1234', 'name' => 'Boutique']);
        $route = TravelCompanyRoute::query()->where('is_active', true)->firstOrFail();
        $route->update(['accepts_parcels' => true]);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $network->update(['receive_phone' => '70000001']);

        Sanctum::actingAs($buyer);
        $createdEscrow = $this->postJson('/api/v1/trusted-payments', [
            'pin' => '1234',
            'merchandise_amount' => 15000,
            'payment_network_id' => $network->id,
            'merchant_phone' => '0722222288',
            'product_description' => 'Téléphone',
        ])->assertCreated();

        $escrow = \App\Models\TrustedPayment::query()->where('uuid', $createdEscrow->json('data.uuid'))->firstOrFail();
        app(TransactionService::class)->markPaymentReceived($escrow->transaction);
        $escrow->refresh();

        Sanctum::actingAs($merchant);
        $this->getJson('/api/v1/trusted-payments/lookup?public_id='.$escrow->public_id)
            ->assertOk()
            ->assertJsonPath('data.public_id', $escrow->public_id)
            ->assertJsonPath('data.buyer.name', 'Client Escrow')
            ->assertJsonMissingPath('data.validation_code');

        $created = $this->post('/api/v1/parcels', [
            'pin' => '1234',
            'travel_company_route_id' => $route->id,
            'delivery_mode' => 'station_pickup',
            'trusted_payment_public_id' => $escrow->public_id,
            'sender_phone' => '0722222288',
            'pickup_address' => $this->pickupMapsUrl(),
            'recipient_name' => 'Client Escrow',
            'recipient_phone' => '0711111188',
            'recipient_cnib_number' => 'B11112222',
            'parcel_description' => 'Colis lié escrow',
            'declared_value' => 15000,
            'sender_name' => 'Boutique',
            'sender_cnib_number' => 'B12345678',
            'sender_cnib_photo' => UploadedFile::fake()->image('sender-cnib.jpg'),
            'estimated_weight_kg' => 2,
            'parcel_photo' => UploadedFile::fake()->image('parcel.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.trusted_payment.public_id', $escrow->public_id);

        $shipment = ParcelShipment::query()->where('uuid', $created->json('data.uuid'))->firstOrFail();
        $this->assertTrue($shipment->transaction->fresh()->isPaid());
        $escrow->refresh();
        $this->assertSame(\App\Data\TrustedPaymentStatusEnum::ExpeditionRequested, $escrow->status);
        $this->assertSame(\App\Data\TrustedPayoutStatusEnum::Pending, $escrow->payout_status);

        $parcels = app(ParcelShipmentService::class);
        $parcels->transition($shipment, ParcelStatusEnum::CourierEnRoute, 'Coursier envoyé');

        try {
            $parcels->transition($shipment->fresh(), ParcelStatusEnum::Collected, 'Sans code');
            $this->fail('Expected validation_code validation');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('validation_code', $e->errors());
        }

        $parcels->transition(
            $shipment->fresh(),
            ParcelStatusEnum::Collected,
            'Collecté',
            'admin',
            null,
            $escrow->validation_code,
        );

        $escrow->refresh();
        $this->assertSame(\App\Data\TrustedPaymentStatusEnum::Collected, $escrow->status);
        $this->assertSame(\App\Data\TrustedPayoutStatusEnum::Processing, $escrow->payout_status);
    }

    public function test_link_escrow_to_delivery_pays_merchant_immediately(): void
    {
        $buyer = User::factory()->create([
            'phone' => '0711111190',
            'pin' => '1234',
            'name' => 'Client Livraison',
            'cnib_number' => 'B33334444',
        ]);
        Storage::disk('public')->put('users/cnib/buyer2.jpg', 'fake');
        $buyer->update(['cnib_photo' => 'users/cnib/buyer2.jpg']);

        $merchant = User::factory()->create(['phone' => '0722222290', 'pin' => '1234', 'name' => 'Marchand']);
        $route = TravelCompanyRoute::query()->where('is_active', true)->firstOrFail();
        $route->update(['accepts_parcels' => true]);
        $network = TransferNetwork::query()->where('code', 'ORANGE')->firstOrFail();
        $network->update(['receive_phone' => '70000001']);

        Sanctum::actingAs($buyer);
        $createdEscrow = $this->postJson('/api/v1/trusted-payments', [
            'pin' => '1234',
            'merchandise_amount' => 8000,
            'payment_network_id' => $network->id,
            'merchant_phone' => '0722222290',
            'product_description' => 'Chaussures',
        ])->assertCreated();

        $escrow = \App\Models\TrustedPayment::query()->where('uuid', $createdEscrow->json('data.uuid'))->firstOrFail();
        app(TransactionService::class)->markPaymentReceived($escrow->transaction);

        Sanctum::actingAs($merchant);
        $this->post('/api/v1/parcels', [
            'pin' => '1234',
            'travel_company_route_id' => $route->id,
            'delivery_mode' => 'door_delivery',
            'trusted_payment_public_id' => $escrow->public_id,
            'sender_phone' => '0722222290',
            'pickup_address' => $this->pickupMapsUrl(),
            'recipient_name' => 'Client Livraison',
            'recipient_phone' => '0711111190',
            'recipient_cnib_number' => 'B33334444',
            'recipient_address' => 'https://www.google.com/maps/place/Bobo',
            'delivery_distance_km' => 4,
            'parcel_description' => 'Livraison escrow',
            'declared_value' => 8000,
            'sender_name' => 'Marchand',
            'sender_cnib_number' => 'B12345678',
            'sender_cnib_photo' => UploadedFile::fake()->image('sender-cnib.jpg'),
            'estimated_weight_kg' => 2,
            'parcel_photo' => UploadedFile::fake()->image('parcel.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');

        $escrow->refresh();
        $this->assertSame(\App\Data\TrustedPaymentStatusEnum::Collected, $escrow->status);
        $this->assertSame(\App\Data\TrustedPayoutStatusEnum::Processing, $escrow->payout_status);
    }
}
