<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\WorkerStarting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // goopil/laravel-redis-sentinel resets stickiness on Octane RequestReceived,
        // but RedisManager::$connections is uninitialized (null) in fresh workers.
        // Resolve the sentinel connections at worker start (also warms them).
        Event::listen(WorkerStarting::class, function () {
            Redis::connection('default');
            Redis::connection('cache');
        });
    }
}
