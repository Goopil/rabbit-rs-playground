<?php

namespace Modules\QueueLab\Console;

use Illuminate\Console\Command;
use Modules\QueueLab\Jobs\StressJob;

class QueueLabStressCommand extends Command
{
    protected $signature = 'queue-lab:stress
                            {--count=100 : Jobs to dispatch}
                            {--queue=bulk : Target queue}
                            {--connection=redis-sentinel : Queue connection}
                            {--sleep-ms=0 : Simulated work per job}
                            {--fail-every=0 : Fail every Nth job (0 = never)}';

    protected $description = 'Dispatch a burst of stress jobs (Horizon / redis-sentinel)';

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 1; $i <= $count; $i++) {
            StressJob::dispatch($i, (int) $this->option('sleep-ms'), (int) $this->option('fail-every'))
                ->onConnection($this->option('connection'))
                ->onQueue($this->option('queue'));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$count} StressJob to {$this->option('connection')}@{$this->option('queue')}");

        return 0;
    }
}
