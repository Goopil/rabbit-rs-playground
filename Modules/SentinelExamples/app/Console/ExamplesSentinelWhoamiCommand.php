<?php

namespace Modules\SentinelExamples\Console;

use Illuminate\Console\Command;
use Modules\SentinelExamples\Support\SentinelWhoami;

class ExamplesSentinelWhoamiCommand extends Command
{
    protected $signature = 'examples:sentinel:whoami';

    protected $description = 'Example: resolve the sentinel topology from app-side code (manager API)';

    public function handle(): int
    {
        $snapshot = SentinelWhoami::captureOrError();

        $this->info('Sentinel-backed connection "default":');
        $this->line('  service "'.$snapshot['service'].'"');
        $this->line('  sentinels: '.implode(', ', $snapshot['sentinels']));
        $this->line('  master: '.$snapshot['master']);
        $this->line('  role: '.($snapshot['role'] ?? '?'));
        $this->newLine();
        $this->line('The resolution contract (copy this):');
        $this->line('  $manager = app(RedisSentinelManager::class);');
        $this->line('  $connector = $manager->resolveConnector("default");');
        $this->line('  $sentinel = $connector->createSentinel("default");');
        $this->line('  $master = $sentinel->master("mymaster"); // or resolve() for a client');
        $this->line('Failover drills: make chaos-kill-master / make chaos-heal (README).');
        $this->line('Diagnostics: the package ships its own sentinel:status command.');

        return 0;
    }
}
