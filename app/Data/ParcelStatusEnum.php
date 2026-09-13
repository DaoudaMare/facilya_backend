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
            self::CourierEnRoute, self::Collected, self::Shipped => 'warning',
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
            self::Delivered,
            self::PickedUp,
        ], true);
    }

    /**
     * Étapes visibles sur le trajet client (après paiement).
     *
     * @return list<array{key: string, label: string}>
     */
    public static function clientTrackingSteps(ParcelDeliveryModeEnum $mode): array
    {
        $final = $mode === ParcelDeliveryModeEnum::DoorDelivery
            ? ['key' => self::Delivered->value, 'label' => 'Remis au destinataire']
            : ['key' => self::PickedUp->value, 'label' => 'Remis au destinataire'];

        return [
            ['key' => self::Confirmed->value, 'label' => 'Accepté'],
            ['key' => self::CourierEnRoute->value, 'label' => 'Coursier en route pour récupérer'],
            ['key' => self::Collected->value, 'label' => 'Récupéré'],
            ['key' => self::Shipped->value, 'label' => 'Expédié'],
            ['key' => self::ArrivedStation->value, 'label' => 'Arrivé'],
            $final,
        ];
    }

    /**
     * Index de l’étape courante dans clientTrackingSteps (-1 si hors parcours).
     */
    public function trackingStepIndex(ParcelDeliveryModeEnum $mode): int
    {
        $keys = array_column(self::clientTrackingSteps($mode), 'key');

        if ($this === self::PendingPayment) {
            return -1;
        }

        if ($this === self::Cancelled || $this === self::Failed) {
            return -1;
        }

        $key = $this->value;
        $index = array_search($key, $keys, true);

        return $index === false ? -1 : $index;
    }

    /**
     * @return list<array{key: string, label: string, state: string}>
     */
    public function trackingTimeline(ParcelDeliveryModeEnum $mode): array
    {
        $currentIndex = $this->trackingStepIndex($mode);
        $steps = self::clientTrackingSteps($mode);
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
    public function allowedNext(ParcelDeliveryModeEnum $mode): array
    {
        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Cancelled, self::Failed],
            self::Confirmed => [self::CourierEnRoute, self::Cancelled, self::Failed],
            self::CourierEnRoute => [self::Collected, self::Cancelled, self::Failed],
            self::Collected => [self::Shipped, self::Failed],
            self::Shipped => [self::ArrivedStation, self::Failed],
            self::ArrivedStation => $mode === ParcelDeliveryModeEnum::DoorDelivery
                ? [self::Delivered, self::Failed]
                : [self::PickedUp, self::Failed],
            default => [],
        };
    }
}
