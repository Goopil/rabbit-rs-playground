<?php

namespace Modules\SentinelExamples\Providers;

use Modules\SentinelExamples\Console\ExamplesSentinelCacheCommand;
use Modules\SentinelExamples\Console\ExamplesSentinelWhoamiCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SentinelExamplesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'SentinelExamples';

    protected string $nameLower = 'sentinelexamples';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExamplesSentinelWhoamiCommand::class,
        ExamplesSentinelCacheCommand::class,
    ];
}
