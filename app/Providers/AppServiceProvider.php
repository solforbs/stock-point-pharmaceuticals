<?php

namespace App\Providers;

use App\Services\Tax\Etims\EtimsDriver;
use App\Services\Tax\Etims\HttpDriver;
use App\Services\Tax\Etims\LogDriver;
use App\Services\Tax\Etims\NullDriver;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant per request, per queued job, per console loop iteration.
        $this->app->scoped(TenantContext::class);

        // Part 13.6 — the eTIMS transport is configuration, not code.
        $this->app->bind(EtimsDriver::class, fn () => match ((string) config('etims.driver')) {
            'http' => new HttpDriver,
            'log' => new LogDriver,
            default => new NullDriver,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
