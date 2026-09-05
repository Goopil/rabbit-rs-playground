<?php

namespace Modules\RabbitRs\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\RabbitRs\Console\RabbitRsDemoCommand;
use Modules\RabbitRs\Console\RabbitRsSetupTopologyCommand;
use Modules\RabbitRs\Console\RabbitRsSetupVhostsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class RabbitRsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'RabbitRs';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'rabbitrs';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        RabbitRsDemoCommand::class,
        RabbitRsSetupTopologyCommand::class,
        RabbitRsSetupVhostsCommand::class,
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
