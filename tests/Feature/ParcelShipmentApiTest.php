<?php

namespace Tests\Feature;

use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelFeeModeEnum;
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
        ];
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
            .'&pickup_distance_km=3'
            .'&declared_value=50000'
        )->assertOk();

        $this->assertEquals(1000, $quote->json('data.agency_fee'));
        $this->assertEquals(500, $quote->json('data.margin_amount'));
        $this->assertEquals(500, $quote->json('data.pickup.total'));
        $this->assertEquals(0, $quote->json('data.delivery.total'));

        Sanctum::actingAs($user);

        $created = $this->post('/api/v1/parcels', [
            'pin' => '1234',
            'travel_company_route_id' => $route->id,
            'delivery_mode' => 'station_pickup',
            'payment_network_id' => $network->id,
            'sender_phone' => '0711111133',
            'pickup_address' => 'Ouaga, secteur 15',
            'pickup_district' => 'Ouaga',
            'pickup_distance_km' => 3,
            'recipient_phone' => '0722222233',
            'parcel_description' => 'Carton documents',
            'declared_value' => 50000,
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
            'pickup_address' => 'Ouaga, secteur 10',
            'pickup_distance_km' => 2,
            'recipient_phone' => '0722222244',
            'recipient_address' => 'Bobo, secteur 3',
            'delivery_distance_km' => 4,
            'parcel_description' => 'Colis fragile',
            'declared_value' => 25000,
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
        $parcels->transition($shipment->fresh(), ParcelStatusEnum::Delivered, 'Livré à domicile');

        $shipment->refresh();
        $this->assertSame(ParcelStatusEnum::Delivered, $shipment->status);
        $this->assertSame('Remis au destinataire', $shipment->status->trackingLabel());
        $this->assertTrue($shipment->transaction->fresh()->isServed());
    }
}
