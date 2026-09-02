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

        return $device->fresh() ?? $device;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{deposit: RelayDeposit, created: bool}
     */
    public function ingestDeposit(RelayDevice $device, array $payload): array
    {
        $existing = RelayDeposit::query()
            ->where('provider_transaction_id', $payload['provider_transaction_id'])
            ->first();

        if ($existing) {
            return ['deposit' => $existing, 'created' => false];
        }

        $deposit = RelayDeposit::query()->create([
            'relay_device_id' => $device->id,
            'network' => strtolower((string) $payload['network']),
            'amount' => $payload['amount'],
            'provider_transaction_id' => $payload['provider_transaction_id'],
            'sender_phone' => Phone::normalize((string) $payload['sender_phone']),
            'sender_name' => $payload['sender_name'] ?? null,
            'received_at' => isset($payload['received_at'])
                ? Carbon::parse($payload['received_at'])
                : now(),
            'raw_body' => $payload['raw_body'] ?? null,
        ]);

        $this->matchDeposit($deposit);

        return ['deposit' => $deposit->fresh() ?? $deposit, 'created' => true];
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

    protected function findMatchingTransaction(RelayDeposit $deposit): ?Transaction
    {
        $candidates = Transaction::query()
            ->with(['sourceNetwork', 'paymentNetwork'])
            ->whereIn('payment_status', [
                PaymentStatusEnum::PENDING,
                PaymentStatusEnum::PROCESSING,
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull('payment_expires_at')
                    ->orWhere('payment_expires_at', '>=', now()->subMinutes(5));
            })
            ->orderBy('created_at')
            ->get();

        $expectedAmount = (int) round((float) $deposit->amount);

        return $candidates->first(function (Transaction $transaction) use ($deposit, $expectedAmount): bool {
            if ((int) round((float) $transaction->totalAmount()) !== $expectedAmount) {
                return false;
            }

            $network = $transaction->payingNetwork();

            if (! $network || $network->relayCode() !== strtolower($deposit->network)) {
                return false;
            }

            return Phone::matches((string) $transaction->payerPhone(), (string) $deposit->sender_phone);
        });
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
