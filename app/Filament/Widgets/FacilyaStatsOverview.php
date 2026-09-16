<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AuthorizesPermissions;
use App\Models\User;
use App\Services\TransactionService;
use App\Services\TravelCompanyService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FacilyaStatsOverview extends StatsOverviewWidget
{
    use AuthorizesPermissions;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return static::userHasPermission(
            'transactions.view',
            'transactions.manage',
            'travel.manage',
        );
    }

    protected function getStats(): array
    {
        $stats = app(TransactionService::class)->dashboardStats();

        return [
            Stat::make('Transactions du jour', (string) $stats['today_count'])
                ->description(number_format((float) $stats['today_volume'], 0, ',', ' ').' F CFA')
                ->color('primary'),
            Stat::make(
                'Paiements en attente',
                (string) $stats['pending_payment'],
            )->color('warning'),
            Stat::make(
                'Services à livrer',
                (string) $stats['awaiting_service'],
            )->color('info'),
            Stat::make(
                'Compagnies actives',
                (string) app(TravelCompanyService::class)->countActive(),
            )->description(User::query()->count().' utilisateurs')
                ->color('success'),
        ];
    }
}
