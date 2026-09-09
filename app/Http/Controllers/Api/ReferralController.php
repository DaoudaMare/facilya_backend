<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        protected ReferralService $referrals,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->referrals->summaryFor($request->user()),
        ]);
    }
}
