<?php

namespace App\Providers;

use App\Services\KaeConfig;
use App\Services\QdrantService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QdrantService::class);
        $this->app->singleton(KaeConfig::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
