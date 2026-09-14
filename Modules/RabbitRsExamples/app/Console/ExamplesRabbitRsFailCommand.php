<?php

namespace Modules\RabbitRsExamples\Console;

use Illuminate\Console\Command;
use Modules\RabbitRsExamples\Jobs\FlakyJob;

class ExamplesRabbitRsFailCommand extends Command
{
    protected $signature = 'examples:rabbit-rs:fail
                            {--connection=rabbit-rs : redis-sentinel or rabbit-rs}';

    protected $description = 'Example: the failure path — tries, retries, failure recording';

    public function handle(): int
    {
        $connection = $this->option('connection');
        if (! in_array($connection, ['redis-sentinel', 'rabbit-rs'], true)) {
            $this->error('Invalid --connection. Use: redis-sentinel or rabbit-rs');

            return 1;
        }

        try {
            FlakyJob::dispatch(uniqid('order-'))
                ->onConnection($connection)
                ->onQueue('bulk');
        } catch (\Throwable $e) {
            $this->error("Dispatch failed: {$e->getMessage()} (is the stack up? make up)");

            return 1;
        }

        $this->info("Dispatched a FlakyJob (tries=3) on {$connection}/bulk.");
        $this->line('The failure contract (copy this):');
        $this->line('  public int $tries = 3;  // worker retries, then records the failure');
        $this->line('Watch it die: /horizon/failed (and the failed_jobs table).');

        return 0;
    }
}
