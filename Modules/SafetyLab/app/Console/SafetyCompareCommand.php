<?php

namespace Modules\SafetyLab\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\RabbitRs\Jobs\ProcessDefaultJob;

/**
 * Compares the rabbit-rs safety modes (blind / unsafe / safe) live: throughput,
 * broker reception, unroutable-routing behavior. The command signature is
 * `queue-lab:safety` on purpose — it is referenced by
 * docs/upstream-rabbit-rs-laravel.md, which was shared with goopil.
 */
class SafetyCompareCommand extends Command
{
    protected $signature = 'queue-lab:safety
                            {--count=200 : Jobs published per safety mode}
                            {--queue=work : Test queue (must exist on the broker)}
                            {--connection=rabbit-rs-work : rabbit-rs connection to publish through}
                            {--settle=5 : Seconds to wait for the async publish flush before reading the broker}
                            {--cleanup : Purge the test queue after the table (the last mode\'s messages would otherwise stay)}';

    protected $description = 'Compare the rabbit-rs safety modes (blind / unsafe / safe): throughput, broker reception, unroutable behavior';

    private const MODES = ['blind', 'unsafe', 'safe'];

    private const BROKER = [
        'host' => 'rabbitmq-simple',
        'port' => 15672,
        'user' => 'guest',
        'pass' => 'guest',
    ];

    private const UNROUTABLE_QUEUE = 'queue-lab-unroutable';

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        $queueName = (string) $this->option('queue');
        $connection = (string) $this->option('connection');

        if (config("queue.connections.{$connection}.driver") !== 'rabbit-rs') {
            $this->error("Connection [{$connection}] is not a rabbit-rs connection.");

            return 1;
        }

        $this->info("Publishing {$count} jobs per mode through [{$connection}]@{$queueName}…");
        $this->newLine();

        $rows = [];

        foreach (self::MODES as $mode) {
            $rows[] = $this->benchmarkMode($mode, $count, $queueName, $connection);
            $this->forgetResolvedQueueConnections();
        }

        $this->table(
            ['mode', 'published', 'ops/s', 'broker received', 'unroutable routing'],
            $rows,
        );

        $this->line('    note: the three modes deliver everything while the process lives — blind');
        $this->line('    only differs on crash (buffered publishes are lost). safe additionally');
        $this->line('    confirms each publish and fails loud on unroutable routing (mandatory).');
        $this->line('    note: the driver flushes publishes asynchronously (~1-3s, see docs/');
        $this->line('    upstream-rabbit-rs-laravel.md bug 10) — each mode settles before reading.');

        if ((bool) $this->option('cleanup')) {
            // Without this, the LAST mode's batch stays on the queue forever:
            // the async flush lands after the run and no later clear exists.
            sleep($this->settleSeconds());
            $purged = Queue::connection($connection)->clear($queueName);
            $this->line("    cleanup: purged {$purged} message(s) from {$queueName}.");
        }

        return 0;
    }

    /**
     * @return array{0: string, 1: int, 2: string, 3: int, 4: string}
     */
    private function benchmarkMode(string $mode, int $count, string $queueName, string $connection): array
    {
        config()->set('rabbit-rs.safety', $mode);
        $this->forgetResolvedQueueConnections();

        $queue = Queue::connection($connection);
        $queue->clear($queueName);

        $start = hrtime(true);
        for ($i = 0; $i < $count; $i++) {
            ProcessDefaultJob::dispatch(['id' => $i, 'source' => "safety-{$mode}"])
                ->onConnection($connection)
                ->onQueue($queueName);
        }
        $elapsedSeconds = (hrtime(true) - $start) / 1e9;
        $opsPerSecond = number_format($count / max($elapsedSeconds, 1e-9), 0);

        // Force a flush attempt, then poll the broker until the expected
        // depth is reached (bounded by max(2x settle, 5s)). A fixed sleep
        // races the async age-flush tail (bug 10): a 300-publish batch once
        // read 246/300 at t+3s, and 5-message batches occasionally took >3s.
        $queue->size($queueName);
        sleep($this->settleSeconds());
        $received = $this->depthOrConverged($queueName, $count);

        $unroutable = $this->probeUnroutable($queue, $queueName, $connection);

        return [$mode, $count, $opsPerSecond, $received, $unroutable];
    }

    /**
     * Publishes one job to a routing key with no bound queue and reports how
     * the mode handles it: safe must fail loud (mandatory + confirms), unsafe
     * and blind drop silently at the broker.
     */
    private function probeUnroutable($queue, string $queueName, string $connection): string
    {
        try {
            ProcessDefaultJob::dispatch(['id' => 0, 'source' => 'safety-unroutable'])
                ->onConnection($connection)
                ->onQueue(self::UNROUTABLE_QUEUE);

            $queue->size($queueName);
            sleep($this->settleSeconds());
        } catch (\Throwable $e) {
            return 'rejected: '.class_basename($e).' — '.mb_strimwidth($e->getMessage(), 0, 48, '…');
        }

        return $this->brokerDepth(self::UNROUTABLE_QUEUE) === null
            ? 'silently dropped'
            : 'landed (!)';
    }

    private function settleSeconds(): int
    {
        return max(1, (int) $this->option('settle'));
    }

    private function depthOrConverged(string $queue, int $expected): int
    {
        $last = $this->brokerDepth($queue) ?? -1;
        $deadline = microtime(true) + max(2 * $this->settleSeconds(), 5);

        while ($last < $expected && microtime(true) < $deadline) {
            usleep(500000);
            $last = $this->brokerDepth($queue) ?? -1;
        }

        return $last;
    }

    private function brokerDepth(string $queue): ?int
    {
        $config = self::BROKER;

        // /get (fetch-and-requeue) is the authoritative read: the
        // messages_ready summary lags the broker by seconds (observed 0
        // while /get saw the messages), which is what the depth poll must
        // not race. Capped at 200 — the command's --count default.
        $response = Http::withBasicAuth($config['user'], $config['pass'])
            ->post("http://{$config['host']}:{$config['port']}/api/queues/%2F/{$queue}/get", [
                'count' => 200,
                'ackmode' => 'ack_requeue_true',
                'encoding' => 'auto',
                'truncate' => 1,
            ]);

        if ($response->status() !== 200) {
            return null;
        }

        return count($response->json() ?? []);
    }

    /**
     * Drops the queue manager's resolved connections so the next resolution
     * recompiles from the mutated config. Mirrors the package's own
     * OctaneLifecycle: Laravel 13 exposes no public API for this.
     */
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
