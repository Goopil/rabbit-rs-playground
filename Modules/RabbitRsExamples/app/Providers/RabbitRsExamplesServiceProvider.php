<?php

namespace Modules\RabbitRsExamples\Providers;

use Modules\RabbitRsExamples\Console\ExamplesRabbitRsDelayCommand;
use Modules\RabbitRsExamples\Console\ExamplesRabbitRsDispatchCommand;
use Modules\RabbitRsExamples\Console\ExamplesRabbitRsFailCommand;
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
        ExamplesRabbitRsDelayCommand::class,
        ExamplesRabbitRsFailCommand::class,
    ];
}
