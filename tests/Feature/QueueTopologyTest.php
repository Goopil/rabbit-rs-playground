<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueueTopologyTest extends TestCase
{
    public function test_redis_sentinel_connection_is_configured(): void
    {
        $connection = config('queue.connections.redis-sentinel');

        $this->assertNotNull($connection);
        $this->assertSame('default', $connection['connection']);
    }

    public function test_rabbit_rs_connection_uses_flat_queue_names(): void
    {
        $connection = config('queue.connections.rabbit-rs');

        $this->assertNotNull($connection);
        $this->assertSame('rabbit-rs', $connection['driver']);
        $this->assertSame('default', $connection['queue']);
        $this->assertSame('laravel.jobs', $connection['exchange']);
        $this->assertSame(['default', 'high-priority', 'bulk'], array_keys($connection['subscriptions']));
    }

    public function test_rabbit_rs_consumer_groups_are_isolated_per_connection(): void
    {
        $legacy = array_keys(config('queue.connections.rabbit-rs.subscriptions'));
        $workQueue = config('queue.connections.rabbit-rs-work.queue');
        $iaQueues = array_keys(config('queue.connections.rabbit-rs-ia.subscriptions'));

        $this->assertSame('rabbit-rs', config('queue.connections.rabbit-rs-work.driver'));
        $this->assertSame('rabbit-rs', config('queue.connections.rabbit-rs-ia.driver'));
        $this->assertSame(['default', 'high-priority', 'bulk'], $legacy);
        $this->assertSame('work', $workQueue);
        $this->assertSame(['ia-summary', 'ia-embed'], $iaQueues);

        $groups = [...$legacy, ...[$workQueue], ...$iaQueues];
        $this->assertSame(
            ['default', 'high-priority', 'bulk', 'work', 'ia-summary', 'ia-embed'],
            $groups,
            'Every defined queue must belong to exactly one consumer group (connection)',
        );
        $this->assertSame(
            [],
            array_intersect(config('horizon.environments.local.supervisor-rabbit.queue'), [...$iaQueues, $workQueue]),
            'Horizon supervisor-rabbit must not consume the work/ia queues',
        );
    }

    public function test_horizon_supervises_all_queues_on_both_transports(): void
    {
        $supervisors = config('horizon.environments.local');

        $this->assertSame(['default', 'high-priority'], $supervisors['supervisor-horizon']['queue']);
        $this->assertSame('redis-sentinel', $supervisors['supervisor-horizon']['connection']);
        $this->assertSame(['bulk'], $supervisors['supervisor-bulk']['queue']);
        $this->assertSame('redis-sentinel', $supervisors['supervisor-bulk']['connection']);

        // The legacy rabbit-rs queues may be split across several supervisors
        // (local runs supervisor-rabbit + supervisor-rabbit-bulk), but every
        // one of them must stay on a rabbit-rs supervisor — the work/ia queues
        // belong to their own consumer-group connections.
        $rabbitQueues = [
            ...$supervisors['supervisor-rabbit']['queue'],
            ...($supervisors['supervisor-rabbit-bulk']['queue'] ?? []),
        ];
        sort($rabbitQueues);

        $this->assertSame(['bulk', 'default', 'high-priority'], $rabbitQueues);
        $this->assertSame('rabbit-rs', $supervisors['supervisor-rabbit']['connection']);
        $this->assertSame('rabbit-rs', $supervisors['supervisor-rabbit-bulk']['connection']);
    }
}
