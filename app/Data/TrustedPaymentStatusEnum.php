<?php

namespace App\Data;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TrustedPaymentStatusEnum: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case FundsHeld = 'funds_held';
    case ExpeditionRequested = 'expedition_requested';
    case CourierEnRoute = 'courier_en_route';
    case Collected = 'collected';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Paiement en attente',
            self::FundsHeld => 'Fonds bloqués',
            self::ExpeditionRequested => 'Expédition demandée',
            self::CourierEnRoute => 'Coursier en route',
            self::Collected => 'Colis récupéré',
            self::InTransit => 'En transit',
            self::Arrived => 'Arrivé à destination',
            self::Delivered => 'Livré à l’acheteur',
            self::Cancelled => 'Annulé',
            self::Failed => 'Échoué',
        };
    }

    public function trackingLabel(): string
    {
        return $this->label();
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendingPayment => 'gray',
            self::FundsHeld => 'info',
            self::ExpeditionRequested, self::CourierEnRoute, self::Collected, self::InTransit => 'warning',
            self::Arrived => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'gray',
            self::Failed => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Failed], true);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function clientTrackingSteps(): array
    {
        return [
            ['key' => self::FundsHeld->value, 'label' => 'Fonds bloqués'],
            ['key' => self::ExpeditionRequested->value, 'label' => 'Expédition demandée'],
            ['key' => self::CourierEnRoute->value, 'label' => 'Coursier en route'],
            ['key' => self::Collected->value, 'label' => 'Colis récupéré / versement commerçant'],
            ['key' => self::InTransit->value, 'label' => 'En transit'],
            ['key' => self::Arrived->value, 'label' => 'Arrivé'],
            ['key' => self::Delivered->value, 'label' => 'Livré à l’acheteur'],
        ];
    }

    public function trackingStepIndex(): int
    {
        if (in_array($this, [self::PendingPayment, self::Cancelled, self::Failed], true)) {
            return -1;
        }

        $keys = array_column(self::clientTrackingSteps(), 'key');
        $index = array_search($this->value, $keys, true);

        return $index === false ? -1 : $index;
    }

    /**
     * @return list<array{key: string, label: string, state: string}>
     */
    public function trackingTimeline(): array
    {
        $currentIndex = $this->trackingStepIndex();
        $completed = $this === self::Delivered;
        $steps = self::clientTrackingSteps();

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
    public function allowedNext(): array
    {
        return match ($this) {
            self::PendingPayment => [self::FundsHeld, self::Cancelled, self::Failed],
            self::FundsHeld => [self::ExpeditionRequested, self::Cancelled, self::Failed],
            self::ExpeditionRequested => [self::CourierEnRoute, self::Cancelled, self::Failed],
            self::CourierEnRoute => [self::Collected, self::Cancelled, self::Failed],
            self::Collected => [self::InTransit, self::Failed],
            self::InTransit => [self::Arrived, self::Failed],
            self::Arrived => [self::Delivered, self::Failed],
            default => [],
        };
    }
}
