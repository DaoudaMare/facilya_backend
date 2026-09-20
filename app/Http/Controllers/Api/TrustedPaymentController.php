<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrustedPaymentResource;
use App\Services\AuthService;
use App\Services\TrustedPaymentService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedPaymentController extends Controller
{
    public function __construct(
        protected TrustedPaymentService $trustedPayments,
        protected AuthService $auth,
    ) {}

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchandise_amount' => ['required', 'numeric', 'min:100'],
            'payment_network_id' => ['nullable', 'integer', 'exists:transfer_networks,id'],
        ]);

        return response()->json([
            'data' => $this->trustedPayments->quote(
                (string) $data['merchandise_amount'],
                isset($data['payment_network_id']) ? (int) $data['payment_network_id'] : null,
            )->toArray(),
        ]);
    }

    public function lookupMerchant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $merchant = $this->trustedPayments->findMerchantByPhone($data['phone']);

        return response()->json([
            'data' => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'phone' => Phone::format((string) $merchant->phone),
            ],
        ]);
    }

    public function lookupByPublicId(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_id' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $payment = $this->trustedPayments->lookupForMerchantParcel(
            (int) $request->user()->id,
            (string) $data['public_id'],
        );

        return response()->json([
            'data' => TrustedPaymentResource::make($payment),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $role = $request->query('role', 'buyer');

        if ($request->boolean('linkable')) {
            $items = $this->trustedPayments->listLinkableForBuyer((int) $request->user()->id);
        } elseif ($role === 'merchant') {
            $items = $this->trustedPayments->listForMerchant((int) $request->user()->id);
        } else {
            $items = $this->trustedPayments->listForBuyer((int) $request->user()->id);
        }

        return response()->json([
            'data' => TrustedPaymentResource::collection($items),
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $payment = $this->trustedPayments->findForParticipant((int) $request->user()->id, $uuid);

        abort_unless($payment, 404);

        return response()->json([
            'data' => TrustedPaymentResource::make($payment),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'merchandise_amount' => ['required', 'numeric', 'min:100'],
            'payment_network_id' => ['required', 'integer', 'exists:transfer_networks,id'],
            'payout_network_id' => ['nullable', 'integer', 'exists:transfer_networks,id'],
            'merchant_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'merchant_phone' => ['required_without:merchant_user_id', 'nullable', 'string', 'max:32'],
            'buyer_deposit_phone' => ['nullable', 'string', 'max:32'],
            'merchant_payout_phone' => ['nullable', 'string', 'max:32'],
            'product_description' => ['required', 'string', 'max:255'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'pickup_district' => ['nullable', 'string', 'max:120'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'delivery_district' => ['nullable', 'string', 'max:120'],
        ]);

        $this->auth->assertPin($request->user(), $data['pin']);

        $payment = $this->trustedPayments->create($request->user(), $data);

        return response()->json([
            'data' => TrustedPaymentResource::make($payment),
        ], 201);
    }

    public function requestExpedition(Request $request, string $uuid): JsonResponse
    {
        $payment = $this->trustedPayments->findForParticipant((int) $request->user()->id, $uuid);

        abort_unless($payment, 404);

        $data = $request->validate([
            'pickup_address' => ['required', 'string', 'max:255'],
            'pickup_district' => ['nullable', 'string', 'max:120'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'delivery_district' => ['nullable', 'string', 'max:120'],
            'product_description' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->trustedPayments->requestExpedition($request->user(), $payment, $data);

        return response()->json([
            'data' => TrustedPaymentResource::make($updated),
        ]);
    }
}
