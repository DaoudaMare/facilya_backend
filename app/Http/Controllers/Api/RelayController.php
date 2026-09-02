<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RelayDevice;
use App\Services\RelayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class RelayController extends Controller
{
    public function __construct(
        protected RelayService $relay,
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->device($request);

        $data = $request->validate([
            'fulfill_networks' => ['sometimes', 'array'],
            'fulfill_networks.*' => ['string', 'max:32'],
        ]);

        $device = $this->relay->heartbeat($device, $data);

        return response()->json([
            'data' => $this->relay->devicePayload($device),
        ]);
    }

    public function deposits(Request $request): JsonResponse
    {
        $device = $this->device($request);

        $data = $request->validate([
            'network' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:1'],
            'provider_transaction_id' => ['required', 'string', 'max:120'],
            'sender_phone' => ['required', 'string', 'max:32'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'received_at' => ['nullable', 'date'],
            'raw_body' => ['nullable', 'string'],
        ]);

        $result = $this->relay->ingestDeposit($device, $data);

        return response()->json([
            'data' => [
                'uuid' => $result['deposit']->uuid,
                'matched' => (bool) $result['deposit']->transaction_id,
                'transaction_uuid' => $result['deposit']->transaction?->uuid,
            ],
        ], $result['created'] ? 201 : 200);
    }

    public function transactions(Request $request): JsonResponse
    {
        $this->device($request);

        return response()->json([
            'data' => $this->relay->listForRelay()->map(
                fn ($transaction) => $this->relay->transactionPayload($transaction),
            )->values(),
        ]);
    }

    public function pendingPayments(Request $request): JsonResponse
    {
        $this->device($request);

        return response()->json([
            'data' => $this->relay->pendingPayments()->map(
                fn ($transaction) => $this->relay->transactionPayload($transaction),
            )->values(),
        ]);
    }

    public function confirmPayment(Request $request, string $uuid): JsonResponse
    {
        $device = $this->device($request);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $transaction = $this->relay->confirmPayment(
            $device,
            $uuid,
            $data['note'] ?? null,
        );

        return response()->json([
            'data' => $this->relay->transactionPayload($transaction),
        ]);
    }

    public function nextJob(Request $request): JsonResponse|Response
    {
        $device = $this->device($request);

        $data = $request->validate([
            'wait' => ['sometimes', 'integer', 'min:0', 'max:25'],
        ]);

        $job = $this->relay->claimNextJob($device, (int) ($data['wait'] ?? 0));

        if (! $job) {
            return response()->noContent();
        }

        return response()->json([
            'data' => $this->relay->jobPayload($job),
        ]);
    }

    public function completeJob(Request $request, string $uuid): JsonResponse
    {
        $device = $this->device($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['succeeded', 'failed'])],
            'provider_reference' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:2000'],
            'failure_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $job = $this->relay->completeJob($device, $uuid, $data);

        return response()->json([
            'data' => $this->relay->jobPayload($job),
        ]);
    }

    protected function device(Request $request): RelayDevice
    {
        $device = $request->user();

        abort_unless($device instanceof RelayDevice, 403, 'Jeton relais invalide.');

        return $device;
    }
}
