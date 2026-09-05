<?php

namespace Modules\QueueLab\Providers;

use Goopil\LaravelRedisSentinel\Events\RedisSentinelConnectionFailed;
use Goopil\LaravelRedisSentinel\Events\RedisSentinelConnectionReconnected;
use Goopil\LaravelRedisSentinel\Events\RedisSentinelMasterFailed;
use Goopil\LaravelRedisSentinel\Events\RedisSentinelMasterReconnected;
use Goopil\LaravelRedisSentinel\Events\RedisSentinelReplicaFallback;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\QueueLab\Console\QueueLabStressCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class QueueLabServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'QueueLab';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'queuelab';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        QueueLabStressCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        foreach ([
            RedisSentinelMasterFailed::class,
            RedisSentinelMasterReconnected::class,
            RedisSentinelConnectionFailed::class,
            RedisSentinelConnectionReconnected::class,
            RedisSentinelReplicaFallback::class,
        ] as $event) {
            Event::listen($event, fn ($e) => Log::channel('sentinel')->info(class_basename($e), (array) $e));
        }
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
