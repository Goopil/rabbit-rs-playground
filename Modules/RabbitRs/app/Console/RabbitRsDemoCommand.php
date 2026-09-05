<?php

namespace Modules\RabbitRs\Console;

use Illuminate\Console\Command;
use Modules\QueueLab\Jobs\StressJob;
use Modules\RabbitRs\Jobs\ProcessDefaultJob;
use Modules\RabbitRs\Jobs\ProcessHighPriorityJob;

class RabbitRsDemoCommand extends Command
{
    protected $signature = 'rabbit-rs:demo
                            {--count=1 : Number of iterations (3 jobs per iteration per connection)}
                            {--connection= : redis-sentinel, rabbit-rs, or both (default: both)}';

    protected $description = 'Dispatch demo jobs to the demo queues (default, high-priority, bulk)';

    public function handle(): int
    {
        $count = (int) $this->option('count');

        if ($count < 1) {
            $this->error("Invalid count: {$count}. Must be >= 1");

            return 1;
        }

        $connectionOption = $this->option('connection') ?: 'both';
        $connections = match ($connectionOption) {
            'redis-sentinel' => ['redis-sentinel'],
            'rabbit-rs' => ['rabbit-rs'],
            'both' => ['redis-sentinel', 'rabbit-rs'],
            default => null,
        };
        if ($connections === null) {
            $this->error("Invalid --connection: {$connectionOption}. Use: redis-sentinel, rabbit-rs, or both");

            return 1;
        }

        $queues = ['default' => ProcessDefaultJob::class, 'high-priority' => ProcessHighPriorityJob::class, 'bulk' => StressJob::class];
        $totalExpected = $count * count($queues) * count($connections);
        $dispatched = 0;

        $this->info("Dispatching {$totalExpected} jobs...");
        $progressBar = $this->output->createProgressBar($totalExpected);
        $progressBar->start();

        for ($iter = 0; $iter < $count; $iter++) {
            foreach ($connections as $connection) {
                foreach ($queues as $queue => $jobClass) {
                    $id = $dispatched + 1;

                    if ($jobClass === StressJob::class) {
                        StressJob::dispatch($id)->onConnection($connection)->onQueue($queue);
                    } else {
                        $jobClass::dispatch(['id' => $id, 'message' => "Demo #{$id} on {$connection}@{$queue}"])
                            ->onConnection($connection)
                            ->onQueue($queue);
                    }

                    $dispatched++;
                    $progressBar->advance();
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Dispatched {$dispatched} jobs to ".implode(', ', $connections).'.');
        $this->info('Horizon dashboard: /horizon');

        return 0;
    }
}
