<?php

namespace App\Http\Controllers\Api;

use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelScopeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ParcelShipmentResource;
use App\Services\AuthService;
use App\Services\ParcelShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParcelController extends Controller
{
    public function __construct(
        protected ParcelShipmentService $parcels,
        protected AuthService $auth,
    ) {}

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', Rule::enum(ParcelScopeEnum::class)],
            'travel_company_route_id' => ['nullable', 'required_unless:scope,local', 'integer', 'exists:travel_company_routes,id'],
            'origin_city' => ['nullable', 'required_if:scope,local', 'string', 'max:120'],
            'delivery_mode' => ['required', Rule::enum(ParcelDeliveryModeEnum::class)],
            'payment_network_id' => ['nullable', 'integer', 'exists:transfer_networks,id'],
            'pickup_address' => ['nullable', 'string', 'url', 'max:2048'],
            'delivery_distance_km' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $scope = ParcelScopeEnum::from($data['scope'] ?? ParcelScopeEnum::Intercity->value);
        $mode = ParcelDeliveryModeEnum::from($data['delivery_mode']);

        return response()->json([
            'data' => $this->parcels->quote(
                isset($data['travel_company_route_id']) ? (int) $data['travel_company_route_id'] : null,
                $mode,
                isset($data['payment_network_id']) ? (int) $data['payment_network_id'] : null,
                isset($data['delivery_distance_km']) ? (float) $data['delivery_distance_km'] : null,
                isset($data['declared_value']) ? (float) $data['declared_value'] : null,
                $scope,
                $data['pickup_address'] ?? null,
                $data['origin_city'] ?? null,
            )->toArray(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ParcelShipmentResource::collection(
                $this->parcels->listForUser((int) $request->user()->id),
            ),
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $shipment = $this->parcels->findForUser((int) $request->user()->id, $uuid);

        abort_unless($shipment, 404);

        return response()->json([
            'data' => ParcelShipmentResource::make($shipment),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'scope' => ['nullable', Rule::enum(ParcelScopeEnum::class)],
            'travel_company_route_id' => ['nullable', 'required_unless:scope,local', 'integer', 'exists:travel_company_routes,id'],
            'origin_city' => ['nullable', 'required_if:scope,local', 'string', 'max:120'],
            'destination_city' => ['nullable', 'string', 'max:120'],
            'delivery_mode' => ['required', Rule::enum(ParcelDeliveryModeEnum::class)],
            'payment_network_id' => ['required_without_all:trusted_payment_public_id,trusted_payment_uuid', 'nullable', 'integer', 'exists:transfer_networks,id'],
            'trusted_payment_public_id' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'trusted_payment_uuid' => ['nullable', 'uuid'],
            'sender_name' => ['required', 'string', 'max:120'],
            'sender_phone' => ['required', 'string', 'max:32'],
            'sender_cnib_number' => ['nullable', 'string', 'max:64'],
            'sender_cnib_photo' => ['nullable', 'image', 'max:5120'],
            'pickup_address' => ['required', 'string', 'url', 'max:2048'],
            'pickup_district' => ['nullable', 'string', 'max:120'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'recipient_phone' => ['required', 'string', 'max:32'],
            'recipient_cnib_number' => ['required', 'string', 'max:64'],
            'recipient_cnib_photo' => ['nullable', 'required_without_all:trusted_payment_public_id,trusted_payment_uuid', 'image', 'max:5120'],
            'recipient_address' => ['nullable', 'required_if:delivery_mode,door_delivery', 'string', 'url', 'max:2048'],
            'recipient_district' => ['nullable', 'string', 'max:120'],
            'delivery_distance_km' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'parcel_description' => ['required', 'string', 'max:255'],
            'parcel_photo' => ['required', 'image', 'max:5120'],
            'estimated_weight_kg' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'declared_value' => ['required', 'numeric', 'min:0'],
        ]);

        $this->auth->assertPin($request->user(), $data['pin']);

        $shipment = $this->parcels->place($request->user(), $data);

        return response()->json([
            'data' => ParcelShipmentResource::make($shipment),
        ], 201);
    }
}
