<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromotionResource;
use App\Http\Resources\TransferNetworkResource;
use App\Http\Resources\TravelTripResource;
use App\Services\PromotionService;
use App\Services\TransferNetworkService;
use App\Services\TravelCompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        protected TransferNetworkService $networks,
        protected TravelCompanyService $travel,
        protected PromotionService $promotions,
    ) {}

    public function networks(): JsonResponse
    {
        return response()->json([
            'data' => TransferNetworkResource::collection($this->networks->listActive()),
        ]);
    }

    public function promotions(): JsonResponse
    {
        return response()->json([
            'data' => PromotionResource::collection($this->promotions->listActive()),
        ]);
    }

    public function cities(): JsonResponse
    {
        return response()->json([
            'data' => $this->travel->cities(),
        ]);
    }

    public function corridors(): JsonResponse
    {
        return response()->json([
            'data' => $this->travel->popularCorridors(),
        ]);
    }

    public function trips(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departure' => ['required', 'string', 'max:120'],
            'arrival' => ['required', 'string', 'max:120'],
        ]);

        return response()->json([
            'data' => TravelTripResource::collection(
                $this->travel->searchTrips($data['departure'], $data['arrival']),
            ),
        ]);
    }
}
