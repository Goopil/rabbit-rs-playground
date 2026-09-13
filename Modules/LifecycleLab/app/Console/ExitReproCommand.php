<?php

namespace Modules\LifecycleLab\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Modules\RabbitRs\Jobs\ProcessDefaultJob;

/**
 * Child-process repro for the terminating-close findings (upstream doc bugs 10.3
 * and 12): publishes jobs through one or more pools and EXITS immediately, with
 * no settle, no kick, no clear. Whatever the driver does with un-flushed buffers
 * and in-memory deferred publishes at process teardown is the finding.
 *
 * Pair it with a broker poll from the parent process (see
 * tests/Feature/ChildProcessReproTest.php):
 *   php artisan queue-lab:dispatch-and-exit --pools=3 --per-pool=3
 * then watch /api/queues/%2F/work until the expected depth shows up (or not).
 */
class ExitReproCommand extends Command
{
    protected $signature = 'queue-lab:dispatch-and-exit
                            {--connection=rabbit-rs-work : rabbit-rs connection to publish through}
                            {--queue=work : Test queue (must exist on the broker)}
                            {--pools=1 : Number of pools (each one a different safety mode)}
                            {--per-pool=3 : Jobs published per pool}
                            {--delay-mode=auto : delay.mode for later() publishes}
                            {--delay-seconds=0 : >0 publishes with later() and exits before the delay elapses}';

    protected $description = 'Publish through rabbit-rs and exit immediately — terminating-close repro for the broker poll';

    private const MODES = ['blind', 'unsafe', 'safe'];

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $queueName = (string) $this->option('queue');
        $pools = max(1, (int) $this->option('pools'));
        $perPool = max(1, (int) $this->option('per-pool'));
        $delaySeconds = (int) $this->option('delay-seconds');

        if (config("queue.connections.{$connection}.driver") !== 'rabbit-rs') {
            $this->error("Connection [{$connection}] is not a rabbit-rs connection.");

            return 1;
        }

        if ($delaySeconds > 0) {
            config()->set('rabbit-rs.delay.mode', (string) $this->option('delay-mode'));
            $this->forgetResolvedQueueConnections();
        }

        $modes = array_slice(self::MODES, 0, $pools);

        foreach ($modes as $mode) {
            config()->set('rabbit-rs.safety', $mode);
            $this->forgetResolvedQueueConnections();

            $queue = Queue::connection($connection);

            for ($i = 0; $i < $perPool; $i++) {
                $job = new ProcessDefaultJob(['id' => $i, 'source' => "exit-repro-{$mode}"]);

                $delaySeconds > 0
                    ? $queue->later($delaySeconds, $job, '', $queueName)
                    : $queue->push($job, '', $queueName);
            }
        }

        // Deliberately NO size()/clear()/settle: the point is to exit with
        // everything still in the publish buffer (or in the in-memory delay
        // hold) and let the terminating hook decide the fate of the tail.
        $this->info(sprintf(
            'Dispatched %d job(s) through %d pool(s) via %s (delay=%ds) — exiting before any flush.',
            $perPool * count($modes),
            count($modes),
            $delaySeconds > 0 ? 'later()' : 'push()',
            $delaySeconds,
        ));

        return 0;
    }

    private function forgetResolvedQueueConnections(): void
    {
        try {
            $manager = app('queue');
            $property = new \ReflectionProperty($manager, 'connections');
            $value = $property->getValue($manager);
            if (is_array($value)) {
                $property->setValue($manager, []);
            }
        } catch (\ReflectionException) {
            // Framework change: stale pools survive until the process ends.
        }
    }
}
