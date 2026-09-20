<?php

namespace App\Filament\Resources\Transactions\Support;

use App\Filament\Resources\ParcelShipments\Support\ParcelShipmentStatusActions;
use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelStatusEnum;
use App\Data\PaymentStatusEnum;
use App\Data\TrustedPaymentStatusEnum;
use App\Models\ParcelShipment;
use App\Models\Transaction;
use App\Models\TrustedPayment;
use App\Models\User;
use App\Services\ParcelShipmentService;
use App\Services\TransactionService;
use App\Services\TrustedPaymentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TransactionStatusActions
{
    /**
     * @return list<Action>
     */
    public static function headerActionsFor(Transaction $transaction): array
    {
        if (! self::userCanManage($transaction)) {
            return [];
        }

        if ($transaction->isParcelShipment()) {
            return self::parcelActions($transaction, forTable: false);
        }

        if ($transaction->isTrustedPayment()) {
            return self::trustedActions($transaction, forTable: false);
        }

        return self::simpleServiceActions($transaction, forTable: false);
    }

    public static function tableActionGroup(): ActionGroup
    {
        $actions = [
            self::makePaymentReceivedAction()
                ->visible(fn (Transaction $record): bool => self::userCanManage($record) && self::canMarkPaymentReceived($record)),
            self::makeServiceDeliveredAction()
                ->visible(fn (Transaction $record): bool => self::userCanManage($record) && self::canMarkServiceDelivered($record)),
            self::makeAcceptExpeditionAction()
                ->visible(fn (Transaction $record): bool => self::userCanManage($record) && self::canAcceptExpedition($record)),
        ];

        foreach (self::parcelTransitionTargets() as $status) {
            $actions[] = self::makeParcelTransitionAction($status)
                ->visible(fn (Transaction $record): bool => self::userCanManage($record) && self::canTransitionParcelTo($record, $status));
        }

        foreach (self::trustedTransitionTargets() as $status) {
            $actions[] = self::makeTrustedTransitionAction($status)
                ->visible(fn (Transaction $record): bool => self::userCanManage($record) && self::canTransitionTrustedTo($record, $status));
        }

        $actions[] = self::makeTrustedRetryPayoutAction()
            ->visible(fn (Transaction $record): bool => self::userCanManage($record) && self::canRetryTrustedPayout($record));

        return ActionGroup::make($actions)
            ->label('Statut')
            ->icon('heroicon-o-arrow-path')
            ->button()
            ->color('primary')
            ->visible(fn (Transaction $record): bool => self::userCanManage($record) && self::hasAnyAction($record));
    }

    public static function nextStepHint(Transaction $transaction): ?string
    {
        if ($transaction->isParcelShipment()) {
            if (self::canMarkPaymentReceived($transaction)) {
                return 'Suivant : Paiement reçu';
            }
            if (self::canAcceptExpedition($transaction)) {
                return 'Suivant : Expédition acceptée';
            }

            $shipment = self::parcelOf($transaction);
            if (! $shipment) {
                return null;
            }

            foreach (self::allowedParcelNext($shipment) as $next) {
                if (! in_array($next, [ParcelStatusEnum::Cancelled, ParcelStatusEnum::Failed], true)) {
                    return 'Suivant : '.self::parcelAdminLabel($next, $shipment);
                }
            }

            return null;
        }

        if ($transaction->isTrustedPayment()) {
            if (self::canMarkPaymentReceived($transaction)) {
                return 'Suivant : Paiement reçu';
            }

            $payment = self::trustedOf($transaction);
            if (! $payment) {
                return null;
            }

            foreach (self::allowedTrustedNext($payment) as $next) {
                if (! in_array($next, [TrustedPaymentStatusEnum::Cancelled, TrustedPaymentStatusEnum::Failed], true)) {
                    return 'Suivant : '.$next->label();
                }
            }

            if (self::canRetryTrustedPayout($transaction)) {
                return 'Suivant : Relancer le versement';
            }

            return null;
        }

        if (self::canMarkPaymentReceived($transaction)) {
            return 'Suivant : Paiement reçu';
        }
        if (self::canMarkServiceDelivered($transaction)) {
            return 'Suivant : Service livré';
        }

        return null;
    }

    /**
     * @return list<Action>
     */
    protected static function simpleServiceActions(Transaction $transaction, bool $forTable): array
    {
        $actions = [];

        if (self::canMarkPaymentReceived($transaction)) {
            $actions[] = self::makePaymentReceivedAction();
        }

        if (self::canMarkServiceDelivered($transaction)) {
            $actions[] = self::makeServiceDeliveredAction();
        }

        return $actions;
    }

    /**
     * @return list<Action>
     */
    protected static function parcelActions(Transaction $transaction, bool $forTable): array
    {
        $actions = [];

        if (self::canMarkPaymentReceived($transaction)) {
            $actions[] = self::makePaymentReceivedAction();
        }

        if (self::canAcceptExpedition($transaction)) {
            $actions[] = self::makeAcceptExpeditionAction();
        }

        $shipment = self::parcelOf($transaction);
        if ($shipment) {
            foreach (self::allowedParcelNext($shipment) as $next) {
                if (in_array($next, [ParcelStatusEnum::Cancelled, ParcelStatusEnum::Failed], true)) {
                    continue;
                }
                $actions[] = self::makeParcelTransitionAction($next, $shipment);
            }

            foreach (self::allowedParcelNext($shipment) as $next) {
                if (in_array($next, [ParcelStatusEnum::Cancelled, ParcelStatusEnum::Failed], true)) {
                    $actions[] = self::makeParcelTransitionAction($next, $shipment);
                }
            }
        }

        return $actions;
    }

    /**
     * @return list<Action>
     */
    protected static function trustedActions(Transaction $transaction, bool $forTable): array
    {
        $actions = [];

        if (self::canMarkPaymentReceived($transaction)) {
            $actions[] = self::makePaymentReceivedAction();
        }

        $payment = self::trustedOf($transaction);
        if ($payment) {
            foreach (self::allowedTrustedNext($payment) as $next) {
                if (in_array($next, [TrustedPaymentStatusEnum::Cancelled, TrustedPaymentStatusEnum::Failed], true)) {
                    continue;
                }
                $actions[] = self::makeTrustedTransitionAction($next, $payment);
            }

            foreach (self::allowedTrustedNext($payment) as $next) {
                if (in_array($next, [TrustedPaymentStatusEnum::Cancelled, TrustedPaymentStatusEnum::Failed], true)) {
                    $actions[] = self::makeTrustedTransitionAction($next, $payment);
                }
            }

            if (self::canRetryTrustedPayout($transaction)) {
                $actions[] = self::makeTrustedRetryPayoutAction();
            }
        }

        return $actions;
    }

    protected static function makePaymentReceivedAction(): Action
    {
        return Action::make('mark_payment_received')
            ->label('Paiement reçu')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Confirmer le paiement reçu')
            ->action(function (Transaction $record): void {
                app(TransactionService::class)->markPaymentReceived($record);
                Notification::make()->title('Paiement marqué comme reçu')->success()->send();
            });
    }

    protected static function makeServiceDeliveredAction(): Action
    {
        return Action::make('mark_service_delivered')
            ->label('Service livré')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Confirmer le service livré')
            ->action(function (Transaction $record): void {
                app(TransactionService::class)->markServiceDelivered($record);
                Notification::make()->title('Service marqué comme livré')->success()->send();
            });
    }

    protected static function makeAcceptExpeditionAction(): Action
    {
        return Action::make('accept_expedition')
            ->label('Expédition acceptée')
            ->icon('heroicon-o-check-circle')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Accepter l’expédition')
            ->modalDescription('Le colis passe au statut « Accepté » et le suivi client démarre.')
            ->action(function (Transaction $record): void {
                if (! $record->isPaid()) {
                    Notification::make()
                        ->title('Paiement requis')
                        ->body('Marquez d’abord le paiement comme reçu.')
                        ->danger()
                        ->send();

                    return;
                }

                app(ParcelShipmentService::class)->confirmAfterPayment($record->fresh() ?? $record);
                Notification::make()->title('Expédition acceptée')->success()->send();
            });
    }

    protected static function makeParcelTransitionAction(
        ParcelStatusEnum $next,
        ?ParcelShipment $shipment = null,
    ): Action {
        return Action::make('parcel_to_'.$next->value)
            ->label(fn (Transaction $record): string => self::parcelAdminLabel(
                $next,
                self::parcelOf($record) ?? $shipment,
            ))
            ->icon(match ($next) {
                ParcelStatusEnum::CourierEnRoute => 'heroicon-o-truck',
                ParcelStatusEnum::Collected => 'heroicon-o-archive-box',
                ParcelStatusEnum::Shipped => 'heroicon-o-paper-airplane',
                ParcelStatusEnum::ArrivedStation => 'heroicon-o-building-storefront',
                ParcelStatusEnum::Delivered, ParcelStatusEnum::PickedUp => 'heroicon-o-check-badge',
                ParcelStatusEnum::Cancelled => 'heroicon-o-x-circle',
                ParcelStatusEnum::Failed => 'heroicon-o-exclamation-triangle',
                default => 'heroicon-o-arrow-right-circle',
            })
            ->color(match ($next) {
                ParcelStatusEnum::Cancelled => 'gray',
                ParcelStatusEnum::Failed => 'danger',
                ParcelStatusEnum::Delivered, ParcelStatusEnum::PickedUp => 'success',
                default => 'primary',
            })
            ->requiresConfirmation()
            ->modalHeading(fn (Transaction $record): string => 'Passer à : '.self::parcelAdminLabel(
                $next,
                self::parcelOf($record),
            ))
            ->form(function (Transaction $record) use ($next): array {
                $fields = [];
                $parcel = self::parcelOf($record);
                if ($next === ParcelStatusEnum::Collected && $parcel && ParcelShipmentStatusActions::needsEscrowUnlock($parcel)) {
                    $fields[] = TextInput::make('pickup_code')
                        ->label('Code de validation')
                        ->helperText('Code à 6 chiffres du paiement confiant.')
                        ->required()
                        ->maxLength(6);
                }
                $fields[] = Textarea::make('note')
                    ->label('Note (optionnel)')
                    ->rows(2)
                    ->maxLength(255);

                return $fields;
            })
            ->action(function (Transaction $record, array $data) use ($next): void {
                $parcel = self::parcelOf($record);
                if (! $parcel) {
                    Notification::make()->title('Colis introuvable')->danger()->send();

                    return;
                }

                try {
                    app(ParcelShipmentService::class)->transition(
                        $parcel,
                        $next,
                        $data['note'] ?? null,
                        'admin',
                        auth()->id(),
                        isset($data['pickup_code']) ? (string) $data['pickup_code'] : null,
                    );
                    Notification::make()
                        ->title('Statut colis mis à jour')
                        ->body(self::parcelAdminLabel($next, $parcel))
                        ->success()
                        ->send();
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Transition refusée')
                        ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                        ->danger()
                        ->send();
                    throw $e;
                }
            });
    }

    protected static function makeTrustedTransitionAction(
        TrustedPaymentStatusEnum $next,
        ?TrustedPayment $payment = null,
    ): Action {
        return Action::make('trusted_to_'.$next->value)
            ->label($next->label())
            ->icon(match ($next) {
                TrustedPaymentStatusEnum::ExpeditionRequested => 'heroicon-o-clipboard-document-check',
                TrustedPaymentStatusEnum::CourierEnRoute => 'heroicon-o-truck',
                TrustedPaymentStatusEnum::Collected => 'heroicon-o-archive-box',
                TrustedPaymentStatusEnum::InTransit => 'heroicon-o-paper-airplane',
                TrustedPaymentStatusEnum::Arrived => 'heroicon-o-map-pin',
                TrustedPaymentStatusEnum::Delivered => 'heroicon-o-check-badge',
                TrustedPaymentStatusEnum::Cancelled => 'heroicon-o-x-circle',
                TrustedPaymentStatusEnum::Failed => 'heroicon-o-exclamation-triangle',
                default => 'heroicon-o-arrow-right-circle',
            })
            ->color(match ($next) {
                TrustedPaymentStatusEnum::Cancelled => 'gray',
                TrustedPaymentStatusEnum::Failed => 'danger',
                TrustedPaymentStatusEnum::Delivered => 'success',
                default => 'primary',
            })
            ->requiresConfirmation()
            ->modalHeading('Passer à : '.$next->label())
            ->form([
                Textarea::make('note')
                    ->label('Note (optionnel)')
                    ->rows(2)
                    ->maxLength(255),
            ])
            ->action(function (Transaction $record, array $data) use ($next): void {
                $trusted = self::trustedOf($record);
                if (! $trusted) {
                    Notification::make()->title('Paiement confiant introuvable')->danger()->send();

                    return;
                }

                try {
                    app(TrustedPaymentService::class)->transition(
                        $trusted,
                        $next,
                        $data['note'] ?? null,
                        'admin',
                        auth()->id(),
                    );
                    Notification::make()
                        ->title('Statut mis à jour')
                        ->body($next->label())
                        ->success()
                        ->send();
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Transition refusée')
                        ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                        ->danger()
                        ->send();
                    throw $e;
                }
            });
    }

    protected static function makeTrustedRetryPayoutAction(): Action
    {
        return Action::make('retry_trusted_payout')
            ->label('Relancer le versement')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (Transaction $record): void {
                $trusted = self::trustedOf($record);
                if (! $trusted) {
                    Notification::make()->title('Paiement confiant introuvable')->danger()->send();

                    return;
                }

                app(TrustedPaymentService::class)->releasePayout($trusted, forceRetry: true);
                Notification::make()->title('Versement relancé')->success()->send();
            });
    }

    protected static function canMarkPaymentReceived(Transaction $transaction): bool
    {
        return $transaction->payment_status !== PaymentStatusEnum::RECEIVED
            && ! in_array($transaction->payment_status, [
                PaymentStatusEnum::FAILED,
                PaymentStatusEnum::CANCELLED,
                PaymentStatusEnum::REFUNDED,
            ], true);
    }

    protected static function canMarkServiceDelivered(Transaction $transaction): bool
    {
        return ! $transaction->isParcelShipment()
            && ! $transaction->isTrustedPayment()
            && $transaction->isPaid()
            && ! $transaction->isServed();
    }

    protected static function canAcceptExpedition(Transaction $transaction): bool
    {
        if (! $transaction->isParcelShipment() || ! $transaction->isPaid()) {
            return false;
        }

        $parcel = self::parcelOf($transaction);

        return $parcel?->status === ParcelStatusEnum::PendingPayment;
    }

    protected static function canTransitionParcelTo(Transaction $transaction, ParcelStatusEnum $next): bool
    {
        $parcel = self::parcelOf($transaction);

        return $parcel !== null && in_array($next, self::allowedParcelNext($parcel), true);
    }

    protected static function canTransitionTrustedTo(Transaction $transaction, TrustedPaymentStatusEnum $next): bool
    {
        $payment = self::trustedOf($transaction);

        return $payment !== null && in_array($next, self::allowedTrustedNext($payment), true);
    }

    protected static function canRetryTrustedPayout(Transaction $transaction): bool
    {
        $payment = self::trustedOf($transaction);
        if (! $payment || ! $transaction->isTrustedPayment()) {
            return false;
        }

        return $payment->status === TrustedPaymentStatusEnum::Collected
            || $payment->payout_status?->value === 'failed'
            || (
                in_array($payment->status, [
                    TrustedPaymentStatusEnum::Collected,
                    TrustedPaymentStatusEnum::InTransit,
                    TrustedPaymentStatusEnum::Arrived,
                    TrustedPaymentStatusEnum::Delivered,
                ], true)
                && $payment->payout_status?->value !== 'sent'
            );
    }

    protected static function hasAnyAction(Transaction $transaction): bool
    {
        if (self::canMarkPaymentReceived($transaction) || self::canMarkServiceDelivered($transaction)) {
            return true;
        }

        if (self::canAcceptExpedition($transaction)) {
            return true;
        }

        if (self::canRetryTrustedPayout($transaction)) {
            return true;
        }

        $parcel = self::parcelOf($transaction);
        if ($parcel !== null && self::allowedParcelNext($parcel) !== []) {
            return true;
        }

        $trusted = self::trustedOf($transaction);

        return $trusted !== null && self::allowedTrustedNext($trusted) !== [];
    }

    /**
     * @return list<ParcelStatusEnum>
     */
    protected static function allowedParcelNext(ParcelShipment $shipment): array
    {
        $current = $shipment->status instanceof ParcelStatusEnum
            ? $shipment->status
            : ParcelStatusEnum::tryFrom((string) $shipment->status);

        $mode = $shipment->delivery_mode instanceof ParcelDeliveryModeEnum
            ? $shipment->delivery_mode
            : ParcelDeliveryModeEnum::tryFrom((string) $shipment->delivery_mode);

        if (! $current || ! $mode || $current->isFinal()) {
            return [];
        }

        return array_values(array_filter(
            $shipment->allowedNextStatuses(),
            fn (ParcelStatusEnum $status) => $status !== ParcelStatusEnum::Confirmed,
        ));
    }

    /**
     * @return list<TrustedPaymentStatusEnum>
     */
    protected static function allowedTrustedNext(TrustedPayment $payment): array
    {
        $current = $payment->status instanceof TrustedPaymentStatusEnum
            ? $payment->status
            : TrustedPaymentStatusEnum::tryFrom((string) $payment->status);

        if (! $current || $current->isFinal()) {
            return [];
        }

        return $current->allowedNext();
    }

    /**
     * @return list<ParcelStatusEnum>
     */
    protected static function parcelTransitionTargets(): array
    {
        return [
            ParcelStatusEnum::CourierEnRoute,
            ParcelStatusEnum::Collected,
            ParcelStatusEnum::Shipped,
            ParcelStatusEnum::ArrivedStation,
            ParcelStatusEnum::OutForDelivery,
            ParcelStatusEnum::Delivered,
            ParcelStatusEnum::PickedUp,
            ParcelStatusEnum::Cancelled,
            ParcelStatusEnum::Failed,
        ];
    }

    /**
     * @return list<TrustedPaymentStatusEnum>
     */
    protected static function trustedTransitionTargets(): array
    {
        return [
            TrustedPaymentStatusEnum::ExpeditionRequested,
            TrustedPaymentStatusEnum::CourierEnRoute,
            TrustedPaymentStatusEnum::Collected,
            TrustedPaymentStatusEnum::InTransit,
            TrustedPaymentStatusEnum::Arrived,
            TrustedPaymentStatusEnum::Delivered,
            TrustedPaymentStatusEnum::Cancelled,
            TrustedPaymentStatusEnum::Failed,
        ];
    }

    protected static function parcelAdminLabel(ParcelStatusEnum $status, ?ParcelShipment $shipment): string
    {
        return match ($status) {
            ParcelStatusEnum::Confirmed => 'Expédition acceptée',
            ParcelStatusEnum::CourierEnRoute => 'Coursier en route',
            ParcelStatusEnum::Collected => 'Colis récupéré',
            ParcelStatusEnum::Shipped => 'Colis expédié',
            ParcelStatusEnum::ArrivedStation => 'Colis arrivé à destination',
            ParcelStatusEnum::OutForDelivery => 'Colis en livraison',
            ParcelStatusEnum::Delivered => 'Colis livré au destinataire',
            ParcelStatusEnum::PickedUp => 'Colis remis au destinataire',
            ParcelStatusEnum::Cancelled => 'Annulé',
            ParcelStatusEnum::Failed => 'Échoué',
            default => $status->label(),
        };
    }

    protected static function parcelOf(Transaction $transaction): ?ParcelShipment
    {
        $transaction->loadMissing('parcelShipment');

        return $transaction->parcelShipment;
    }

    protected static function trustedOf(Transaction $transaction): ?TrustedPayment
    {
        $transaction->loadMissing('trustedPayment');

        return $transaction->trustedPayment;
    }

    protected static function userCanManage(Transaction $transaction): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if ($transaction->isParcelShipment()) {
            return $user->hasPermission('parcels.manage');
        }

        if ($transaction->isTrustedPayment()) {
            return $user->hasPermission('trusted_payments.manage');
        }

        return $user->hasPermission('transactions.manage');
    }
}
