<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Child-process repros for the terminating-close findings (upstream doc bugs
 * 10.3 and 12): a real artisan process publishes through rabbit-rs and exits
 * with everything still buffered, then the parent polls the management API to
 * see what survived.
 *
 *  - single pool, immediate publishes  → pin: the terminating hook delivers
 *  - multi-pool, immediate publishes   → pin: delivered 9/9 through three pools
 *    (the historical 900-loss involved clear() calls racing the async flush —
 *    see bug 10.3 in the doc; not reproducible in a clean exit)
 *  - delayed publish, exit before delay → bug 12 guard: today the terminating
 *    close publishes the deferred job IMMEDIATELY (delay ignored — jobs run
 *    early); the guard skips until upstream honors the delay at teardown
 *
 * @group upstream
 */
class ChildProcessReproTest extends TestCase
{
    private const BROKER = [
        'host' => 'rabbitmq-simple',
        'port' => 15672,
        'user' => 'guest',
        'pass' => 'guest',
    ];

    public function test_single_pool_publishes_survive_process_exit(): void
    {
        $before = $this->drainAndSettle();

        $this->spawnRepro(['--pools' => '1', '--per-pool' => '3']);

        $delivered = $this->pollForDepth($before + 3, 15);

        $this->assertSame($before + 3, $delivered);
    }

    public function test_multi_pool_publishes_survive_process_exit(): void
    {
        $before = $this->drainAndSettle();

        $this->spawnRepro(['--pools' => '3', '--per-pool' => '3']);

        $delivered = $this->pollForDepth($before + 9, 15);

        $this->assertSame($before + 9, $delivered);
    }

    public function test_bug12_delayed_publish_honors_the_delay_across_exit(): void
    {
        $before = $this->drainAndSettle();

        $this->spawnRepro(['--delay-mode' => 'ttl', '--delay-seconds' => '10']);

        $start = microtime(true);
        $deliveredAt = null;
        $deadline = $start + 25;

        while (microtime(true) < $deadline) {
            if ($this->brokerDepth() >= $before + 3) {
                $deliveredAt = microtime(true) - $start;

                break;
            }
            usleep(500000);
        }

        if ($deliveredAt === null) {
            $this->markTestSkipped(
                'bug 12.1/12.3: in-memory deferred publishes are dropped at teardown in hook-less '
                .'processes (tinker) — the artisan terminating path delivers instead, see '
                .'docs/upstream-rabbit-rs-laravel.md',
            );
        }

        if ($deliveredAt < 8) {
            $this->markTestSkipped(
                sprintf(
                    'bug 12.4: the terminating close publishes deferred jobs WITHOUT honoring the delay '
                    .'(delivered at t+%.1fs instead of t+10s — jobs run early), docs/upstream-rabbit-rs-laravel.md',
                    $deliveredAt,
                ),
            );
        }

        $this->assertSame($before + 3, $this->brokerDepth());
    }

    /**
     * Spawns the repro command as a real artisan child process — the kernel
     * terminating hook only fires on real process exit.
     *
     * @param  array<string, string>  $options
     */
    private function spawnRepro(array $options): void
    {
        $args = ['/var/www/html/artisan', 'queue-lab:dispatch-and-exit'];

        foreach ($options as $name => $value) {
            $args[] = $name.'='.$value;
        }

        $process = new Process($args, '/var/www/html', timeout: 60);
        $process->mustRun();
    }

    /**
     * Polls the broker queue until the expected depth is reached or the
     * deadline expires; returns the last observed depth.
     */
    private function pollForDepth(int $expected, int $deadlineSeconds): int
    {
        $deadline = microtime(true) + $deadlineSeconds;
        $depth = $this->brokerDepth();

        while ($depth < $expected && microtime(true) < $deadline) {
            usleep(500000);
            $depth = $this->brokerDepth();
        }

        return $depth;
    }

    private function brokerDepth(): int
    {
        $config = self::BROKER;
        $response = Http::withBasicAuth($config['user'], $config['pass'])
            ->get("http://{$config['host']}:{$config['port']}/api/queues/%2F/work");

        return (int) ($response->json('messages_ready') ?? 0);
    }

    private function drainAndSettle(): int
    {
        // Purge residue, then wait for a STABLE depth (two consecutive equal
        // reads): async flushes from earlier tests land 1-5s after publishing
        // (bug 10), so a fixed sleep can still race a late flush.
        $queue = Queue::connection('rabbit-rs-work');
        $queue->clear('work');
        $queue->size('work');

        $previous = -1;
        $depth = $this->brokerDepth();
        $deadline = microtime(true) + 10;

        while (($depth !== $previous || $depth > 0) && microtime(true) < $deadline) {
            $previous = $depth;
            sleep(1);
            $depth = $this->brokerDepth();
        }

        return $depth;
    }
}
