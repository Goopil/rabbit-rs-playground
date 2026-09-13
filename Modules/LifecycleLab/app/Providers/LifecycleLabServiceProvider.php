<?php

namespace Modules\LifecycleLab\Providers;

use Modules\LifecycleLab\Console\ExitReproCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LifecycleLabServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'LifecycleLab';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'lifecyclelab';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExitReproCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [];
}
