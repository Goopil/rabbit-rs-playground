<?php

namespace Modules\RabbitRsExamples\Providers;

use Modules\RabbitRsExamples\Console\ExamplesRabbitRsDispatchCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class RabbitRsExamplesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'RabbitRsExamples';

    protected string $nameLower = 'rabbitrsexamples';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExamplesRabbitRsDispatchCommand::class,
    ];
}
