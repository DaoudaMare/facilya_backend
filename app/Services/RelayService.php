<?php

namespace App\Services;

use App\Data\PaymentStatusEnum;
use App\Data\ServiceStatusEnum;
use App\Models\RelayDeposit;
use App\Models\RelayDevice;
use App\Models\RelayJob;
use App\Models\Transaction;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RelayService
{
    public function __construct(
        protected TransactionService $transactions,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function heartbeat(RelayDevice $device, array $payload): RelayDevice
    {
        $networks = collect($payload['fulfill_networks'] ?? [])
            ->map(fn ($code) => strtolower(trim((string) $code)))
            ->filter()
            ->values()
            ->all();

        $device->fill([
            'last_seen_at' => now(),
            'fulfill_networks' => $networks !== [] ? $networks : $device->fulfill_networks,
        ])->save();

        $this->rematchUnmatchedDeposits();

        return $device->fresh() ?? $device;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{deposit: RelayDeposit, created: bool}
     */
    public function ingestDeposit(RelayDevice $device, array $payload): array
    {
        return $this->storeDeposit($device, $payload, autoMatch: true);
    }

    /**
     * Enregistre un SMS de dépôt. Ne le lie à une transaction que si
     * $autoMatch est vrai (arrivée live). Un SMS déjà lié n'est jamais réutilisé.
     *
     * @param  array<string, mixed>  $payload
     * @return array{deposit: RelayDeposit, created: bool}
     */
    public function storeDeposit(RelayDevice $device, array $payload, bool $autoMatch = true): array
    {
        $existing = RelayDeposit::query()
            ->where('provider_transaction_id', $payload['provider_transaction_id'])
            ->first();

        if ($existing) {
            if ($autoMatch && ! $existing->transaction_id) {
                $this->matchDeposit($existing);
            }

            $existing = $existing->fresh(['transaction']) ?? $existing;

            return ['deposit' => $existing, 'created' => false];
        }

        $deposit = RelayDeposit::query()->create([
            'relay_device_id' => $device->id,
            'network' => strtolower((string) $payload['network']),
            'amount' => $payload['amount'],
            'provider_transaction_id' => $payload['provider_transaction_id'],
            'sender_phone' => Phone::normalize((string) ($payload['sender_phone'] ?? '')),
            'sender_name' => $payload['sender_name'] ?? null,
            'received_at' => isset($payload['received_at'])
                ? Carbon::parse($payload['received_at'])
                : now(),
            'raw_body' => $payload['raw_body'] ?? null,
        ]);

        if ($autoMatch) {
            $this->matchDeposit($deposit);
        }

        return ['deposit' => $deposit->fresh(['transaction']) ?? $deposit, 'created' => true];
    }

    public function matchDeposit(RelayDeposit $deposit): ?Transaction
    {
        if ($deposit->transaction_id) {
            return $deposit->transaction;
        }

        $transaction = $this->findMatchingTransaction($deposit);

        if (! $transaction) {
            return null;
        }

        $this->applyReceivedPayment($transaction, $deposit);

        return $transaction->fresh() ?? $transaction;
    }

    public function confirmPayment(RelayDevice $device, string $uuid, ?string $note = null): Transaction
    {
        $transaction = Transaction::query()
            ->with(['sourceNetwork', 'destinationNetwork', 'paymentNetwork', 'trip', 'deposits'])
            ->where('uuid', $uuid)
            ->first();

        if (! $transaction) {
            throw ValidationException::withMessages([
                'uuid' => 'Transaction introuvable.',
            ]);
        }

        if ($transaction->isPaid()) {
            return $transaction;
        }

        $status = $transaction->payment_status instanceof PaymentStatusEnum
            ? $transaction->payment_status
            : PaymentStatusEnum::tryFrom((string) $transaction->payment_status);

        if (! in_array($status, [PaymentStatusEnum::PENDING, PaymentStatusEnum::PROCESSING], true)) {
            throw ValidationException::withMessages([
                'uuid' => 'Ce paiement ne peut plus être confirmé.',
            ]);
        }

        $reference = 'MANUAL-'.$transaction->reference;
        $deposit = RelayDeposit::query()->where('provider_transaction_id', $reference)->first();

        if (! $deposit) {
            $deposit = RelayDeposit::query()->create([
                'relay_device_id' => $device->id,
                'transaction_id' => $transaction->id,
                'network' => $transaction->payingNetwork()?->relayCode() ?? '',
                'amount' => $transaction->totalAmount(),
                'provider_transaction_id' => $reference,
                'sender_phone' => $transaction->payerPhone() ?? '',
                'sender_name' => 'Confirmation manuelle',
                'received_at' => now(),
                'raw_body' => $note ?: 'Paiement confirmé manuellement depuis TelRelayX.',
                'matched_at' => now(),
            ]);
        }

        return $this->applyReceivedPayment($transaction, $deposit);
    }

    public function cancelPayment(RelayDevice $device, string $uuid, ?string $note = null): Transaction
    {
        $transaction = Transaction::query()
            ->with(['sourceNetwork', 'destinationNetwork', 'paymentNetwork', 'trip', 'deposits'])
            ->where('uuid', $uuid)
            ->first();

        if (! $transaction) {
            throw ValidationException::withMessages([
                'uuid' => 'Transaction introuvable.',
            ]);
        }

        $status = $transaction->payment_status instanceof PaymentStatusEnum
            ? $transaction->payment_status
            : PaymentStatusEnum::tryFrom((string) $transaction->payment_status);

        if ($status === PaymentStatusEnum::CANCELLED) {
            return $transaction;
        }

        if ($transaction->isPaid()) {
            throw ValidationException::withMessages([
                'uuid' => 'Un paiement reçu ne peut plus être annulé.',
            ]);
        }

        if (! in_array($status, [PaymentStatusEnum::PENDING, PaymentStatusEnum::PROCESSING], true)) {
            throw ValidationException::withMessages([
                'uuid' => 'Cette transaction ne peut plus être annulée.',
            ]);
        }

        $reason = $note ?: 'Annulée depuis TelRelayX ('.$device->name.').';
        $transaction = $this->transactions->markCancelled($transaction, $reason);

        RelayJob::query()
            ->where('transaction_id', $transaction->id)
            ->whereIn('status', ['pending', 'claimed'])
            ->update([
                'status' => 'failed',
                'failure_reason' => 'transaction_cancelled',
                'message' => $reason,
                'completed_at' => now(),
            ]);

        return $transaction->fresh([
            'sourceNetwork',
            'destinationNetwork',
            'paymentNetwork',
            'trip',
            'deposits',
        ]) ?? $transaction;
    }

    /**
     * Relance le matching d'une transaction en attente : enregistre les SMS
     * envoyés par le relais, puis lie le premier dépôt inutilisé qui
     * correspond (après création, même numéro, même montant / réseau).
     *
     * @param  list<array<string, mixed>>  $deposits
     * @return array{transaction: Transaction, matched: bool, deposits_stored: int}
     */
    public function retryPayment(RelayDevice $device, string $uuid, array $deposits = []): array
    {
        $transaction = Transaction::query()
            ->with(['sourceNetwork', 'destinationNetwork', 'paymentNetwork', 'trip', 'deposits'])
            ->where('uuid', $uuid)
            ->first();

        if (! $transaction) {
            throw ValidationException::withMessages([
                'uuid' => 'Transaction introuvable.',
            ]);
        }

        $stored = 0;
        foreach ($deposits as $payload) {
            $result = $this->storeDeposit($device, $payload, autoMatch: false);
            if ($result['created']) {
                $stored++;
            }
        }

        if ($transaction->isPaid()) {
            return [
                'transaction' => $transaction->fresh(['deposits']) ?? $transaction,
                'matched' => true,
                'deposits_stored' => $stored,
            ];
        }

        $status = $transaction->payment_status instanceof PaymentStatusEnum
            ? $transaction->payment_status
            : PaymentStatusEnum::tryFrom((string) $transaction->payment_status);

        if (! in_array($status, [PaymentStatusEnum::PENDING, PaymentStatusEnum::PROCESSING], true)) {
            throw ValidationException::withMessages([
                'uuid' => 'Cette transaction ne peut plus être relancée.',
            ]);
        }

        $deposit = $this->findMatchingDepositFor($transaction);

        if (! $deposit) {
            return [
                'transaction' => $transaction->fresh(['deposits']) ?? $transaction,
                'matched' => false,
                'deposits_stored' => $stored,
            ];
        }

        $transaction = $this->applyReceivedPayment($transaction, $deposit);

        return [
            'transaction' => $transaction,
            'matched' => true,
            'deposits_stored' => $stored,
        ];
    }

    protected function applyReceivedPayment(Transaction $transaction, RelayDeposit $deposit): Transaction
    {
        if (! $transaction->isPaid()) {
            $this->transactions->markPaymentReceived($transaction, $deposit->provider_transaction_id);
        }

        $deposit->fill([
            'transaction_id' => $transaction->id,
            'matched_at' => $deposit->matched_at ?? now(),
        ])->save();

        $transaction = $transaction->fresh([
            'sourceNetwork',
            'destinationNetwork',
            'paymentNetwork',
            'trip',
        ]) ?? $transaction;

        if ($transaction->isTicketPurchase()) {
            if (! $transaction->isServed()) {
                $this->transactions->fulfillAfterPayment($transaction);
            }
        } else {
            $service = $transaction->service_status;
            $serviceValue = $service instanceof ServiceStatusEnum
                ? $service
                : ServiceStatusEnum::tryFrom((string) $service);

            if ($serviceValue === ServiceStatusEnum::PENDING) {
                $this->transactions->markServiceProcessing($transaction);
            }

            $this->createFulfillmentJob($transaction);
        }

        return $transaction->fresh([
            'sourceNetwork',
            'destinationNetwork',
            'paymentNetwork',
            'deposits',
        ]) ?? $transaction;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function listForRelay(?int $limit = 50): Collection
    {
        return Transaction::query()
            ->with(['sourceNetwork', 'destinationNetwork', 'paymentNetwork', 'deposits'])
            ->where(function ($query): void {
                $query
                    ->whereIn('payment_status', [
                        PaymentStatusEnum::PENDING,
                        PaymentStatusEnum::PROCESSING,
                        PaymentStatusEnum::RECEIVED,
                    ])
                    ->orWhere('created_at', '>=', now()->subDay());
            })
            ->latest()
            ->limit($limit ?? 50)
            ->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function pendingPayments(): Collection
    {
        return Transaction::query()
            ->with(['sourceNetwork', 'destinationNetwork', 'paymentNetwork', 'deposits'])
            ->whereIn('payment_status', [
                PaymentStatusEnum::PENDING,
                PaymentStatusEnum::PROCESSING,
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull('payment_expires_at')
                    ->orWhere('payment_expires_at', '>=', now());
            })
            ->latest()
            ->limit(50)
            ->get();
    }

    public function claimNextJob(RelayDevice $device, int $waitSeconds = 0): ?RelayJob
    {
        $networks = $device->fulfillNetworkCodes();
        $deadline = now()->addSeconds(max(0, min($waitSeconds, 25)));

        do {
            $job = $this->claimJob($device, $networks);

            if ($job) {
                return $job;
            }

            if (now()->greaterThanOrEqualTo($deadline)) {
                return null;
            }

            usleep(400_000);
        } while (true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function completeJob(RelayDevice $device, string $jobUuid, array $payload): RelayJob
    {
        $job = RelayJob::query()
            ->where('uuid', $jobUuid)
            ->first();

        if (! $job) {
            throw ValidationException::withMessages([
                'uuid' => 'Job introuvable.',
            ]);
        }

        if ($job->relay_device_id && (int) $job->relay_device_id !== (int) $device->id) {
            throw ValidationException::withMessages([
                'uuid' => 'Ce job n’appartient pas à cet appareil.',
            ]);
        }

        if (in_array($job->status, ['succeeded', 'failed'], true)) {
            return $job;
        }

        $succeeded = ($payload['status'] ?? '') === 'succeeded';

        $job->fill([
            'status' => $succeeded ? 'succeeded' : 'failed',
            'provider_reference' => $payload['provider_reference'] ?? $job->provider_reference,
            'message' => $payload['message'] ?? null,
            'failure_reason' => $succeeded ? null : ($payload['failure_reason'] ?? 'unknown'),
            'completed_at' => now(),
            'relay_device_id' => $device->id,
        ])->save();

        $transaction = $job->transaction;

        if ($transaction) {
            if ($succeeded) {
                $this->transactions->markServiceDelivered(
                    $transaction,
                    $job->provider_reference ?: 'SVC-'.$transaction->reference,
                );
            } else {
                $this->transactions->markServiceFailed(
                    $transaction,
                    (string) ($job->failure_reason ?: 'Échec de l’envoi USSD.'),
                );
            }
        }

        return $job->fresh() ?? $job;
    }

    /**
     * @return array<string, mixed>
     */
    public function devicePayload(RelayDevice $device): array
    {
        return [
            'uuid' => $device->uuid,
            'name' => $device->name,
            'network' => $device->network,
            'phone_number' => $device->phone_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jobPayload(RelayJob $job): array
    {
        $job->loadMissing('transaction');

        return [
            'uuid' => $job->uuid,
            'type' => $job->type,
            'status' => $job->status,
            'network' => $job->network,
            'recipient_phone' => $job->recipient_phone,
            'recipient_name' => $job->recipient_name,
            'currency' => $job->currency,
            'amount' => number_format((float) $job->amount, 0, '.', ''),
            'transaction_uuid' => $job->transaction?->uuid,
            'transaction_reference' => $job->transaction?->reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionPayload(Transaction $transaction): array
    {
        $transaction->loadMissing(['sourceNetwork', 'destinationNetwork', 'paymentNetwork', 'deposits']);

        $source = $transaction->isTicketPurchase()
            ? $transaction->paymentNetwork
            : $transaction->sourceNetwork;
        $destination = $transaction->isTicketPurchase()
            ? null
            : $transaction->destinationNetwork;
        $deposit = $transaction->deposits->sortByDesc('matched_at')->first();

        return [
            'uuid' => $transaction->uuid,
            'reference' => $transaction->reference,
            'source_network' => $source?->relayCode() ?? '',
            'destination_network' => $destination?->relayCode() ?? ($transaction->isTicketPurchase() ? 'ticket' : ''),
            'sender_phone' => $transaction->payerPhone() ?? '',
            'recipient_phone' => $transaction->isTicketPurchase()
                ? (string) $transaction->passenger_phone
                : (string) $transaction->recipient_phone,
            'recipient_name' => $transaction->isTicketPurchase()
                ? $transaction->passenger_name
                : $transaction->recipient_name,
            'amount' => number_format((float) $transaction->amount, 0, '.', ''),
            'fee' => number_format((float) $transaction->totalFees(), 0, '.', ''),
            'total_amount' => number_format((float) $transaction->totalAmount(), 0, '.', ''),
            'currency' => $transaction->currency,
            'payment_status' => $this->relayPaymentStatus($transaction),
            'created_at' => $transaction->created_at?->toIso8601String(),
            'payment_expires_at' => $transaction->payment_expires_at?->toIso8601String(),
            'deposit' => $deposit ? [
                'sender_phone' => $deposit->sender_phone,
                'sender_name' => $deposit->sender_name,
                'provider_transaction_id' => $deposit->provider_transaction_id,
                'received_at' => $deposit->received_at?->toIso8601String(),
                'raw_body' => $deposit->raw_body,
                'amount' => number_format((float) $deposit->amount, 0, '.', ''),
                'network' => $deposit->network,
            ] : null,
        ];
    }

    protected function rematchUnmatchedDeposits(): void
    {
        RelayDeposit::query()
            ->whereNull('transaction_id')
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->limit(25)
            ->get()
            ->each(fn (RelayDeposit $deposit) => $this->matchDeposit($deposit));
    }

    protected function findMatchingTransaction(RelayDeposit $deposit): ?Transaction
    {
        $candidates = Transaction::query()
            ->with(['sourceNetwork', 'paymentNetwork'])
            ->whereIn('payment_status', [
                PaymentStatusEnum::PENDING,
                PaymentStatusEnum::PROCESSING,
            ])
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at')
            ->get();

        $byAmountAndNetwork = $candidates->filter(
            fn (Transaction $transaction): bool => $this->depositFitsTransaction($deposit, $transaction),
        );

        $withPhone = $byAmountAndNetwork->first(function (Transaction $transaction) use ($deposit): bool {
            return Phone::matches((string) $transaction->payerPhone(), (string) $deposit->sender_phone);
        });

        if ($withPhone) {
            return $withPhone;
        }

        // Le SMS porte le numéro Mobile Money qui a payé, pas forcément
        // le compte Facilya. S'il n'y a qu'un paiement en attente pour
        // ce montant et ce réseau, on l'associe.
        if ($byAmountAndNetwork->count() === 1) {
            return $byAmountAndNetwork->first();
        }

        return null;
    }

    protected function findMatchingDepositFor(Transaction $transaction): ?RelayDeposit
    {
        $notBefore = $transaction->created_at?->copy()->subMinute() ?? now()->subDay();

        return RelayDeposit::query()
            ->whereNull('transaction_id')
            ->where(function ($query) use ($notBefore): void {
                $query
                    ->where('received_at', '>=', $notBefore)
                    ->orWhere(function ($inner) use ($notBefore): void {
                        $inner->whereNull('received_at')->where('created_at', '>=', $notBefore);
                    });
            })
            ->orderBy('received_at')
            ->orderBy('id')
            ->get()
            ->first(fn (RelayDeposit $deposit): bool => $this->depositFitsTransaction(
                $deposit,
                $transaction,
                requirePhone: true,
            ));
    }

    protected function depositFitsTransaction(
        RelayDeposit $deposit,
        Transaction $transaction,
        bool $requirePhone = false,
    ): bool {
        if ((int) round((float) $deposit->amount) !== (int) round((float) $transaction->totalAmount())) {
            return false;
        }

        $network = $transaction->payingNetwork();
        if (! $network || $network->relayCode() !== strtolower((string) $deposit->network)) {
            return false;
        }

        $when = $deposit->received_at ?? $deposit->created_at;
        $created = $transaction->created_at;
        if ($when && $created && $when->lt($created->copy()->subMinute())) {
            return false;
        }

        if ($requirePhone) {
            return Phone::matches(
                (string) $transaction->payerPhone(),
                (string) $deposit->sender_phone,
            );
        }

        return true;
    }

    protected function createFulfillmentJob(Transaction $transaction): RelayJob
    {
        $existing = RelayJob::query()
            ->where('transaction_id', $transaction->id)
            ->whereIn('status', ['pending', 'claimed'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $transaction->loadMissing('destinationNetwork');

        return RelayJob::query()->create([
            'transaction_id' => $transaction->id,
            'type' => 'transfer',
            'status' => 'pending',
            'network' => $transaction->destinationNetwork?->relayCode() ?? '',
            'recipient_phone' => $transaction->recipient_phone,
            'recipient_name' => $transaction->recipient_name,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?? 'XOF',
        ]);
    }

    /**
     * @param  list<string>  $networks
     */
    protected function claimJob(RelayDevice $device, array $networks): ?RelayJob
    {
        return RelayJob::query()->getConnection()->transaction(function () use ($device, $networks) {
            $query = RelayJob::query()
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->lockForUpdate();

            if ($networks !== []) {
                $query->whereIn('network', $networks);
            }

            $job = $query->first();

            if (! $job) {
                return null;
            }

            $job->fill([
                'status' => 'claimed',
                'relay_device_id' => $device->id,
                'claimed_at' => now(),
            ])->save();

            $device->forceFill(['last_seen_at' => now()])->save();

            return $job->fresh(['transaction']) ?? $job;
        });
    }

    protected function relayPaymentStatus(Transaction $transaction): string
    {
        $status = $transaction->payment_status instanceof PaymentStatusEnum
            ? $transaction->payment_status
            : PaymentStatusEnum::tryFrom((string) $transaction->payment_status);

        return match ($status) {
            PaymentStatusEnum::RECEIVED => 'paid',
            PaymentStatusEnum::PROCESSING => 'initiated',
            PaymentStatusEnum::FAILED => 'failed',
            PaymentStatusEnum::CANCELLED => 'cancelled',
            PaymentStatusEnum::REFUNDED => 'refunded',
            default => 'pending',
        };
    }
}
