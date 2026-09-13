<?php

namespace Modules\SafetyLab\Providers;

use Modules\SafetyLab\Console\SafetyCompareCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SafetyLabServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'SafetyLab';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'safetylab';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SafetyCompareCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [];

    public function boot(): void
    {
        parent::boot();
    }
}
