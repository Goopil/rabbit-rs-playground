<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\QueueLab\Jobs\StressJob;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Live broker topology behavior that compile-time pins cannot cover. The
 * Management API is the ground truth for every assertion.
 *
 * @group upstream
 */
class RabbitRsLiveTopologyTest extends TestCase
{
    private const API = 'http://rabbitmq-simple:15672';

    public function test_poison_deliveries_dead_letter_to_failed_jobs(): void
    {
        $this->drainAndSettleWork();
        $before = $this->queueDepth('failed-jobs');

        // A payload the driver cannot marshal into a job (published straight
        // through the Management API, bypassing the driver's envelope) is a
        // poison delivery: pop() must settle it terminally — reject with
        // requeue=false — so it flows to the dead-letter exchange and lands
        // in failed-jobs (RabbitMqQueue::settleUnmarshable).
        // properties must encode as a JSON OBJECT: PHP's [] encodes as [],
        // which RabbitMQ's publish API rejects with a 500 (curl sends {}).
        $routed = Http::withBasicAuth('guest', 'guest')
            ->post(self::API.'/api/exchanges/%2F/laravel.jobs/publish', [
                'properties' => (object) [],
                'routing_key' => 'work',
                'payload' => 'definitely-not-a-laravel-envelope',
                'payload_encoding' => 'string',
            ]);
        $this->assertTrue($routed->json('routed') === true, 'poison publish was not routed');

        // Pop-and-drop everything (including valid-envelope residue published
        // by earlier tests' child teardowns — bug 12.4's untimed early
        // publish) until the queue is exhausted: the poison must be settled
        // terminally inside pop(), never returned as a job. The failed-jobs
        // depth assert below is the authoritative regression detector.
        $queue = Queue::connection('rabbit-rs-work');
        for ($i = 0; $i < 5; $i++) {
            $job = $queue->pop('work');
            if ($job === null) {
                break;
            }
            $job->delete();
        }
        $this->assertNull($queue->pop('work'));

        $deadline = microtime(true) + 10;
        while ($this->queueDepth('failed-jobs') < $before + 1 && microtime(true) < $deadline) {
            usleep(500000);
        }

        $this->assertSame($before + 1, $this->queueDepth('failed-jobs'));
    }

    public function test_two_connections_consume_the_same_physical_queue(): void
    {
        config()->set('queue.connections.rabbit-rs-work-2', [
            'driver' => 'rabbit-rs',
            'queue' => 'work',
            'hosts' => 'rabbitmq-simple:5672',
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
        ]);
        $this->forgetResolvedQueueConnections();
        $this->drainAndSettleWork();

        $first = Queue::connection('rabbit-rs-work');
        $second = Queue::connection('rabbit-rs-work-2');

        foreach (range(1, 50) as $i) {
            $first->push(new StressJob($i), '', 'work');
        }

        // Bug 10: the flush is asynchronous and chunky — wait for the bulk
        // wave before popping.
        $deadline = microtime(true) + 20;
        while ($this->queueDepth('work') < 40 && microtime(true) < $deadline) {
            sleep(1);
        }

        $retrieved = ['first' => 0, 'second' => 0];
        $deadline = microtime(true) + 30;
        while (array_sum($retrieved) < 50 && $this->queueDepth('work') > 0 && microtime(true) < $deadline) {
            foreach (['first' => $first, 'second' => $second] as $side => $connection) {
                $job = $connection->pop('work');
                if ($job !== null) {
                    $retrieved[$side]++;
                    $job->delete();
                }
            }
        }

        // Competing consumers: every message goes to exactly ONE connection,
        // but both connections can pop the same physical queue (doc bug 8's
        // flip side — queue access is not connection-scoped).
        $this->assertGreaterThanOrEqual(40, array_sum($retrieved));
        $this->assertGreaterThanOrEqual(1, $retrieved['second']);
    }

    public function test_classic_queues_reject_the_inherited_delivery_limit_at_declare(): void
    {
        // Bug 13: the compiler emits x-delivery-limit for EVERY queue type,
        // but RabbitMQ only accepts it on quorum queues — a classic queue
        // with the inherited delivery_limit default can never be declared:
        // PRECONDITION_FAILED (broker log: "invalid arg 'x-delivery-limit'
        // … of queue type rabbit_classic_queue"). The guard below flips to a
        // positive declare assertion once upstream drops the arg for classic.
        config()->set('queue.connections.rabbit-rs-nondurable', [
            'driver' => 'rabbit-rs',
            'queue' => 'classic-probe',
            'hosts' => 'rabbitmq-simple:5672',
            'exchange' => 'laravel.jobs',
            'queue_type' => 'classic',
            'queue_durable' => false,
            'topology_mode' => 'declare',
        ]);

        Artisan::call('rabbit-rs:topology', [
            '--connection' => ['rabbit-rs-nondurable'],
            '--fix' => true,
            '--force' => true,
        ]);

        // Bug 11's readiness gate runs 30s first — give the declare plenty of
        // slack, then assert the queue never reached the broker.
        sleep(3);
        $deadline = microtime(true) + 5;
        do {
            $exists = Http::withBasicAuth('guest', 'guest')
                ->get(self::API.'/api/queues/%2F/classic-probe')
                ->successful();
            if ($exists) {
                break;
            }
            usleep(1000000);
        } while (microtime(true) < $deadline);

        $this->assertFalse($exists, 'classic queue declared despite x-delivery-limit (bug 13 fixed upstream — flip this guard)');
    }

    public function test_the_driver_declares_a_coherent_dlx_pair(): void
    {
        // The driver's DLX contract (routing_key = null): direct exchange,
        // x-dead-letter-routing-key = the MAIN queue's name on the source
        // queue, and the DLQ bound to the DLX with that same key — a coherent
        // per-queue pair. A rejected delivery must land in the DLQ.
        config()->set('queue.connections.rabbit-rs-dlx-probe', [
            'driver' => 'rabbit-rs',
            'queue' => 'dlx-probe-main',
            'hosts' => 'rabbitmq-simple:5672',
            'exchange' => 'laravel.jobs',
            'queue_type' => 'quorum',
            'dead_letter' => ['exchange' => 'dlx-probe-dlx', 'queue' => 'dlx-probe-dead', 'routing_key' => null],
            'topology_mode' => 'declare',
        ]);

        Artisan::call('rabbit-rs:topology', [
            '--connection' => ['rabbit-rs-dlx-probe'],
            '--fix' => true,
            '--force' => true,
        ]);

        try {
            $main = Http::withBasicAuth('guest', 'guest')->get(self::API.'/api/queues/%2F/dlx-probe-main');
            $this->assertTrue($main->successful(), 'driver-declared main queue missing');

            $arguments = $main->json('arguments');
            $this->assertSame('dlx-probe-dlx', $arguments['x-dead-letter-exchange'] ?? null);
            $this->assertSame('dlx-probe-main', $arguments['x-dead-letter-routing-key'] ?? null);

            $dlxType = Http::withBasicAuth('guest', 'guest')
                ->get(self::API.'/api/exchanges/%2F/dlx-probe-dlx')
                ->json('type');
            $bindingKeys = collect(Http::withBasicAuth('guest', 'guest')
                ->get(self::API.'/api/queues/%2F/dlx-probe-dead/bindings')
                ->json())->pluck('routing_key');
            $this->assertSame('direct', $dlxType);
            $this->assertTrue($bindingKeys->contains('dlx-probe-main'), 'DLQ not bound with the main queue name');

            // Finding: --fix declares the queue + the DLX chain but NOT the
            // publish-side route binding (queue ← connection exchange) — a
            // driver-only declare leaves the queue unreachable for pushes
            // (routed=false). Bind it manually so the canary can flow.
            $bound = Http::withBasicAuth('guest', 'guest')
                ->post(self::API.'/api/bindings/%2F/e/laravel.jobs/q/dlx-probe-main', [
                    'routing_key' => 'dlx-probe-main',
                ]);
            $this->assertTrue($bound->successful(), 'manual route binding failed');

            $routed = Http::withBasicAuth('guest', 'guest')
                ->post(self::API.'/api/exchanges/%2F/laravel.jobs/publish', [
                    'properties' => (object) [],
                    'routing_key' => 'dlx-probe-main',
                    'payload' => 'dlx-contract-canary',
                    'payload_encoding' => 'string',
                ]);
            $this->assertTrue($routed->json('routed') === true, 'canary publish not routed');

            $rejected = Http::withBasicAuth('guest', 'guest')
                ->post(self::API.'/api/queues/%2F/dlx-probe-main/get', [
                    'count' => 1,
                    'ackmode' => 'reject_requeue_false',
                    'encoding' => 'auto',
                    'truncate' => 60,
                ]);
            $this->assertCount(1, $rejected->json() ?? [], 'canary not fetched for terminal reject');

            $deadline = microtime(true) + 10;
            do {
                sleep(1);
                $dead = Http::withBasicAuth('guest', 'guest')
                    ->post(self::API.'/api/queues/%2F/dlx-probe-dead/get', [
                        'count' => 5,
                        'ackmode' => 'ack_requeue_true',
                        'encoding' => 'auto',
                        'truncate' => 80,
                    ])->json() ?? [];
            } while ($dead === [] && microtime(true) < $deadline);

            $this->assertNotEmpty($dead, 'rejected canary never reached the DLQ');
            $this->assertSame('dlx-contract-canary', $dead[0]['payload'] ?? null);
        } finally {
            Http::withBasicAuth('guest', 'guest')->delete(self::API.'/api/queues/%2F/dlx-probe-main');
            Http::withBasicAuth('guest', 'guest')->delete(self::API.'/api/queues/%2F/dlx-probe-dead');
            Http::withBasicAuth('guest', 'guest')->delete(self::API.'/api/exchanges/%2F/dlx-probe-dlx');
        }
    }

    private function queueDepth(string $queue): int
    {
        return (int) Http::withBasicAuth('guest', 'guest')
            ->get(self::API."/api/queues/%2F/{$queue}")
            ->json('messages_ready');
    }

    private function drainAndSettleWork(): void
    {
        Queue::connection('rabbit-rs-work')->clear('work');
        Queue::connection('rabbit-rs-work')->size('work');

        // Stable-depth loop: async flushes from earlier tests land 1-5s after
        // publishing (bug 10), so a fixed sleep can still race a late flush.
        $previous = -1;
        $depth = $this->queueDepth('work');
        $deadline = microtime(true) + 10;
        while (($depth !== $previous || $depth > 0) && microtime(true) < $deadline) {
            usleep(500000);
            $previous = $depth;
            $depth = $this->queueDepth('work');
        }
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
