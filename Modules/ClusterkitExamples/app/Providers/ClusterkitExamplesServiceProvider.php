<?php

namespace Modules\ClusterkitExamples\Providers;

use Modules\ClusterkitExamples\Console\ExamplesClusterkitRenderCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ClusterkitExamplesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'ClusterkitExamples';

    protected string $nameLower = 'clusterkitexamples';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExamplesClusterkitRenderCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
