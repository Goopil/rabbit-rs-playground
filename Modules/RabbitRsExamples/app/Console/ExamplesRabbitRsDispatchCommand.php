<?php

namespace Modules\RabbitRsExamples\Console;

use Illuminate\Console\Command;
use Modules\RabbitRsExamples\Jobs\ProcessOrderJob;

class ExamplesRabbitRsDispatchCommand extends Command
{
    protected $signature = 'examples:rabbit-rs:dispatch
                            {--count=1 : Orders per queue}
                            {--connection= : redis-sentinel, rabbit-rs, or both (default: both)}';

    protected $description = 'Example: route one job class to default/high-priority/bulk via routing_key = {queue}';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        if ($count < 1) {
            $this->error("Invalid count: {$count}. Must be >= 1");

            return 1;
        }

        $connections = match ($this->option('connection') ?: 'both') {
            'redis-sentinel' => ['redis-sentinel'],
            'rabbit-rs' => ['rabbit-rs'],
            'both' => ['redis-sentinel', 'rabbit-rs'],
            default => null,
        };
        if ($connections === null) {
            $this->error('Invalid --connection. Use: redis-sentinel, rabbit-rs, or both');

            return 1;
        }

        $queues = ['default', 'high-priority', 'bulk'];
        foreach ($connections as $connection) {
            foreach ($queues as $queue) {
                for ($i = 0; $i < $count; $i++) {
                    ProcessOrderJob::dispatch(['order' => uniqid('order-')])
                        ->onConnection($connection)
                        ->onQueue($queue);
                }
            }
        }

        $this->info("Dispatched {$count} order(s) × ".count($queues).' queues × '.count($connections).' connection(s).');
        $this->line('The routing contract (copy this):');
        $this->line('  ProcessOrderJob::dispatch([...])->onConnection($c)->onQueue($q);');
        $this->line('  config/queue.php: routing_key = {queue} → the queue name IS the routing key.');
        $this->line('Watch them land: /horizon (Recent jobs) or /lab.');

        return 0;
    }
}
