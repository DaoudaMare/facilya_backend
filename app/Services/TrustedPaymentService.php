<?php

namespace App\Services;

use App\Data\TransactionTypeEnum;
use App\Data\TrustedPaymentQuote;
use App\Data\TrustedPaymentStatusEnum;
use App\Data\TrustedPayoutStatusEnum;
use App\Models\ParcelShipment;
use App\Models\RelayJob;
use App\Models\TrustedPayment;
use App\Models\TrustedPaymentEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\MerchantFundsHeldNotification;
use App\Support\Phone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrustedPaymentService
{
    public function __construct(
        protected FeeQuoteService $feeQuotes,
        protected TransactionService $transactions,
    ) {}

    public function quote(string $merchandiseAmount, ?int $paymentNetworkId = null): TrustedPaymentQuote
    {
        $amount = number_format((float) $merchandiseAmount, 2, '.', '');

        if ((float) $amount <= 0) {
            throw ValidationException::withMessages([
                'merchandise_amount' => 'Le montant de la marchandise doit être supérieur à 0.',
            ]);
        }

        $feeQuote = $this->feeQuotes->quote(
            TransactionTypeEnum::TRUSTED_PAYMENT,
            $amount,
            $paymentNetworkId,
            null,
        );

        return new TrustedPaymentQuote(
            merchandiseAmount: $amount,
            networkFee: $feeQuote->networkFee,
            platformFee: $feeQuote->platformFee,
            payoutAmount: $amount,
        );
    }

    public function findMerchantByPhone(string $phone): User
    {
        $normalized = Phone::normalize($phone);

        if (! Phone::isValid($normalized)) {
            throw ValidationException::withMessages([
                'merchant_phone' => 'Numéro commerçant invalide.',
            ]);
        }

        $users = User::query()->whereNotNull('phone')->get();
        $merchant = $users->first(fn (User $user) => Phone::matches((string) $user->phone, $normalized));

        if (! $merchant) {
            throw ValidationException::withMessages([
                'merchant_phone' => 'Aucun compte Facilya trouvé pour ce numéro. Le commerçant doit avoir un compte.',
            ]);
        }

        return $merchant;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $buyer, array $attributes): TrustedPayment
    {
        $merchant = isset($attributes['merchant_user_id'])
            ? User::query()->findOrFail((int) $attributes['merchant_user_id'])
            : $this->findMerchantByPhone((string) ($attributes['merchant_phone'] ?? ''));

        if ((int) $merchant->id === (int) $buyer->id) {
            throw ValidationException::withMessages([
                'merchant_phone' => 'Vous ne pouvez pas être à la fois acheteur et commerçant.',
            ]);
        }

        $paymentNetworkId = (int) $attributes['payment_network_id'];
        $this->transactions->assertReceivesPayments($paymentNetworkId, 'payment_network_id');

        $buyerPhone = Phone::normalize((string) ($attributes['buyer_deposit_phone'] ?? $buyer->phone));
        $merchantPayoutPhone = Phone::normalize((string) ($attributes['merchant_payout_phone'] ?? $merchant->phone));

        $phoneErrors = [];
        if (! Phone::isValid($buyerPhone)) {
            $phoneErrors['buyer_deposit_phone'] = ['Numéro de dépôt acheteur invalide.'];
        }
        if (! Phone::isValid($merchantPayoutPhone)) {
            $phoneErrors['merchant_payout_phone'] = ['Numéro de versement commerçant invalide.'];
        }
        if ($phoneErrors !== []) {
            throw ValidationException::withMessages($phoneErrors);
        }

        $description = trim((string) ($attributes['product_description'] ?? ''));
        if ($description === '') {
            throw ValidationException::withMessages([
                'product_description' => 'La description du produit est obligatoire.',
            ]);
        }

        $merchandiseAmount = number_format((float) $attributes['merchandise_amount'], 2, '.', '');
        $quote = $this->quote($merchandiseAmount, $paymentNetworkId);
        $declaredValue = isset($attributes['declared_value'])
            ? number_format((float) $attributes['declared_value'], 2, '.', '')
            : $merchandiseAmount;

        $payoutNetworkId = isset($attributes['payout_network_id'])
            ? (int) $attributes['payout_network_id']
            : $paymentNetworkId;

        return DB::transaction(function () use (
            $buyer,
            $merchant,
            $attributes,
            $paymentNetworkId,
            $payoutNetworkId,
            $buyerPhone,
            $merchantPayoutPhone,
            $description,
            $quote,
            $declaredValue,
        ) {
            $reference = $this->generateReference();

            $transaction = $this->transactions->createTrustedPayment([
                'user_id' => $buyer->id,
                'amount' => $quote->merchandiseAmount,
                'network_fee' => $quote->networkFee,
                'platform_fee' => $quote->platformFee,
                'payment_network_id' => $paymentNetworkId,
                'sender_phone' => $buyerPhone,
                'recipient_phone' => $merchantPayoutPhone,
                'recipient_name' => $merchant->name,
                'description' => sprintf('Paiement confiant · %s', $description),
                'payment_expires_at' => now()->addMinutes(30),
                'reference' => $reference,
                'metadata' => [
                    'merchant_user_id' => $merchant->id,
                    'product_description' => $description,
                ],
            ]);

            $payment = TrustedPayment::query()->create([
                'reference' => $reference,
                'buyer_user_id' => $buyer->id,
                'merchant_user_id' => $merchant->id,
                'transaction_id' => $transaction->id,
                'status' => TrustedPaymentStatusEnum::PendingPayment,
                'payout_status' => TrustedPayoutStatusEnum::Pending,
                'merchandise_amount' => $quote->merchandiseAmount,
                'network_fee' => $quote->networkFee,
                'platform_fee' => $quote->platformFee,
                'total_amount' => $quote->totalAmount(),
                'payout_amount' => $quote->payoutAmount,
                'buyer_deposit_phone' => $buyerPhone,
                'merchant_payout_phone' => $merchantPayoutPhone,
                'payment_network_id' => $paymentNetworkId,
                'payout_network_id' => $payoutNetworkId,
                'product_description' => $description,
                'declared_value' => $declaredValue,
                'pickup_address' => $attributes['pickup_address'] ?? null,
                'pickup_district' => $attributes['pickup_district'] ?? null,
                'delivery_address' => $attributes['delivery_address'] ?? null,
                'delivery_district' => $attributes['delivery_district'] ?? null,
            ]);

            $this->recordEvent(
                $payment,
                TrustedPaymentStatusEnum::PendingPayment,
                'Paiement confiant créé, en attente de dépôt.',
                'buyer',
                $buyer->id,
            );

            return $payment->load([
                'buyer',
                'merchant',
                'transaction.paymentNetwork',
                'paymentNetwork',
                'payoutNetwork',
                'events',
            ]);
        });
    }

    public function listForBuyer(int $userId): Collection
    {
        return TrustedPayment::query()
            ->with(['merchant', 'transaction.paymentNetwork', 'paymentNetwork', 'parcelShipment'])
            ->where('buyer_user_id', $userId)
            ->latest('id')
            ->get();
    }

    public function listLinkableForBuyer(int $userId): Collection
    {
        return TrustedPayment::query()
            ->with(['merchant', 'paymentNetwork'])
            ->where('buyer_user_id', $userId)
            ->whereIn('status', [
                TrustedPaymentStatusEnum::FundsHeld->value,
                TrustedPaymentStatusEnum::ExpeditionRequested->value,
            ])
            ->whereDoesntHave('parcelShipment')
            ->latest('id')
            ->get();
    }

    public function findLinkableForBuyer(int $userId, string $uuid): TrustedPayment
    {
        $payment = TrustedPayment::query()
            ->with(['merchant', 'paymentNetwork'])
            ->where('uuid', $uuid)
            ->where('buyer_user_id', $userId)
            ->whereIn('status', [
                TrustedPaymentStatusEnum::FundsHeld->value,
                TrustedPaymentStatusEnum::ExpeditionRequested->value,
            ])
            ->whereDoesntHave('parcelShipment')
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'trusted_payment_uuid' => 'Aucun paiement confiant disponible à lier (fonds bloqués, non déjà utilisé).',
            ]);
        }

        return $payment;
    }

    public function listForMerchant(int $userId): Collection
    {
        return TrustedPayment::query()
            ->with(['buyer', 'transaction.paymentNetwork', 'paymentNetwork'])
            ->where('merchant_user_id', $userId)
            ->whereIn('status', [
                TrustedPaymentStatusEnum::FundsHeld->value,
                TrustedPaymentStatusEnum::ExpeditionRequested->value,
                TrustedPaymentStatusEnum::CourierEnRoute->value,
                TrustedPaymentStatusEnum::Collected->value,
                TrustedPaymentStatusEnum::InTransit->value,
                TrustedPaymentStatusEnum::Arrived->value,
                TrustedPaymentStatusEnum::Delivered->value,
            ])
            ->latest('id')
            ->get();
    }

    public function findForParticipant(int $userId, string $uuid): ?TrustedPayment
    {
        return TrustedPayment::query()
            ->with(['buyer', 'merchant', 'transaction.paymentNetwork', 'paymentNetwork', 'payoutNetwork', 'events'])
            ->where('uuid', $uuid)
            ->where(function ($query) use ($userId): void {
                $query->where('buyer_user_id', $userId)
                    ->orWhere('merchant_user_id', $userId);
            })
            ->first();
    }

    public function confirmPayment(Transaction $transaction): void
    {
        if (! $transaction->isTrustedPayment()) {
            return;
        }

        $payment = TrustedPayment::query()
            ->where('transaction_id', $transaction->id)
            ->first();

        if (! $payment || $payment->status !== TrustedPaymentStatusEnum::PendingPayment) {
            return;
        }

        $this->transition(
            $payment,
            TrustedPaymentStatusEnum::FundsHeld,
            'Fonds reçus et bloqués chez Facilya.',
            'system',
        );

        $payment->refresh();
        $payment->loadMissing('merchant');
        if ($payment->merchant) {
            try {
                $payment->merchant->notify(new MerchantFundsHeldNotification($payment));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Notif commerçant paiement confiant impossible.', [
                    'trusted_payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function onLinkedToParcel(TrustedPayment $payment, ParcelShipment $shipment): TrustedPayment
    {
        $isDelivery = $shipment->isDoorDelivery();

        if ($isDelivery) {
            return $this->transition(
                $payment,
                TrustedPaymentStatusEnum::Collected,
                'Livraison colis '.$shipment->reference.' : versement immédiat du paiement confiant.',
                'system',
            );
        }

        if ($payment->status === TrustedPaymentStatusEnum::FundsHeld) {
            $payment->fill([
                'pickup_address' => $shipment->pickup_address ?: $payment->pickup_address,
                'pickup_district' => $shipment->pickup_district ?: $payment->pickup_district,
                'delivery_address' => $shipment->recipient_address ?: $payment->delivery_address,
                'delivery_district' => $shipment->recipient_district ?: $payment->delivery_district,
            ])->save();

            return $this->transition(
                $payment,
                TrustedPaymentStatusEnum::ExpeditionRequested,
                'Lié à l’expédition colis '.$shipment->reference.'. Déblocage à la collecte avec le code.',
                'buyer',
                (int) $shipment->user_id,
            );
        }

        return $payment;
    }

    public function releaseOnParcelCollected(TrustedPayment $payment, ParcelShipment $shipment): TrustedPayment
    {
        $status = $payment->status instanceof TrustedPaymentStatusEnum
            ? $payment->status
            : TrustedPaymentStatusEnum::from((string) $payment->status);

        if (in_array($status, [
            TrustedPaymentStatusEnum::Collected,
            TrustedPaymentStatusEnum::InTransit,
            TrustedPaymentStatusEnum::Arrived,
            TrustedPaymentStatusEnum::Delivered,
            TrustedPaymentStatusEnum::Cancelled,
            TrustedPaymentStatusEnum::Failed,
        ], true)) {
            return $payment;
        }

        return $this->transition(
            $payment,
            TrustedPaymentStatusEnum::Collected,
            'Collecte colis '.$shipment->reference.' : déblocage confirmé avec le code.',
            'admin',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function requestExpedition(User $merchant, TrustedPayment $payment, array $attributes = []): TrustedPayment
    {
        if ((int) $payment->merchant_user_id !== (int) $merchant->id) {
            throw ValidationException::withMessages([
                'merchant' => 'Seul le commerçant de cette transaction peut demander l’expédition.',
            ]);
        }

        if ($payment->status !== TrustedPaymentStatusEnum::FundsHeld) {
            throw ValidationException::withMessages([
                'status' => 'L’expédition ne peut être demandée que lorsque les fonds sont bloqués.',
            ]);
        }

        $pickupAddress = trim((string) ($attributes['pickup_address'] ?? $payment->pickup_address ?? ''));
        if ($pickupAddress === '') {
            throw ValidationException::withMessages([
                'pickup_address' => 'L’adresse de collecte est obligatoire.',
            ]);
        }

        $payment->fill([
            'pickup_address' => $pickupAddress,
            'pickup_district' => $attributes['pickup_district'] ?? $payment->pickup_district,
            'delivery_address' => $attributes['delivery_address'] ?? $payment->delivery_address,
            'delivery_district' => $attributes['delivery_district'] ?? $payment->delivery_district,
            'product_description' => filled($attributes['product_description'] ?? null)
                ? trim((string) $attributes['product_description'])
                : $payment->product_description,
        ]);
        $payment->save();

        return $this->transition(
            $payment,
            TrustedPaymentStatusEnum::ExpeditionRequested,
            'Demande d’expédition enregistrée.',
            'merchant',
            $merchant->id,
        );
    }

    public function transition(
        TrustedPayment $payment,
        TrustedPaymentStatusEnum $next,
        ?string $note = null,
        string $actorType = 'admin',
        ?int $actorUserId = null,
    ): TrustedPayment {
        $current = $payment->status instanceof TrustedPaymentStatusEnum
            ? $payment->status
            : TrustedPaymentStatusEnum::from((string) $payment->status);

        if ($current->isFinal()) {
            throw ValidationException::withMessages([
                'status' => 'Ce paiement confiant est déjà terminé.',
            ]);
        }

        if (! in_array($next, $current->allowedNext(), true)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Transition invalide : %s → %s.',
                    $current->label(),
                    $next->label(),
                ),
            ]);
        }

        $payment->status = $next;

        match ($next) {
            TrustedPaymentStatusEnum::FundsHeld => $payment->funds_held_at = now(),
            TrustedPaymentStatusEnum::ExpeditionRequested => $payment->expedition_requested_at = now(),
            TrustedPaymentStatusEnum::Collected => $payment->collected_at = now(),
            TrustedPaymentStatusEnum::Delivered => $payment->delivered_at = now(),
            default => null,
        };

        $payment->save();
        $this->recordEvent($payment, $next, $note, $actorType, $actorUserId);

        if ($next === TrustedPaymentStatusEnum::Collected) {
            $this->releasePayout($payment->fresh() ?? $payment);
        }

        if ($next === TrustedPaymentStatusEnum::Delivered && $payment->transaction_id) {
            $tx = Transaction::query()->find($payment->transaction_id);
            if ($tx && ! $tx->isServed()) {
                $this->transactions->markServiceDelivered($tx, 'CF-'.$payment->reference);
            }
        }

        if ($next === TrustedPaymentStatusEnum::Cancelled && $payment->transaction_id) {
            $tx = Transaction::query()->find($payment->transaction_id);
            if ($tx) {
                $this->transactions->markCancelled($tx, $note ?? 'Paiement confiant annulé.');
            }
        }

        if ($next === TrustedPaymentStatusEnum::Failed && $payment->transaction_id) {
            $tx = Transaction::query()->find($payment->transaction_id);
            if ($tx && ! $tx->isServed()) {
                $this->transactions->markServiceFailed($tx, $note ?? 'Paiement confiant échoué.');
            }
        }

        return $payment->fresh([
            'buyer',
            'merchant',
            'transaction.paymentNetwork',
            'paymentNetwork',
            'payoutNetwork',
            'events',
        ]) ?? $payment;
    }

    public function releasePayout(TrustedPayment $payment, bool $forceRetry = false): TrustedPayment
    {
        $payment->loadMissing(['payoutNetwork', 'merchant', 'transaction']);

        if (! $forceRetry && in_array($payment->payout_status, [
            TrustedPayoutStatusEnum::Sent,
            TrustedPayoutStatusEnum::Processing,
        ], true)) {
            return $payment;
        }

        $network = $payment->payoutNetwork;
        $job = RelayJob::query()->create([
            'transaction_id' => $payment->transaction_id,
            'type' => 'transfer',
            'status' => 'pending',
            'network' => $network?->relayCode() ?? '',
            'recipient_phone' => $payment->merchant_payout_phone,
            'recipient_name' => $payment->merchant?->name,
            'amount' => $payment->payout_amount,
            'currency' => $payment->currency ?? 'XOF',
        ]);

        $payment->fill([
            'payout_status' => TrustedPayoutStatusEnum::Processing,
            'payout_relay_job_id' => $job->id,
            'payout_reference' => null,
        ])->save();

        $this->recordEvent(
            $payment,
            $payment->status instanceof TrustedPaymentStatusEnum
                ? $payment->status
                : TrustedPaymentStatusEnum::from((string) $payment->status),
            'Versement commerçant initié ('.$payment->payout_amount.' XOF).',
            'system',
        );

        return $payment->fresh() ?? $payment;
    }

    public function handlePayoutJobResult(RelayJob $job, bool $succeeded): void
    {
        $payment = TrustedPayment::query()
            ->where('transaction_id', $job->transaction_id)
            ->first();

        if (! $payment) {
            return;
        }

        if ($succeeded) {
            $payment->fill([
                'payout_status' => TrustedPayoutStatusEnum::Sent,
                'payout_reference' => $job->provider_reference ?: 'PO-'.$payment->reference,
                'payout_sent_at' => now(),
            ])->save();

            $this->recordEvent(
                $payment,
                $payment->status instanceof TrustedPaymentStatusEnum
                    ? $payment->status
                    : TrustedPaymentStatusEnum::from((string) $payment->status),
                'Versement commerçant confirmé.',
                'system',
            );

            return;
        }

        $payment->fill([
            'payout_status' => TrustedPayoutStatusEnum::Failed,
        ])->save();

        $this->recordEvent(
            $payment,
            $payment->status instanceof TrustedPaymentStatusEnum
                ? $payment->status
                : TrustedPaymentStatusEnum::from((string) $payment->status),
            'Échec du versement commerçant : '.($job->failure_reason ?: 'inconnu'),
            'system',
        );
    }

    protected function recordEvent(
        TrustedPayment $payment,
        TrustedPaymentStatusEnum $status,
        ?string $note,
        string $actorType,
        ?int $actorUserId = null,
    ): void {
        TrustedPaymentEvent::query()->create([
            'trusted_payment_id' => $payment->id,
            'status' => $status,
            'note' => $note,
            'actor_type' => $actorType,
            'actor_user_id' => $actorUserId,
        ]);
    }

    protected function generateReference(): string
    {
        return sprintf(
            'CF-%s-%s',
            now()->format('ymd'),
            strtoupper(Str::random(6)),
        );
    }
}
