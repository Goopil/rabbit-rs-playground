<?php

namespace Modules\RabbitRsExamples\Console;

use Illuminate\Console\Command;
use Modules\RabbitRsExamples\Jobs\DelayedReportJob;

class ExamplesRabbitRsDelayCommand extends Command
{
    protected $signature = 'examples:rabbit-rs:delay
                            {--seconds=30 : Delay before delivery}
                            {--connection=rabbit-rs : redis-sentinel or rabbit-rs}';

    protected $description = 'Example: later(N) with the ttl delay mode — buckets quantize UP to 1/5/30/120s';

    public function handle(): int
    {
        $seconds = (int) $this->option('seconds');
        if ($seconds < 1) {
            $this->error("Invalid --seconds: {$seconds}. Must be >= 1");

            return 1;
        }

        $connection = $this->option('connection');
        if (! in_array($connection, ['redis-sentinel', 'rabbit-rs'], true)) {
            $this->error('Invalid --connection. Use: redis-sentinel or rabbit-rs');

            return 1;
        }

        try {
            DelayedReportJob::dispatch(['report' => uniqid('report-'), 'at' => now()->toIso8601String()])
                ->delay($seconds)
                ->onConnection($connection)
                ->onQueue('default');
        } catch (\Throwable $e) {
            $this->error("Dispatch failed: {$e->getMessage()} (is the stack up? make up)");

            return 1;
        }

        $this->info("Delayed a report by {$seconds}s on {$connection}/default.");
        $this->line('The delay contract (copy this):');
        $this->line('  DelayedReportJob::dispatch([...])->delay($seconds)->onConnection("rabbit-rs");');
        $this->line('  ttl mode quantizes UP to the configured buckets (1/5/30/120s): later(10) → the 30s bucket.');
        $this->line('Watch the bucket queues in RabbitMQ Management (localhost:15672): rabbit-rs.delay.*');

        return 0;
    }
}
