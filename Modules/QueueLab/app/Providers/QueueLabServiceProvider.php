<?php

namespace Modules\QueueLab\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

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
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
