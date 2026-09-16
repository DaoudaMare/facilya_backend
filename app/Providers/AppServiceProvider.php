<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component(
            'filament.livewire.configuration-fees-table',
            \App\Filament\Livewire\ConfigurationFeesTable::class,
        );

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('phone', ''));
        });

        RateLimiter::for('assistant', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(20)->by('assistant|'.$key);
        });
    }
}
