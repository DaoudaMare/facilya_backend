<?php

namespace App\Services;

use App\Data\FeeQuote;
use App\Data\PaymentStatusEnum;
use App\Data\ServiceStatusEnum;
use App\Data\TransactionTypeEnum;
use App\Models\Transaction;
use App\Models\TransferNetwork;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyTripRepositoryInterface;
use App\Support\Phone;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class TransactionService
{
    public function __construct(
        protected TransactionRepositoryInterface $transactions,
        protected TravelCompanyTripRepositoryInterface $trips,
        protected FeeQuoteService $feeQuotes,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Transaction
    {
        return $this->transactions->create($this->sanitize($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTicketPurchase(array $attributes): Transaction
    {
        return $this->create([
            ...$attributes,
            'type' => TransactionTypeEnum::TICKET_PURCHASE,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createNetworkTransfer(array $attributes): Transaction
    {
        return $this->create([
            ...$attributes,
            'type' => TransactionTypeEnum::NETWORK_TRANSFER,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Transaction $transaction, array $attributes): Transaction
    {
        return $this->transactions->update($transaction, $this->sanitize($attributes, $transaction));
    }

    public function findByReference(string $reference): ?Transaction
    {
        return $this->transactions->findByReference($reference);
    }

    public function prepareDefaults(Transaction $transaction): void
    {
        $transaction->uuid ??= (string) Str::uuid();
        $transaction->payment_status ??= PaymentStatusEnum::PENDING;
        $transaction->service_status ??= ServiceStatusEnum::PENDING;
        $transaction->currency ??= 'XOF';
        $transaction->reference ??= $this->generateReference($transaction->type);
    }

    public function hydrate(Transaction $transaction): void
    {
        $transaction->payment_status ??= PaymentStatusEnum::PENDING;
        $this->syncTicketRouteFromTrip($transaction);

        if ($transaction->isPaid()) {
            return;
        }

        $this->syncServiceAmount($transaction);
        $this->applyQuotedFees($transaction);
    }

    public function applyQuotedFees(Transaction $transaction): void
    {
        $quote = $this->quoteForTransaction($transaction);

        $transaction->network_fee = $quote->networkFee;
        $transaction->platform_fee = $quote->platformFee;
    }

    public function quoteForTransaction(Transaction $transaction): FeeQuote
    {
        $type = $transaction->type instanceof TransactionTypeEnum
            ? $transaction->type
            : TransactionTypeEnum::tryFrom((string) $transaction->type);

        if (! $type || $transaction->amount === null || (float) $transaction->amount <= 0) {
            return FeeQuote::empty((string) ($transaction->amount ?? '0'));
        }

        $networkId = $transaction->isTicketPurchase()
            ? $transaction->payment_network_id
            : $transaction->source_network_id;

        $counterpartId = $transaction->isNetworkTransfer()
            ? $transaction->destination_network_id
            : null;

        return $this->feeQuotes->quote(
            $type,
            (string) $transaction->amount,
            $networkId ? (int) $networkId : null,
            $counterpartId ? (int) $counterpartId : null,
        );
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function quoteFromState(array $state): FeeQuote
    {
        $type = $state['type'] ?? null;
        $type = $type instanceof TransactionTypeEnum
            ? $type
            : TransactionTypeEnum::tryFrom((string) $type);

        if (! $type) {
            return FeeQuote::empty();
        }

        $amount = (string) ($state['amount'] ?? '0');
        $seats = max(1, (int) ($state['passenger_count'] ?? 1));

        if ($type === TransactionTypeEnum::TICKET_PURCHASE && filled($state['travel_company_route_id'] ?? null)) {
            $amount = $this->feeQuotes->serviceAmountForTicket(
                (int) $state['travel_company_route_id'],
                $seats,
            );
        }

        $networkId = $type === TransactionTypeEnum::TICKET_PURCHASE
            ? ($state['payment_network_id'] ?? null)
            : ($state['source_network_id'] ?? null);

        $counterpartId = $type === TransactionTypeEnum::NETWORK_TRANSFER
            ? ($state['destination_network_id'] ?? null)
            : null;

        return $this->feeQuotes->quote(
            $type,
            $amount,
            $networkId ? (int) $networkId : null,
            $counterpartId ? (int) $counterpartId : null,
        );
    }

    public function generateReference(mixed $type): string
    {
        $enum = $type instanceof TransactionTypeEnum
            ? $type
            : TransactionTypeEnum::tryFrom((string) $type);

        return sprintf(
            '%s-%s-%s',
            $enum?->prefix() ?? 'TX',
            now()->format('Ymd'),
            strtoupper(Str::random(8)),
        );
    }

    public function markPaymentProcessing(Transaction $transaction): Transaction
    {
        $transaction->fill([
            'payment_status' => PaymentStatusEnum::PROCESSING,
            'payment_failure_reason' => null,
        ]);
        $transaction->save();

        return $transaction;
    }

    public function markPaymentReceived(Transaction $transaction, ?string $paymentReference = null): Transaction
    {
        $transaction->fill([
            'payment_status' => PaymentStatusEnum::RECEIVED,
            'paid_at' => now(),
            'payment_failure_reason' => null,
            'payment_reference' => $paymentReference ?? $transaction->payment_reference,
        ]);
        $transaction->save();

        return $transaction;
    }

    public function markPaymentFailed(Transaction $transaction, string $reason): Transaction
    {
        $transaction->fill([
            'payment_status' => PaymentStatusEnum::FAILED,
            'payment_failure_reason' => $reason,
        ]);
        $transaction->save();

        return $transaction;
    }

    public function markServiceProcessing(Transaction $transaction): Transaction
    {
        if (! $transaction->isPaid()) {
            throw new LogicException('Le service ne peut démarrer qu’après réception du paiement.');
        }

        $transaction->fill([
            'service_status' => ServiceStatusEnum::PROCESSING,
            'service_failure_reason' => null,
        ]);
        $transaction->save();

        return $transaction;
    }

    public function markServiceDelivered(Transaction $transaction, ?string $serviceReference = null): Transaction
    {
        if (! $transaction->isPaid()) {
            throw new LogicException('Le service ne peut être livré qu’après réception du paiement.');
        }

        $transaction->fill([
            'service_status' => ServiceStatusEnum::DELIVERED,
            'served_at' => now(),
            'service_failure_reason' => null,
            'service_reference' => $serviceReference ?? $transaction->service_reference,
        ]);
        $transaction->save();

        return $transaction;
    }

    public function markServiceFailed(Transaction $transaction, string $reason): Transaction
    {
        $transaction->fill([
            'service_status' => ServiceStatusEnum::FAILED,
            'service_failure_reason' => $reason,
        ]);
        $transaction->save();

        return $transaction;
    }

    /**
     * @return array{today_count: int, today_volume: string, pending_payment: int, awaiting_service: int}
     */
    public function dashboardStats(): array
    {
        return [
            'today_count' => $this->transactions->countToday(),
            'today_volume' => $this->transactions->sumAmountToday(),
            'pending_payment' => $this->transactions->countPendingPayment(),
            'awaiting_service' => $this->transactions->countAwaitingService(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    public function listForUser(int $userId, ?string $type = null, int $limit = 50)
    {
        return $this->transactions->listForUser($userId, $type, $limit);
    }

    /**
     * @return array{month_total: string, exchanges_total: string, tickets_total: string, count: int}
     */
    public function monthlyStatsForUser(int $userId): array
    {
        return $this->transactions->monthlyStatsForUser($userId);
    }

    public function findForUser(int $userId, int $id): ?Transaction
    {
        return $this->transactions->findForUser($userId, $id);
    }

    public function quoteTransfer(string $amount, ?int $sourceNetworkId, ?int $destinationNetworkId): FeeQuote
    {
        if ($sourceNetworkId) {
            $this->assertSendable($sourceNetworkId, 'source_network_id');
        }
        if ($destinationNetworkId) {
            $this->assertReceivable($destinationNetworkId);
        }

        return $this->quoteFromState([
            'type' => TransactionTypeEnum::NETWORK_TRANSFER,
            'amount' => $amount,
            'source_network_id' => $sourceNetworkId,
            'destination_network_id' => $destinationNetworkId,
        ]);
    }

    public function quoteTicket(int $tripId, int $passengerCount, ?int $paymentNetworkId): FeeQuote
    {
        if ($paymentNetworkId) {
            $this->assertSendable($paymentNetworkId, 'payment_network_id');
        }

        return $this->quoteFromState([
            'type' => TransactionTypeEnum::TICKET_PURCHASE,
            'travel_company_trip_id' => $tripId,
            'travel_company_route_id' => $this->trips->routeIdOf($tripId),
            'passenger_count' => $passengerCount,
            'payment_network_id' => $paymentNetworkId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function placeTransfer(int $userId, array $attributes): Transaction
    {
        $this->assertReceivesPayments((int) $attributes['source_network_id'], 'source_network_id');
        $this->assertReceivable((int) $attributes['destination_network_id']);

        return $this->createNetworkTransfer([
            ...$attributes,
            'user_id' => $userId,
            'payment_expires_at' => now()->addMinutes(30),
        ])->load(['sourceNetwork', 'destinationNetwork', 'paymentNetwork']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function placeTicket(int $userId, array $attributes): Transaction
    {
        $this->assertReceivesPayments((int) $attributes['payment_network_id'], 'payment_network_id');

        $this->assertTripHasSeats(
            (int) $attributes['travel_company_trip_id'],
            max(1, (int) ($attributes['passenger_count'] ?? 1)),
        );

        return $this->createTicketPurchase([
            ...$attributes,
            'user_id' => $userId,
            'payment_expires_at' => now()->addMinutes(30),
        ])->load(['route.travelCompany', 'trip.station', 'paymentNetwork']);
    }

    public function completeForClient(Transaction $transaction): Transaction
    {
        $this->markPaymentReceived($transaction, 'MOBILE-'.$transaction->reference);
        $this->fulfillAfterPayment($transaction, 'SVC-'.$transaction->reference);

        return $transaction->fresh([
            'sourceNetwork',
            'destinationNetwork',
            'paymentNetwork',
            'route.travelCompany',
            'trip.station',
        ]) ?? $transaction;
    }

    public function fulfillAfterPayment(Transaction $transaction, ?string $serviceReference = null): Transaction
    {
        $transaction->loadMissing(['paymentNetwork', 'sourceNetwork', 'destinationNetwork', 'trip']);

        if ($transaction->isTicketPurchase()) {
            try {
                $this->assertTripHasSeats(
                    (int) $transaction->travel_company_trip_id,
                    max(1, (int) ($transaction->passenger_count ?? 1)),
                );
            } catch (ValidationException $exception) {
                return $this->markServiceFailed($transaction, 'Plus assez de places disponibles sur ce départ.');
            }

            $this->consumeSeats($transaction);

            return $this->markServiceDelivered($transaction, $serviceReference ?? 'TCK-'.$transaction->reference);
        }

        return $transaction;
    }

    protected function assertTripHasSeats(int $tripId, int $seats): void
    {
        $trip = $this->trips->findOrFail($tripId);

        if ($trip->available_seats !== null && $trip->available_seats < $seats) {
            throw ValidationException::withMessages([
                'travel_company_trip_id' => 'Plus assez de places disponibles sur ce départ.',
            ]);
        }
    }

    public function consumeSeats(Transaction $transaction): void
    {
        if (! $transaction->travel_company_trip_id) {
            return;
        }

        $trip = $this->trips->find((int) $transaction->travel_company_trip_id);

        if (! $trip || $trip->available_seats === null) {
            return;
        }

        $this->trips->update($trip, [
            'available_seats' => max(0, $trip->available_seats - max(1, (int) $transaction->passenger_count)),
        ]);
    }

    public function syncTicketRouteFromTrip(Transaction $transaction): void
    {
        if (! $transaction->isTicketPurchase()) {
            $transaction->travel_company_route_id = null;
            $transaction->travel_company_trip_id = null;
            $transaction->travel_date = null;

            return;
        }

        if (! $transaction->travel_company_trip_id) {
            return;
        }

        $routeId = $this->trips->routeIdOf((int) $transaction->travel_company_trip_id);

        if ($routeId) {
            $transaction->travel_company_route_id = $routeId;
        }
    }

    public function syncServiceAmount(Transaction $transaction): void
    {
        if (! $transaction->isTicketPurchase() || ! $transaction->travel_company_route_id) {
            return;
        }

        $seats = max(1, (int) ($transaction->passenger_count ?: 1));
        $amount = $this->feeQuotes->serviceAmountForTicket(
            (int) $transaction->travel_company_route_id,
            $seats,
        );

        if ((float) $amount <= 0) {
            return;
        }

        $transaction->amount = $amount;
        $transaction->passenger_count = $seats;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function sanitize(array $attributes, ?Transaction $existing = null): array
    {
        $type = $attributes['type'] ?? $existing?->type;
        $type = $type instanceof TransactionTypeEnum
            ? $type
            : TransactionTypeEnum::tryFrom((string) $type);

        if (! $type) {
            throw ValidationException::withMessages([
                'type' => 'Le type de transaction est obligatoire.',
            ]);
        }

        $attributes['type'] = $type;
        $attributes['payment_status'] ??= $existing?->payment_status ?? PaymentStatusEnum::PENDING;
        $attributes['service_status'] ??= $existing?->service_status ?? ServiceStatusEnum::PENDING;
        $attributes['currency'] ??= $existing?->currency ?? 'XOF';
        $attributes['reference'] ??= $existing?->reference ?? $this->generateReference($type);

        if ($type === TransactionTypeEnum::TICKET_PURCHASE) {
            if (empty($attributes['travel_company_route_id']) && ! empty($attributes['travel_company_trip_id'])) {
                $attributes['travel_company_route_id'] = $this->trips->routeIdOf(
                    (int) $attributes['travel_company_trip_id'],
                );
            }

            $attributes['passenger_count'] = max(1, (int) ($attributes['passenger_count'] ?? $existing?->passenger_count ?? 1));
            $attributes['source_network_id'] = null;
            $attributes['destination_network_id'] = null;
            $attributes['sender_phone'] = null;
            $attributes['recipient_phone'] = null;
            $attributes['recipient_name'] = null;
        } else {
            $sourceId = $attributes['source_network_id'] ?? $existing?->source_network_id;
            $destinationId = $attributes['destination_network_id'] ?? $existing?->destination_network_id;

            if ($sourceId && $destinationId && (int) $sourceId === (int) $destinationId) {
                throw ValidationException::withMessages([
                    'destination_network_id' => 'Le réseau de destination doit être différent du réseau source.',
                ]);
            }

            $attributes['travel_company_route_id'] = null;
            $attributes['travel_company_trip_id'] = null;
            $attributes['travel_date'] = null;
            $attributes['passenger_name'] = null;
            $attributes['passenger_phone'] = null;
            $attributes['passenger_count'] = null;
        }

        return $attributes;
    }

    protected function assertSendable(int $id, string $field): TransferNetwork
    {
        $network = TransferNetwork::query()->find($id);

        if (! $network || ! $network->is_active || ! $network->can_send) {
            throw ValidationException::withMessages([
                $field => 'Ce réseau ne peut pas être utilisé pour envoyer ou payer.',
            ]);
        }

        return $network;
    }

    protected function assertReceivesPayments(int $id, string $field): TransferNetwork
    {
        $network = $this->assertSendable($id, $field);

        if (blank($network->receive_phone) || ! Phone::isValid((string) $network->receive_phone)) {
            throw ValidationException::withMessages([
                $field => 'Ce réseau n’a pas encore de numéro de réception configuré.',
            ]);
        }

        return $network;
    }

    protected function assertReceivable(int $id): TransferNetwork
    {
        $network = TransferNetwork::query()->find($id);

        if (! $network || ! $network->is_active || ! $network->can_receive) {
            throw ValidationException::withMessages([
                'destination_network_id' => 'Ce réseau ne peut pas recevoir d’argent.',
            ]);
        }

        return $network;
    }
}
