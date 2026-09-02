<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Services\AuthService;
use App\Services\TransactionService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactions,
        protected AuthService $auth,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['network_transfer', 'ticket_purchase'])],
        ]);

        return response()->json([
            'data' => TransactionResource::collection(
                $this->transactions->listForUser(
                    (int) $request->user()->id,
                    $data['type'] ?? null,
                ),
            ),
        ]);
    }

    public function show(Request $request, int $transaction): JsonResponse
    {
        $model = $this->transactions->findForUser((int) $request->user()->id, $transaction);

        abort_unless($model, 404);

        return response()->json([
            'data' => TransactionResource::make($model),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->transactions->monthlyStatsForUser((int) $request->user()->id),
        ]);
    }

    public function quoteTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:100'],
            'source_network_id' => ['required', 'integer', 'exists:transfer_networks,id'],
            'destination_network_id' => ['required', 'integer', 'exists:transfer_networks,id', 'different:source_network_id'],
        ]);

        return response()->json([
            'data' => $this->transactions->quoteTransfer(
                (string) $data['amount'],
                (int) $data['source_network_id'],
                (int) $data['destination_network_id'],
            )->toArray(),
        ]);
    }

    public function quoteTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:travel_company_trips,id'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:9'],
            'payment_network_id' => ['nullable', 'integer', 'exists:transfer_networks,id'],
        ]);

        return response()->json([
            'data' => $this->transactions->quoteTicket(
                (int) $data['trip_id'],
                (int) $data['passenger_count'],
                isset($data['payment_network_id']) ? (int) $data['payment_network_id'] : null,
            )->toArray(),
        ]);
    }

    public function storeTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'amount' => ['required', 'numeric', 'min:100'],
            'source_network_id' => ['required', 'integer', 'exists:transfer_networks,id'],
            'destination_network_id' => ['required', 'integer', 'exists:transfer_networks,id', 'different:source_network_id'],
            'sender_phone' => ['required', 'string', 'max:32'],
            'recipient_phone' => ['required', 'string', 'max:32'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $this->auth->assertPin($request->user(), $data['pin']);

        $sender = Phone::normalize($data['sender_phone']);
        $recipient = Phone::normalize($data['recipient_phone']);

        if (! Phone::isValid($sender) || ! Phone::isValid($recipient)) {
            return response()->json([
                'message' => 'Numéro de téléphone invalide.',
                'errors' => [
                    'sender_phone' => Phone::isValid($sender) ? [] : ['Numéro source invalide.'],
                    'recipient_phone' => Phone::isValid($recipient) ? [] : ['Numéro destinataire invalide.'],
                ],
            ], 422);
        }

        $transaction = $this->transactions->placeTransfer((int) $request->user()->id, [
            'amount' => $data['amount'],
            'source_network_id' => $data['source_network_id'],
            'destination_network_id' => $data['destination_network_id'],
            'sender_phone' => $sender,
            'recipient_phone' => $recipient,
            'recipient_name' => $data['recipient_name'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return response()->json([
            'data' => TransactionResource::make($transaction),
        ], 201);
    }

    public function storeTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'travel_company_trip_id' => ['required', 'integer', 'exists:travel_company_trips,id'],
            'travel_date' => ['required', 'date'],
            'passenger_name' => ['required', 'string', 'max:120'],
            'passenger_phone' => ['required', 'string', 'max:32'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:9'],
            'payment_network_id' => ['required', 'integer', 'exists:transfer_networks,id'],
        ]);

        $this->auth->assertPin($request->user(), $data['pin']);

        $phone = Phone::normalize($data['passenger_phone']);
        if (! Phone::isValid($phone)) {
            return response()->json([
                'message' => 'Numéro de téléphone invalide.',
                'errors' => ['passenger_phone' => ['Numéro passager invalide.']],
            ], 422);
        }

        $transaction = $this->transactions->placeTicket((int) $request->user()->id, [
            'travel_company_trip_id' => $data['travel_company_trip_id'],
            'travel_date' => $data['travel_date'],
            'passenger_name' => $data['passenger_name'],
            'passenger_phone' => $phone,
            'passenger_count' => $data['passenger_count'],
            'payment_network_id' => $data['payment_network_id'],
        ]);

        return response()->json([
            'data' => TransactionResource::make($transaction),
        ], 201);
    }
}
