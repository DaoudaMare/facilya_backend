<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ParcelStatusEnum: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case CourierEnRoute = 'courier_en_route';
    case Collected = 'collected';
    case Shipped = 'shipped';
    case ArrivedStation = 'arrived_station';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Paiement en attente',
            self::Confirmed => 'Accepté',
            self::CourierEnRoute => 'Coursier en route pour récupérer',
            self::Collected => 'Récupéré',
            self::Shipped => 'Expédié',
            self::ArrivedStation => 'Arrivé',
            self::OutForDelivery => 'En livraison',
            self::Delivered => 'Remis au destinataire',
            self::PickedUp => 'Remis au destinataire',
            self::Cancelled => 'Annulé',
            self::Failed => 'Échoué',
        };
    }

    /**
     * Statut de suivi client.
     */
    public function trackingLabel(): ?string
    {
        return match ($this) {
            self::PendingPayment => 'Paiement en attente',
            self::Confirmed => 'Accepté',
            self::CourierEnRoute => 'Coursier en route pour récupérer',
            self::Collected => 'Récupéré',
            self::Shipped => 'Expédié',
            self::ArrivedStation => 'Arrivé',
            self::OutForDelivery => 'En livraison',
            self::Delivered, self::PickedUp => 'Remis au destinataire',
            self::Cancelled => 'Annulé',
            self::Failed => 'Échoué',
        };
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendingPayment => 'gray',
            self::Confirmed => 'info',
            self::CourierEnRoute, self::Collected, self::Shipped, self::OutForDelivery => 'warning',
            self::ArrivedStation => 'primary',
            self::Delivered, self::PickedUp => 'success',
            self::Cancelled => 'gray',
            self::Failed => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::PickedUp,
            self::Cancelled,
            self::Failed,
        ], true);
    }

    public function isTrackingStep(): bool
    {
        return in_array($this, [
            self::Confirmed,
            self::CourierEnRoute,
            self::Collected,
            self::Shipped,
            self::ArrivedStation,
            self::OutForDelivery,
            self::Delivered,
            self::PickedUp,
        ], true);
    }

    /**
     * Étapes visibles sur le trajet client (après paiement).
     *
     * @return list<array{key: string, label: string}>
     */
    public static function clientTrackingSteps(
        ParcelDeliveryModeEnum $mode,
        ?ParcelScopeEnum $scope = null,
    ): array {
        $scope ??= ParcelScopeEnum::Intercity;

        $final = $mode === ParcelDeliveryModeEnum::DoorDelivery
            ? ['key' => self::Delivered->value, 'label' => 'Remis au destinataire']
            : ['key' => self::PickedUp->value, 'label' => 'Remis au destinataire'];

        if ($scope === ParcelScopeEnum::Local) {
            return [
                ['key' => self::Confirmed->value, 'label' => 'Accepté'],
                ['key' => self::CourierEnRoute->value, 'label' => 'Coursier en route pour récupérer'],
                ['key' => self::Collected->value, 'label' => 'Récupéré'],
                ['key' => self::OutForDelivery->value, 'label' => 'En livraison'],
                $final,
            ];
        }

        $steps = [
            ['key' => self::Confirmed->value, 'label' => 'Accepté'],
            ['key' => self::CourierEnRoute->value, 'label' => 'Coursier en route pour récupérer'],
            ['key' => self::Collected->value, 'label' => 'Récupéré'],
            ['key' => self::Shipped->value, 'label' => 'Expédié'],
            ['key' => self::ArrivedStation->value, 'label' => 'Arrivé'],
        ];

        if ($mode === ParcelDeliveryModeEnum::DoorDelivery) {
            $steps[] = ['key' => self::OutForDelivery->value, 'label' => 'En livraison'];
        }

        $steps[] = $final;

        return $steps;
    }

    /**
     * Index de l’étape courante dans clientTrackingSteps (-1 si hors parcours).
     */
    public function trackingStepIndex(
        ParcelDeliveryModeEnum $mode,
        ?ParcelScopeEnum $scope = null,
    ): int {
        $keys = array_column(self::clientTrackingSteps($mode, $scope), 'key');

        if ($this === self::PendingPayment) {
            return -1;
        }

        if ($this === self::Cancelled || $this === self::Failed) {
            return -1;
        }

        $index = array_search($this->value, $keys, true);

        return $index === false ? -1 : $index;
    }

    /**
     * @return list<array{key: string, label: string, state: string}>
     */
    public function trackingTimeline(
        ParcelDeliveryModeEnum $mode,
        ?ParcelScopeEnum $scope = null,
    ): array {
        $currentIndex = $this->trackingStepIndex($mode, $scope);
        $steps = self::clientTrackingSteps($mode, $scope);
        $completed = $this->isFinal() && ! in_array($this, [self::Cancelled, self::Failed], true);

        return array_map(function (array $step, int $index) use ($currentIndex, $completed) {
            $state = 'upcoming';
            if ($currentIndex >= 0) {
                if ($completed || $index < $currentIndex) {
                    $state = 'done';
                } elseif ($index === $currentIndex) {
                    $state = 'current';
                }
            }

            return [
                'key' => $step['key'],
                'label' => $step['label'],
                'state' => $state,
            ];
        }, $steps, array_keys($steps));
    }

    /**
     * @return list<self>
     */
    public function allowedNext(
        ParcelDeliveryModeEnum $mode,
        ?ParcelScopeEnum $scope = null,
    ): array {
        $scope ??= ParcelScopeEnum::Intercity;

        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Cancelled, self::Failed],
            self::Confirmed => [self::CourierEnRoute, self::Cancelled, self::Failed],
            self::CourierEnRoute => [self::Collected, self::Cancelled, self::Failed],
            self::Collected => $scope === ParcelScopeEnum::Local
                ? [self::OutForDelivery, self::Failed]
                : [self::Shipped, self::Failed],
            self::Shipped => [self::ArrivedStation, self::Failed],
            self::ArrivedStation => $mode === ParcelDeliveryModeEnum::DoorDelivery
                ? [self::OutForDelivery, self::Failed]
                : [self::PickedUp, self::Failed],
            self::OutForDelivery => [self::Delivered, self::Failed],
            default => [],
        };
    }
}
