<?php

namespace App\Filament\Resources\ParcelShipments\Support;

use App\Data\ParcelDeliveryModeEnum;
use App\Data\ParcelStatusEnum;
use App\Models\ParcelShipment;
use App\Services\ParcelShipmentService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class ParcelShipmentStatusActions
{
    /**
     * Actions header fiche (uniquement transitions autorisées pour ce record).
     *
     * @return list<Action>
     */
    public static function headerActionsFor(ParcelShipment $shipment): array
    {
        return array_map(
            fn (ParcelStatusEnum $next) => self::makeTransitionAction($next, highlight: self::isPrimaryNext($shipment, $next)),
            self::allowedStatuses($shipment),
        );
    }

    /**
     * Groupe d’actions liste : toutes les cibles possibles, filtrées par visibilité.
     */
    public static function tableActionGroup(): ActionGroup
    {
        return ActionGroup::make(
            array_map(
                function (ParcelStatusEnum $next) {
                    return self::makeTransitionAction($next, highlight: false)
                        ->visible(fn (ParcelShipment $record): bool => self::isAllowed($record, $next));
                },
                self::allTransitionTargets(),
            )
        )
            ->label('Statut')
            ->icon('heroicon-o-arrow-path')
            ->button()
            ->color('primary')
            ->visible(fn (ParcelShipment $record): bool => self::canAdvance($record));
    }

    /**
     * @return list<ParcelStatusEnum>
     */
    protected static function allTransitionTargets(): array
    {
        return [
            ParcelStatusEnum::Confirmed,
            ParcelStatusEnum::CourierEnRoute,
            ParcelStatusEnum::Collected,
            ParcelStatusEnum::Shipped,
            ParcelStatusEnum::ArrivedStation,
            ParcelStatusEnum::Delivered,
            ParcelStatusEnum::PickedUp,
            ParcelStatusEnum::Cancelled,
            ParcelStatusEnum::Failed,
        ];
    }

    /**
     * @return list<ParcelStatusEnum>
     */
    protected static function allowedStatuses(ParcelShipment $shipment): array
    {
        $current = self::statusOf($shipment);
        $mode = self::modeOf($shipment);

        if (! $current || ! $mode) {
            return [];
        }

        return $current->allowedNext($mode);
    }

    public static function canAdvance(ParcelShipment $shipment): bool
    {
        return self::allowedStatuses($shipment) !== [];
    }

    public static function nextStepHint(ParcelShipment $shipment): ?string
    {
        foreach (self::allowedStatuses($shipment) as $next) {
            if (! in_array($next, [ParcelStatusEnum::Cancelled, ParcelStatusEnum::Failed], true)) {
                return 'Suivant : '.$next->label();
            }
        }

        return null;
    }

    protected static function isAllowed(ParcelShipment $shipment, ParcelStatusEnum $next): bool
    {
        return in_array($next, self::allowedStatuses($shipment), true);
    }

    protected static function isPrimaryNext(ParcelShipment $shipment, ParcelStatusEnum $next): bool
    {
        foreach (self::allowedStatuses($shipment) as $candidate) {
            if (! in_array($candidate, [ParcelStatusEnum::Cancelled, ParcelStatusEnum::Failed], true)) {
                return $candidate === $next;
            }
        }

        return false;
    }

    protected static function makeTransitionAction(ParcelStatusEnum $next, bool $highlight): Action
    {
        return Action::make('to_'.$next->value)
            ->label(($highlight ? '→ ' : '').$next->label())
            ->icon(match ($next) {
                ParcelStatusEnum::Confirmed => 'heroicon-o-check-circle',
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
                default => $highlight ? 'primary' : 'gray',
            })
            ->requiresConfirmation()
            ->modalHeading('Passer à : '.$next->label())
            ->modalDescription('Seules les transitions logiques du parcours colis sont autorisées.')
            ->form([
                Textarea::make('note')
                    ->label('Note (optionnel)')
                    ->rows(2)
                    ->maxLength(255),
            ])
            ->action(function (ParcelShipment $record, array $data) use ($next): void {
                try {
                    app(ParcelShipmentService::class)->transition(
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

    protected static function statusOf(ParcelShipment $shipment): ?ParcelStatusEnum
    {
        return $shipment->status instanceof ParcelStatusEnum
            ? $shipment->status
            : ParcelStatusEnum::tryFrom((string) $shipment->status);
    }

    protected static function modeOf(ParcelShipment $shipment): ?ParcelDeliveryModeEnum
    {
        return $shipment->delivery_mode instanceof ParcelDeliveryModeEnum
            ? $shipment->delivery_mode
            : ParcelDeliveryModeEnum::tryFrom((string) $shipment->delivery_mode);
    }
}
