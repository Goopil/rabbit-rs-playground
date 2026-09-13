<?php

namespace Tests\Feature;

use Goopil\RabbitRs\Laravel\Config\ConnectionCompiler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Modules\QueueLab\Jobs\StressJob;
use Tests\TestCase;

/**
 * Config-surface pins for the rabbit-rs ConnectionCompiler: what a connection
 * accepts, what it rejects (typed errors), and how the topology knobs map to
 * the compiled native config. Compile-time tests are broker-free; one live
 * round-trip proves the driver also works against a classic (non-quorum)
 * queue.
 *
 * @group upstream
 */
class RabbitRsTopologyConfigTest extends TestCase
{
    public function test_compiles_the_playground_connections(): void
    {
        foreach (['rabbit-rs', 'rabbit-rs-work', 'rabbit-rs-ia'] as $name) {
            $native = ConnectionCompiler::compile(
                $name,
                config("queue.connections.{$name}"),
                config('rabbit-rs'),
            )['native'];

            $this->assertSame('quorum', $native['queue_type'], $name);
            $this->assertSame(20, $native['delivery_limit'], $name);
            $this->assertSame(config('rabbit-rs.delay.mode'), $native['delay']['mode'], $name);
            $this->assertSame('external', $native['topology_mode'], $name);
            $this->assertSame('dead-letters', $native['dead_letter']['exchange'], $name);
        }
    }

    public function test_routes_fall_back_to_a_default_route_with_the_queue_template(): void
    {
        $compiled = ConnectionCompiler::compile(
            'rabbit-rs-work',
            config('queue.connections.rabbit-rs-work'),
            config('rabbit-rs'),
        );

        $this->assertSame('laravel.jobs', $compiled['routes']['default']['exchange']);
        $this->assertSame('{queue}', $compiled['routes']['default']['routing_key']);
        $this->assertSame('rabbit-rs-work', $compiled['routes']['default']['broker']);
    }

    public function test_rejects_unknown_connection_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('totally_unknown');

        ConnectionCompiler::compile('x', $this->config(['totally_unknown' => 1]), []);
    }

    public function test_rejects_invalid_delay_modes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be auto, plugin, or ttl');

        ConnectionCompiler::compile('x', $this->config(['delay' => ['mode' => 'fast']]), []);
    }

    public function test_rejects_bucket_counts_over_max_buckets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds configured maximum');

        ConnectionCompiler::compile('x', $this->config(['delay' => [
            'buckets' => [1, 2, 3, 4, 5, 6, 7, 8, 9],
        ]]), []);
    }

    public function test_rejects_delivery_limit_without_dead_letter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dead_letter must be configured');

        ConnectionCompiler::compile('x', $this->config(['delivery_limit' => 20]), []);
    }

    public function test_rejects_unknown_queue_types(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be quorum or classic');

        ConnectionCompiler::compile('x', $this->config(['queue_type' => 'redis']), []);
    }

    public function test_rejects_consumer_wait_timeouts_under_one_second(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('wait_timeout');

        ConnectionCompiler::compile('x', $this->config(['wait_timeout' => 500]), []);
    }

    public function test_rejects_delivery_limit_on_classic_queues(): void
    {
        // 0.2.1 (#204): delivery_limit on a classic queue fails broker-side
        // with precondition_failed (bug 13) — the compiler now rejects the
        // combination before the extension is ever loaded.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('delivery_limit');

        ConnectionCompiler::compile('x', $this->config([
            'queue_type' => 'classic',
            'delivery_limit' => 5,
            'dead_letter' => ['exchange' => 'dead-letters', 'queue' => 'failed-jobs', 'routing_key' => null],
        ]), []);
    }

    public function test_subscription_knobs_reach_the_compiled_profile(): void
    {
        $native = ConnectionCompiler::compile(
            'rabbit-rs',
            config('queue.connections.rabbit-rs'),
            config('rabbit-rs'),
        )['native'];

        $high = collect($native['workers'][0]['subscriptions'])->firstWhere('queue', 'high-priority');

        $this->assertSame(4, $high['weight']);
        $this->assertSame(16, $high['prefetch']);
        $this->assertFalse($high['early_ack']);
        $this->assertFalse($high['no_ack']);
        $this->assertSame(['strategy' => 'weighted_fair'], $native['workers'][0]['scheduler']);
    }

    public function test_subscription_priority_classes_are_rejected_in_0_2(): void
    {
        // 0.2.0 removed the dead knobs (priority_class, starvation_after) from
        // the subscription surface — the playground config no longer carries
        // them, and a stray one must fail with the typed path error.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('priority_class');

        ConnectionCompiler::compile('x', $this->config([
            'subscriptions' => [
                'default' => ['queue' => 'work', 'priority_class' => 0],
            ],
        ]), []);
    }

    public function test_nondurable_classic_compiles_but_nondurable_quorum_is_rejected(): void
    {
        // Classic queues may be non-durable — that still compiles.
        $classic = ConnectionCompiler::compile('x', $this->config([
            'queue_type' => 'classic',
            'queue_durable' => false,
        ]), [])['native'];

        $this->assertSame('classic', $classic['queue_type']);
        $this->assertFalse($classic['queue_durable']);

        // 0.2.1 (#204): quorum queues are ALWAYS durable in RabbitMQ, and
        // queue_durable=false + quorum used to fail broker-side with
        // precondition_failed (bug 13's adjacent hole) — the compiler now
        // rejects it with the typed config path.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('queue_durable');

        ConnectionCompiler::compile('x', $this->config([
            'queue_durable' => false,
        ]), []);
    }

    public function test_a_queue_shared_by_two_connections_compiles_into_both_profiles(): void
    {
        $shared = [
            'driver' => 'rabbit-rs',
            'queue' => 'work',
            'hosts' => 'rabbitmq-simple:5672',
            'exchange' => 'laravel.jobs',
        ];

        $first = ConnectionCompiler::compile('first', $shared, [])['native']['workers'][0]['subscriptions'];
        $second = ConnectionCompiler::compile('second', $shared, [])['native']['workers'][0]['subscriptions'];

        // Advertised dual-consumption ("a queue defined on two targeted
        // connections is consumed on both") — the flip side of bug 8: with a
        // single all-queues profile per connection, nothing scopes it away.
        $this->assertSame('work', $first[0]['queue']);
        $this->assertSame('work', $second[0]['queue']);
    }

    public function test_package_defaults_keys_are_allow_listed_for_connections(): void
    {
        // Bug 2's fix: keys living in the package defaults (e.g. worker) must
        // ride through the merge without triggering the unknown-key rejection.
        $compiled = ConnectionCompiler::compile(
            'rabbit-rs-work',
            config('queue.connections.rabbit-rs-work'),
            config('rabbit-rs'),
        );

        $this->assertNotEmpty($compiled['native']['workers']);
    }

    public function test_classic_queues_round_trip_through_the_driver(): void
    {
        // Pre-declare a classic queue through the Management API, then push
        // and observe it through the driver. (The driver's own classic
        // declaration path runs through rabbit-rs:topology --fix, gated by
        // bug 11's 30s readiness timeout — kept out of the suite.)
        $api = 'http://rabbitmq-simple:15672';
        $declared = Http::withBasicAuth('guest', 'guest')
            ->put("{$api}/api/queues/%2F/classic-probe", [
                'durable' => true,
                'arguments' => ['x-queue-type' => 'classic'],
            ]);
        $this->assertTrue($declared->successful(), 'classic queue declare failed: '.$declared->body());

        $bound = Http::withBasicAuth('guest', 'guest')
            ->post("{$api}/api/bindings/%2F/e/laravel.jobs/q/classic-probe", ['routing_key' => 'classic-probe']);
        $this->assertTrue($bound->successful(), 'binding failed: '.$bound->body());

        try {
            config()->set('queue.connections.rabbit-rs-classic', $this->config(['queue' => 'classic-probe']));
            $this->forgetResolvedQueueConnections();

            $connection = Queue::connection('rabbit-rs-classic');
            foreach (range(1, 50) as $i) {
                $connection->push(new StressJob($i), '', 'classic-probe');
            }

            // Bug 10: the flush is asynchronous AND chunky — the bulk wave
            // lands within ~15s (observed 47-48/50), stragglers follow. Poll
            // for the bulk wave; exact wave counts belong to bug 10's repro.
            $depth = 0;
            $deadline = microtime(true) + 20;
            while ($depth < 40 && microtime(true) < $deadline) {
                sleep(1);
                $depth = (int) Http::withBasicAuth('guest', 'guest')
                    ->get("{$api}/api/queues/%2F/classic-probe")
                    ->json('messages_ready');
            }

            $this->assertGreaterThanOrEqual(40, $depth);
        } finally {
            Http::withBasicAuth('guest', 'guest')->delete("{$api}/api/queues/%2F/classic-probe");
        }
    }

    private function config(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'rabbit-rs',
            'queue' => 'work',
            'hosts' => 'rabbitmq-simple:5672',
            'vhost' => '/',
            'username' => 'guest',
            'password' => 'guest',
            'exchange' => 'laravel.jobs',
        ], $overrides);
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
