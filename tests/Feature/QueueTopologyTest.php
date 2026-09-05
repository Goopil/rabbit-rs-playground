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
        $this->assertSame('default', $connection['subscriptions']['default']['queue']);
        $this->assertSame('high-priority', $connection['subscriptions']['high-priority']['queue']);
        $this->assertSame('bulk', $connection['subscriptions']['bulk']['queue']);
    }

    public function test_horizon_supervises_the_redis_queues(): void
    {
        $supervisors = config('horizon.environments.local');

        $this->assertSame(['default', 'high-priority'], $supervisors['supervisor-horizon']['queue']);
        $this->assertSame('redis-sentinel', $supervisors['supervisor-horizon']['connection']);
        $this->assertSame(['bulk'], $supervisors['supervisor-bulk']['queue']);
        $this->assertSame('redis-sentinel', $supervisors['supervisor-bulk']['connection']);
        $this->assertArrayNotHasKey('supervisor-rabbit', $supervisors);
    }
}
