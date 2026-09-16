<?php

namespace App\Services;

use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelFeeModeEnum;
use App\Data\ParcelQuote;
use App\Data\ParcelScopeEnum;
use App\Data\ParcelStatusEnum;
use App\Data\TransactionTypeEnum;
use App\Data\TrustedPaymentStatusEnum;
use App\Models\ParcelEvent;
use App\Models\ParcelPricingSetting;
use App\Models\ParcelShipment;
use App\Models\Transaction;
use App\Models\TravelCompanyRoute;
use App\Models\TravelCompanyStation;
use App\Models\User;
use App\Support\GoogleMapsLink;
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ParcelShipmentService
{
    public function __construct(
        protected FeeQuoteService $feeQuotes,
        protected TransactionService $transactions,
        protected MapDirectionService $mapDirections,
    ) {}

    public function quote(
        ?int $routeId,
        ParcelDeliveryModeEnum $mode,
        ?int $paymentNetworkId = null,
        ?float $deliveryDistanceKm = null,
        ?float $declaredValue = null,
        ParcelScopeEnum $scope = ParcelScopeEnum::Intercity,
        ?string $pickupAddress = null,
        ?string $originCity = null,
    ): ParcelQuote {
        if ($scope === ParcelScopeEnum::Local) {
            $mode = ParcelDeliveryModeEnum::DoorDelivery;
        }

        $settings = ParcelPricingSetting::current();
        $route = $scope === ParcelScopeEnum::Intercity
            ? $this->assertActiveRoute((int) $routeId)
            : null;

        $agency = $scope === ParcelScopeEnum::Local
            ? '0.00'
            : $this->computeAmount(
                $settings->agency_mode,
                (string) $settings->agency_value,
                $this->routeReferenceAmount($route),
            );

        $margin = $this->computeAmount(
            $settings->margin_mode,
            (string) $settings->margin_value,
            $agency,
        );

        $base = bcadd($agency, $margin, 2);

        $valuePercent = number_format((float) $settings->value_fee_percent, 4, '.', '');
        $valueFee = '0.00';
        if ($declaredValue !== null && $declaredValue > 0 && (float) $valuePercent > 0) {
            $valueFee = bcmul(
                number_format($declaredValue, 2, '.', ''),
                bcdiv($valuePercent, '100', 6),
                2,
            );
        }

        $pickupPerKm = number_format((float) $settings->pickup_per_km, 4, '.', '');
        $deliveryPerKm = number_format((float) $settings->delivery_per_km, 4, '.', '');

        $pickupDistanceKm = $this->resolvePickupDistanceKm(
            $pickupAddress,
            $route,
            $scope,
            $originCity,
        );

        $this->assertDistances($settings, $mode, $pickupDistanceKm, $deliveryDistanceKm);

        $pickupBase = $this->computeAmount(
            $settings->pickup_mode,
            (string) $settings->pickup_value,
            $base,
        );
        $pickupDistanceFee = $this->distanceFee($pickupPerKm, $pickupDistanceKm);
        $pickupTotal = bcadd($pickupBase, $pickupDistanceFee, 2);

        $deliveryBase = '0.00';
        $deliveryDistanceFee = '0.00';
        $deliveryTotal = '0.00';
        $resolvedDeliveryKm = null;

        if ($mode === ParcelDeliveryModeEnum::DoorDelivery) {
            $deliveryBase = $this->computeAmount(
                $settings->delivery_mode,
                (string) $settings->delivery_value,
                $base,
            );
            $deliveryDistanceFee = $this->distanceFee($deliveryPerKm, $deliveryDistanceKm);
            $deliveryTotal = bcadd($deliveryBase, $deliveryDistanceFee, 2);
            $resolvedDeliveryKm = $deliveryDistanceKm;
        }

        $serviceAmount = bcadd(
            bcadd(bcadd($base, $valueFee, 2), $pickupTotal, 2),
            $deliveryTotal,
            2,
        );

        $feeQuote = $this->feeQuotes->quote(
            TransactionTypeEnum::PARCEL_SHIPMENT,
            $serviceAmount,
            $paymentNetworkId,
            null,
        );

        return new ParcelQuote(
            agencyFee: $agency,
            marginAmount: $margin,
            baseAmount: $base,
            valueFee: $valueFee,
            pickupBaseFee: $pickupBase,
            pickupDistanceFee: $pickupDistanceFee,
            pickupFee: $pickupTotal,
            deliveryBaseFee: $deliveryBase,
            deliveryDistanceFee: $deliveryDistanceFee,
            deliveryFee: $deliveryTotal,
            networkFee: $feeQuote->networkFee,
            platformFee: $feeQuote->platformFee,
            pickupDistanceKm: $pickupDistanceKm,
            deliveryDistanceKm: $resolvedDeliveryKm,
            declaredValue: $declaredValue,
            valueFeePercent: number_format((float) $valuePercent, 2, '.', ''),
            pickupPerKm: number_format((float) $pickupPerKm, 2, '.', ''),
            deliveryPerKm: number_format((float) $deliveryPerKm, 2, '.', ''),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function place(User $user, array $attributes): ParcelShipment
    {
        $scope = ($attributes['scope'] ?? null) instanceof ParcelScopeEnum
            ? $attributes['scope']
            : ParcelScopeEnum::from((string) ($attributes['scope'] ?? ParcelScopeEnum::Intercity->value));

        $mode = $attributes['delivery_mode'] instanceof ParcelDeliveryModeEnum
            ? $attributes['delivery_mode']
            : ParcelDeliveryModeEnum::from((string) ($attributes['delivery_mode'] ?? ParcelDeliveryModeEnum::DoorDelivery->value));

        if ($scope === ParcelScopeEnum::Local) {
            $mode = ParcelDeliveryModeEnum::DoorDelivery;
        }

        $route = $scope === ParcelScopeEnum::Intercity
            ? $this->assertActiveRoute((int) $attributes['travel_company_route_id'])
            : null;

        $originCity = trim((string) ($attributes['origin_city'] ?? ''));
        $destinationCity = trim((string) ($attributes['destination_city'] ?? $originCity));

        if ($scope === ParcelScopeEnum::Local) {
            if ($originCity === '') {
                throw ValidationException::withMessages([
                    'origin_city' => 'La ville de livraison locale est obligatoire.',
                ]);
            }
            $destinationCity = $originCity;
        } else {
            $originCity = $originCity !== '' ? $originCity : (string) $route->departure;
            $destinationCity = $destinationCity !== '' ? $destinationCity : (string) $route->arrival;
        }
        $senderPhone = Phone::normalize((string) $attributes['sender_phone']);
        $recipientPhone = Phone::normalize((string) $attributes['recipient_phone']);

        $phoneErrors = [];
        if (! Phone::isValid($senderPhone)) {
            $phoneErrors['sender_phone'] = ['Numéro expéditeur invalide.'];
        }
        if (! Phone::isValid($recipientPhone)) {
            $phoneErrors['recipient_phone'] = ['Numéro destinataire invalide.'];
        }
        if ($phoneErrors !== []) {
            throw ValidationException::withMessages($phoneErrors);
        }

        $pickupAddress = GoogleMapsLink::normalize((string) ($attributes['pickup_address'] ?? ''));
        
        if ($pickupAddress === '') {
            throw ValidationException::withMessages([
                'pickup_address' => 'Le lien Google Maps de collecte est obligatoire.',
            ]);
        }
        if (! GoogleMapsLink::isValid($pickupAddress)) {
            throw ValidationException::withMessages([
                'pickup_address' => GoogleMapsLink::validationMessage(),
            ]);
        }
        $attributes['pickup_address'] = $pickupAddress;

        if ($mode === ParcelDeliveryModeEnum::DoorDelivery) {
            $recipientAddress = GoogleMapsLink::normalize((string) ($attributes['recipient_address'] ?? ''));
            if ($recipientAddress === '') {
                throw ValidationException::withMessages([
                    'recipient_address' => 'Le lien Google Maps de livraison est obligatoire pour une livraison à domicile.',
                ]);
            }
            if (! GoogleMapsLink::isValid($recipientAddress)) {
                throw ValidationException::withMessages([
                    'recipient_address' => GoogleMapsLink::validationMessage(),
                ]);
            }
            $attributes['recipient_address'] = $recipientAddress;
        } elseif (filled($attributes['recipient_address'] ?? null)) {
            $recipientAddress = GoogleMapsLink::normalize((string) $attributes['recipient_address']);
            if (! GoogleMapsLink::isValid($recipientAddress)) {
                throw ValidationException::withMessages([
                    'recipient_address' => GoogleMapsLink::validationMessage(),
                ]);
            }
            $attributes['recipient_address'] = $recipientAddress;
        }

        $senderCnib = trim((string) ($attributes['sender_cnib_number'] ?? ''));
        if ($senderCnib === '') {
            $senderCnib = trim((string) ($user->cnib_number ?? ''));
        }
        $recipientCnib = trim((string) ($attributes['recipient_cnib_number'] ?? ''));
        $cnibErrors = [];
        if ($senderCnib === '') {
            $cnibErrors['sender_cnib_number'] = ['Le numéro CNIB de l’expéditeur est obligatoire.'];
        }
        if ($recipientCnib === '') {
            $cnibErrors['recipient_cnib_number'] = ['Le numéro CNIB du destinataire est obligatoire.'];
        }
        if ($cnibErrors !== []) {
            throw ValidationException::withMessages($cnibErrors);
        }

        $senderPhoto = $this->storeCnibPhoto(
            $attributes['sender_cnib_photo'] ?? null,
            'sender',
            $user->cnib_photo,
        );
        $recipientPhoto = $this->storeCnibPhoto($attributes['recipient_cnib_photo'] ?? null, 'recipient');
        $parcelPhoto = $this->storeParcelPhoto($attributes['parcel_photo'] ?? null);

        $deliveryKm = isset($attributes['delivery_distance_km']) ? (float) $attributes['delivery_distance_km'] : null;
        $declaredValue = isset($attributes['declared_value']) ? (float) $attributes['declared_value'] : null;
        $description = trim((string) ($attributes['parcel_description'] ?? ''));
        $weightKg = isset($attributes['estimated_weight_kg']) ? (float) $attributes['estimated_weight_kg'] : null;

        if ($description === '') {
            throw ValidationException::withMessages([
                'parcel_description' => 'La description du colis est obligatoire.',
            ]);
        }

        if ($declaredValue === null || $declaredValue < 0) {
            throw ValidationException::withMessages([
                'declared_value' => 'La valeur déclarée du colis est obligatoire.',
            ]);
        }

        if ($weightKg === null || $weightKg <= 0) {
            throw ValidationException::withMessages([
                'estimated_weight_kg' => 'Le poids du colis est obligatoire.',
            ]);
        }

        $trustedUuid = trim((string) ($attributes['trusted_payment_uuid'] ?? ''));
        $trustedPayment = null;
        if ($trustedUuid !== '') {
            $trustedPayment = app(TrustedPaymentService::class)->findLinkableForBuyer((int) $user->id, $trustedUuid);
            $paymentNetworkId = (int) $trustedPayment->payment_network_id;
        } else {
            $paymentNetworkId = (int) ($attributes['payment_network_id'] ?? 0);
        }

        $this->transactions->assertReceivesPayments($paymentNetworkId, 'payment_network_id');

        $quote = $this->quote(
            $route?->id,
            $mode,
            $paymentNetworkId,
            $deliveryKm,
            $declaredValue,
            $scope,
            $attributes['pickup_address'] ?? null,
            $originCity,
        );

        return DB::transaction(function () use (
            $user,
            $attributes,
            $mode,
            $scope,
            $route,
            $originCity,
            $destinationCity,
            $paymentNetworkId,
            $senderPhone,
            $recipientPhone,
            $quote,
            $deliveryKm,
            $senderCnib,
            $recipientCnib,
            $senderPhoto,
            $recipientPhoto,
            $parcelPhoto,
            $declaredValue,
            $description,
            $weightKg,
            $trustedPayment,
        ) {
            $reference = $this->generateReference();
            $corridorLabel = $scope === ParcelScopeEnum::Local
                ? $originCity.' (local)'
                : $originCity.' → '.$destinationCity;

            $transaction = $this->transactions->createParcelShipment([
                'user_id' => $user->id,
                'amount' => $quote->serviceAmount(),
                'network_fee' => $quote->networkFee,
                'platform_fee' => $quote->platformFee,
                'payment_network_id' => $paymentNetworkId,
                'travel_company_route_id' => $route?->id,
                'sender_phone' => $senderPhone,
                'recipient_phone' => $recipientPhone,
                'recipient_name' => $attributes['recipient_name'],
                'description' => sprintf('Colis %s (%s)', $corridorLabel, $mode->label()),
                'payment_expires_at' => now()->addMinutes(30),
                'reference' => $reference,
            ]);

            $shipment = ParcelShipment::query()->create([
                'reference' => $reference,
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'travel_company_id' => $route?->travel_company_id,
                'travel_company_route_id' => $route?->id,
                'scope' => $scope,
                'origin_city' => $originCity,
                'destination_city' => $destinationCity,
                'delivery_mode' => $mode,
                'status' => ParcelStatusEnum::PendingPayment,
                'sender_name' => $attributes['sender_name'] ?? $user->name,
                'sender_phone' => $senderPhone,
                'sender_cnib_number' => $senderCnib,
                'sender_cnib_photo' => $senderPhoto,
                'pickup_address' => $attributes['pickup_address'],
                'pickup_district' => $attributes['pickup_district'] ?? null,
                'recipient_name' => $attributes['recipient_name'],
                'recipient_phone' => $recipientPhone,
                'recipient_cnib_number' => $recipientCnib,
                'recipient_cnib_photo' => $recipientPhoto,
                'recipient_address' => $mode === ParcelDeliveryModeEnum::DoorDelivery
                    ? ($attributes['recipient_address'] ?? null)
                    : null,
                'recipient_district' => $attributes['recipient_district'] ?? null,
                'parcel_description' => $description,
                'parcel_photo' => $parcelPhoto,
                'estimated_weight_kg' => $weightKg,
                'declared_value' => $declaredValue,
                'agency_fee' => $quote->agencyFee,
                'margin_amount' => $quote->marginAmount,
                'value_fee' => $quote->valueFee,
                'base_amount' => $quote->baseAmount,
                'pickup_fee' => $quote->pickupFee,
                'pickup_distance_km' => $quote->pickupDistanceKm,
                'pickup_distance_fee' => $quote->pickupDistanceFee,
                'delivery_fee' => $quote->deliveryFee,
                'delivery_distance_km' => $mode === ParcelDeliveryModeEnum::DoorDelivery ? $deliveryKm : null,
                'delivery_distance_fee' => $quote->deliveryDistanceFee,
                'network_fee' => $quote->networkFee,
                'platform_fee' => $quote->platformFee,
                'total_amount' => $quote->totalAmount(),
                'pickup_code' => $this->generateCode(),
                'delivery_code' => $mode === ParcelDeliveryModeEnum::DoorDelivery ? $this->generateCode() : null,
                'station_pickup_code' => $mode === ParcelDeliveryModeEnum::StationPickup ? $this->generateCode() : null,
            ]);

            $this->recordEvent($shipment, ParcelStatusEnum::PendingPayment, 'Commande créée, en attente de paiement.', 'system');

            if ($trustedPayment) {
                $shipment->trusted_payment_id = $trustedPayment->id;
                $shipment->save();

                $this->transactions->markPaymentReceived($transaction, $trustedPayment->reference);
                $this->confirmAfterPayment($transaction->fresh() ?? $transaction);
                app(TrustedPaymentService::class)->onLinkedToParcel(
                    $trustedPayment->fresh() ?? $trustedPayment,
                    $shipment->fresh() ?? $shipment,
                );
            }

            return ($shipment->fresh([
                'route.travelCompany',
                'transaction.paymentNetwork',
                'trustedPayment',
                'events',
            ]) ?? $shipment);
        });
    }

    public function listForUser(int $userId): Collection
    {
        return ParcelShipment::query()
            ->with(['route.travelCompany', 'transaction.paymentNetwork', 'trustedPayment'])
            ->where('user_id', $userId)
            ->latest('id')
            ->get();
    }

    public function findForUser(int $userId, string $uuid): ?ParcelShipment
    {
        return ParcelShipment::query()
            ->with(['route.travelCompany', 'transaction.paymentNetwork', 'trustedPayment', 'events'])
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function confirmAfterPayment(Transaction $transaction): void
    {
        if (! $transaction->isParcelShipment()) {
            return;
        }

        $shipment = ParcelShipment::query()
            ->where('transaction_id', $transaction->id)
            ->first();

        if (! $shipment || $shipment->status !== ParcelStatusEnum::PendingPayment) {
            return;
        }

        $this->transition($shipment, ParcelStatusEnum::Confirmed, 'Paiement reçu.', 'system');
    }

    public function transition(
        ParcelShipment $shipment,
        ParcelStatusEnum $next,
        ?string $note = null,
        string $actorType = 'admin',
        ?int $actorUserId = null,
        ?string $pickupCode = null,
    ): ParcelShipment {
        $current = $shipment->status instanceof ParcelStatusEnum
            ? $shipment->status
            : ParcelStatusEnum::from((string) $shipment->status);

        $mode = $shipment->delivery_mode instanceof ParcelDeliveryModeEnum
            ? $shipment->delivery_mode
            : ParcelDeliveryModeEnum::from((string) $shipment->delivery_mode);

        $scope = $shipment->scope instanceof ParcelScopeEnum
            ? $shipment->scope
            : ParcelScopeEnum::from((string) ($shipment->scope ?? ParcelScopeEnum::Intercity->value));

        if ($current->isFinal()) {
            throw ValidationException::withMessages([
                'status' => 'Cet envoi est déjà terminé.',
            ]);
        }

        if (! in_array($next, $current->allowedNext($mode, $scope), true)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Transition invalide : %s → %s.',
                    $current->label(),
                    $next->label(),
                ),
            ]);
        }

        if ($next === ParcelStatusEnum::Collected) {
            $this->assertEscrowUnlockCode($shipment, $pickupCode);
        }

        $shipment->status = $next;

        match ($next) {
            ParcelStatusEnum::Collected => $shipment->collected_at = now(),
            ParcelStatusEnum::Shipped => $shipment->departed_at = now(),
            ParcelStatusEnum::ArrivedStation => $shipment->arrived_at = now(),
            ParcelStatusEnum::Delivered, ParcelStatusEnum::PickedUp => $shipment->delivered_at = now(),
            default => null,
        };

        $shipment->save();

        $this->recordEvent($shipment, $next, $note, $actorType, $actorUserId);

        if ($next === ParcelStatusEnum::Collected) {
            $shipment->loadMissing('trustedPayment');
            if ($shipment->trustedPayment) {
                app(TrustedPaymentService::class)->releaseOnParcelCollected(
                    $shipment->trustedPayment,
                    $shipment,
                );
            }
        }

        if (in_array($next, [ParcelStatusEnum::Delivered, ParcelStatusEnum::PickedUp], true) && $shipment->transaction_id) {
            $tx = Transaction::query()->find($shipment->transaction_id);
            if ($tx && ! $tx->isServed()) {
                $this->transactions->markServiceDelivered($tx, 'PC-'.$shipment->reference);
            }
        }

        if ($next === ParcelStatusEnum::Cancelled && $shipment->transaction_id) {
            $tx = Transaction::query()->find($shipment->transaction_id);
            if ($tx) {
                $this->transactions->markCancelled($tx, $note ?? 'Envoi colis annulé.');
            }
        }

        if ($next === ParcelStatusEnum::Failed && $shipment->transaction_id) {
            $tx = Transaction::query()->find($shipment->transaction_id);
            if ($tx && ! $tx->isServed()) {
                $this->transactions->markServiceFailed($tx, $note ?? 'Envoi colis échoué.');
            }
        }

        return $shipment->fresh(['route.travelCompany', 'transaction.paymentNetwork', 'trustedPayment', 'events']) ?? $shipment;
    }

    protected function assertEscrowUnlockCode(ParcelShipment $shipment, ?string $pickupCode): void
    {
        $shipment->loadMissing('trustedPayment');
        $escrow = $shipment->trustedPayment;
        if (! $escrow) {
            return;
        }

        $status = $escrow->status instanceof TrustedPaymentStatusEnum
            ? $escrow->status
            : TrustedPaymentStatusEnum::tryFrom((string) $escrow->status);

        if (! in_array($status, [
            TrustedPaymentStatusEnum::FundsHeld,
            TrustedPaymentStatusEnum::ExpeditionRequested,
            TrustedPaymentStatusEnum::CourierEnRoute,
        ], true)) {
            return;
        }

        $expected = (string) $shipment->pickup_code;
        $given = trim((string) $pickupCode);
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            throw ValidationException::withMessages([
                'pickup_code' => 'Le code de collecte est obligatoire pour débloquer le paiement confiant.',
            ]);
        }
    }

    protected function computeAmount(ParcelFeeModeEnum|string $mode, string $value, string $reference): string
    {
        $mode = $mode instanceof ParcelFeeModeEnum ? $mode : ParcelFeeModeEnum::from($mode);
        $value = number_format((float) $value, 4, '.', '');

        if ((float) $value <= 0) {
            return '0.00';
        }

        if ($mode === ParcelFeeModeEnum::Percentage) {
            $computed = bcmul($reference, bcdiv($value, '100', 8), 4);

            return number_format((float) round((float) $computed), 2, '.', '');
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function distanceFee(string $perKm, ?float $distanceKm): string
    {
        if ((float) $perKm <= 0 || $distanceKm === null || $distanceKm <= 0) {
            return '0.00';
        }

        $computed = bcmul($perKm, number_format($distanceKm, 4, '.', ''), 4);

        return number_format((float) round((float) $computed), 2, '.', '');
    }

    protected function assertDistances(
        ParcelPricingSetting $settings,
        ParcelDeliveryModeEnum $mode,
        ?float $pickupDistanceKm,
        ?float $deliveryDistanceKm,
    ): void {
        $errors = [];

        if ((float) $settings->pickup_per_km > 0 && $pickupDistanceKm === null) {
            $errors['pickup_address'] = 'Le lien Google Maps de collecte est obligatoire pour calculer les frais au km.';
        }

        if (
            $mode === ParcelDeliveryModeEnum::DoorDelivery
            && (float) $settings->delivery_per_km > 0
            && ($deliveryDistanceKm === null || $deliveryDistanceKm <= 0)
        ) {
            $errors['delivery_distance_km'] = 'La distance de livraison (km) est obligatoire pour calculer les frais au km.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function storeParcelPhoto(mixed $file): string
    {
        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            throw ValidationException::withMessages([
                'parcel_photo' => 'La photo du colis est obligatoire (image).',
            ]);
        }

        $path = $file->store('parcels/photos', 'public');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'parcel_photo' => 'Impossible d’enregistrer la photo du colis.',
            ]);
        }

        return $path;
    }

    protected function resolvePickupDistanceKm(
        ?string $pickupAddress,
        ?TravelCompanyRoute $route,
        ParcelScopeEnum $scope,
        ?string $originCity,
    ): ?float {
        $address = trim((string) $pickupAddress);
        if ($address === '') {
            return null;
        }

        $pickup = GoogleMapsExtractor::extractCoordinates($address);
        if ($pickup === null) {
            throw ValidationException::withMessages([
                'pickup_address' => 'Impossible d’extraire les coordonnées du lien Google Maps de collecte.',
            ]);
        }

        $origin = $this->pickupOriginCoordinates($route, $scope, (string) $originCity);

        return $this->mapDirections->drivingDistanceKm($origin, $pickup);
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    protected function pickupOriginCoordinates(
        ?TravelCompanyRoute $route,
        ParcelScopeEnum $scope,
        string $originCity,
    ): array {
        if ($scope === ParcelScopeEnum::Intercity && $route !== null) {
            $route->loadMissing('travelCompany');
            $companyLink = trim((string) ($route->travelCompany?->google_maps_link ?? ''));
            if ($companyLink !== '') {
                $fromCompany = GoogleMapsExtractor::extractCoordinates($companyLink);
                if ($fromCompany !== null) {
                    return [
                        'latitude' => $fromCompany['latitude'],
                        'longitude' => $fromCompany['longitude'],
                    ];
                }
            }

            $station = TravelCompanyStation::query()
                ->where('travel_company_id', $route->travel_company_id)
                ->where('is_active', true)
                ->whereNotNull('google_maps_link')
                ->where('google_maps_link', '!=', '')
                ->where(function ($query) use ($route): void {
                    $departure = (string) $route->departure;
                    $query->where('station_name', 'like', '%'.$departure.'%')
                        ->orWhere('address', 'like', '%'.$departure.'%');
                })
                ->first();

            if ($station) {
                $fromStation = GoogleMapsExtractor::extractCoordinates((string) $station->google_maps_link);
                if ($fromStation !== null) {
                    return [
                        'latitude' => $fromStation['latitude'],
                        'longitude' => $fromStation['longitude'],
                    ];
                }
            }

            $geocoded = $this->mapDirections->geocode($route->departure.', Burkina Faso');
            if ($geocoded !== null) {
                return $geocoded;
            }
        }

        $city = $originCity !== '' ? $originCity : (string) ($route?->departure ?? '');
        $geocodedCity = $this->mapDirections->geocode($city.', Burkina Faso');
        if ($geocodedCity !== null) {
            return $geocodedCity;
        }

        return [
            'latitude' => (float) config('services.mapbox.default_origin_lat'),
            'longitude' => (float) config('services.mapbox.default_origin_lng'),
        ];
    }

    protected function storeCnibPhoto(mixed $file, string $role, ?string $profilePath = null): string
    {
        if ($file instanceof UploadedFile) {
            $path = $file->store('parcels/cnib/'.$role, 'public');

            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    $role === 'sender' ? 'sender_cnib_photo' : 'recipient_cnib_photo' => 'Impossible d’enregistrer la photo CNIB.',
                ]);
            }

            return $path;
        }

        if ($role === 'sender' && filled($profilePath) && Storage::disk('public')->exists($profilePath)) {
            $extension = pathinfo($profilePath, PATHINFO_EXTENSION) ?: 'jpg';
            $destination = 'parcels/cnib/sender/'.Str::uuid().'.'.$extension;
            Storage::disk('public')->copy($profilePath, $destination);

            return $destination;
        }

        throw ValidationException::withMessages([
            $role === 'sender' ? 'sender_cnib_photo' : 'recipient_cnib_photo' => 'La photo CNIB est obligatoire (image).',
        ]);
    }

    protected function routeReferenceAmount(TravelCompanyRoute $route): string
    {
        if ($route->price !== null && (float) $route->price > 0) {
            return number_format((float) $route->price, 2, '.', '');
        }

        return '0.00';
    }

    protected function recordEvent(
        ParcelShipment $shipment,
        ParcelStatusEnum $status,
        ?string $note,
        string $actorType,
        ?int $actorUserId = null,
    ): void {
        ParcelEvent::query()->create([
            'parcel_shipment_id' => $shipment->id,
            'status' => $status,
            'note' => $note,
            'actor_type' => $actorType,
            'actor_user_id' => $actorUserId,
        ]);
    }

    protected function assertActiveRoute(int $routeId): TravelCompanyRoute
    {
        $route = TravelCompanyRoute::query()->with('travelCompany')->find($routeId);

        if (! $route || ! $route->is_active || ! $route->accepts_parcels) {
            throw ValidationException::withMessages([
                'travel_company_route_id' => 'Ce corridor n’est pas disponible pour les colis.',
            ]);
        }

        return $route;
    }

    protected function generateReference(): string
    {
        return sprintf('PC-%s-%s', now()->format('Ymd'), strtoupper(Str::random(8)));
    }

    protected function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
