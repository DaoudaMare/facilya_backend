<?php

namespace App\Providers;

use App\Repositories\Contracts\FeeRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\TransferNetworkRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyRouteRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyStationRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyTripRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\FeeRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\TransferNetworkRepository;
use App\Repositories\TravelCompanyRepository;
use App\Repositories\TravelCompanyRouteRepository;
use App\Repositories\TravelCompanyStationRepository;
use App\Repositories\TravelCompanyTripRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [
        FeeRepositoryInterface::class => FeeRepository::class,
        TransactionRepositoryInterface::class => TransactionRepository::class,
        TravelCompanyRepositoryInterface::class => TravelCompanyRepository::class,
        TravelCompanyStationRepositoryInterface::class => TravelCompanyStationRepository::class,
        TravelCompanyRouteRepositoryInterface::class => TravelCompanyRouteRepository::class,
        TravelCompanyTripRepositoryInterface::class => TravelCompanyTripRepository::class,
        TransferNetworkRepositoryInterface::class => TransferNetworkRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
    ];
}
