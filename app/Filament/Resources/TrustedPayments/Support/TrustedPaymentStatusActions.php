<?php

namespace App\Filament\Resources\TrustedPayments\Support;

use App\Data\TrustedPaymentStatusEnum;
use App\Data\TrustedPayoutStatusEnum;
use App\Models\TrustedPayment;
use App\Services\TrustedPaymentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class TrustedPaymentStatusActions
{
    /**
     * @return list<Action>
     */
    public static function headerActionsFor(TrustedPayment $payment): array
    {
        $actions = array_map(
            fn (TrustedPaymentStatusEnum $next) => self::makeTransitionAction($next, highlight: self::isPrimaryNext($payment, $next)),
            self::allowedStatuses($payment),
        );

        if (self::canRetryPayout($payment)) {
            $actions[] = self::makeRetryPayoutAction();
        }

        return $actions;
    }

    public static function tableActionGroup(): ActionGroup
    {
        $actions = array_map(
            function (TrustedPaymentStatusEnum $next) {
                return self::makeTransitionAction($next, highlight: false)
                    ->visible(fn (TrustedPayment $record): bool => self::isAllowed($record, $next));
            },
            self::allTransitionTargets(),
        );

        $actions[] = self::makeRetryPayoutAction()
            ->visible(fn (TrustedPayment $record): bool => self::canRetryPayout($record));

        return ActionGroup::make($actions)
            ->label('Statut')
            ->icon('heroicon-o-arrow-path')
            ->button()
            ->color('primary')
            ->visible(fn (TrustedPayment $record): bool => self::canAdvance($record) || self::canRetryPayout($record));
    }

    public static function nextStepHint(TrustedPayment $payment): ?string
    {
        foreach (self::allowedStatuses($payment) as $next) {
            if (! in_array($next, [TrustedPaymentStatusEnum::Cancelled, TrustedPaymentStatusEnum::Failed], true)) {
                return 'Suivant : '.$next->label();
            }
        }

        if (self::canRetryPayout($payment)) {
            return 'Suivant : Relancer le versement';
        }

        return null;
    }

    /**
     * @return list<TrustedPaymentStatusEnum>
     */
    protected static function allTransitionTargets(): array
    {
        return [
            TrustedPaymentStatusEnum::CourierEnRoute,
            TrustedPaymentStatusEnum::Collected,
            TrustedPaymentStatusEnum::InTransit,
            TrustedPaymentStatusEnum::Arrived,
            TrustedPaymentStatusEnum::Delivered,
            TrustedPaymentStatusEnum::Cancelled,
            TrustedPaymentStatusEnum::Failed,
        ];
    }

    /**
     * @return list<TrustedPaymentStatusEnum>
     */
    protected static function allowedStatuses(TrustedPayment $payment): array
    {
        $current = self::statusOf($payment);

        if (! $current || $current->isFinal()) {
            return [];
        }

        // funds_held → expedition via commerçant (API) ; pas via admin générique sauf cancel/fail
        return array_values(array_filter(
            $current->allowedNext(),
            function (TrustedPaymentStatusEnum $status) use ($current) {
                if ($current === TrustedPaymentStatusEnum::FundsHeld) {
                    return in_array($status, [
                        TrustedPaymentStatusEnum::ExpeditionRequested,
                        TrustedPaymentStatusEnum::Cancelled,
                        TrustedPaymentStatusEnum::Failed,
                    ], true);
                }

                return $status !== TrustedPaymentStatusEnum::FundsHeld;
            },
        ));
    }

    protected static function canAdvance(TrustedPayment $payment): bool
    {
        return self::allowedStatuses($payment) !== [];
    }

    protected static function canRetryPayout(TrustedPayment $payment): bool
    {
        return $payment->status === TrustedPaymentStatusEnum::Collected
            || $payment->payout_status === TrustedPayoutStatusEnum::Failed
            || (
                in_array($payment->status, [
                    TrustedPaymentStatusEnum::Collected,
                    TrustedPaymentStatusEnum::InTransit,
                    TrustedPaymentStatusEnum::Arrived,
                    TrustedPaymentStatusEnum::Delivered,
                ], true)
                && $payment->payout_status !== TrustedPayoutStatusEnum::Sent
            );
    }

    protected static function isAllowed(TrustedPayment $payment, TrustedPaymentStatusEnum $next): bool
    {
        return in_array($next, self::allowedStatuses($payment), true);
    }

    protected static function isPrimaryNext(TrustedPayment $payment, TrustedPaymentStatusEnum $next): bool
    {
        foreach (self::allowedStatuses($payment) as $candidate) {
            if (! in_array($candidate, [TrustedPaymentStatusEnum::Cancelled, TrustedPaymentStatusEnum::Failed], true)) {
                return $candidate === $next;
            }
        }

        return false;
    }

    protected static function makeTransitionAction(TrustedPaymentStatusEnum $next, bool $highlight): Action
    {
        return Action::make('trusted_to_'.$next->value)
            ->label($next->label())
            ->icon(match ($next) {
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
                default => $highlight ? 'primary' : 'gray',
            })
            ->requiresConfirmation()
            ->modalHeading('Passer à : '.$next->label())
            ->form([
                Textarea::make('note')
                    ->label('Note (optionnel)')
                    ->rows(2)
                    ->maxLength(255),
            ])
            ->action(function (TrustedPayment $record, array $data) use ($next): void {
                try {
                    app(TrustedPaymentService::class)->transition(
                        $record,
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

    protected static function makeRetryPayoutAction(): Action
    {
        return Action::make('retry_payout')
            ->label('Relancer le versement')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Relancer le versement commerçant')
            ->action(function (TrustedPayment $record): void {
                app(TrustedPaymentService::class)->releasePayout($record, forceRetry: true);
                Notification::make()->title('Versement relancé')->success()->send();
            });
    }

    protected static function statusOf(TrustedPayment $payment): ?TrustedPaymentStatusEnum
    {
        return $payment->status instanceof TrustedPaymentStatusEnum
            ? $payment->status
            : TrustedPaymentStatusEnum::tryFrom((string) $payment->status);
    }
}
