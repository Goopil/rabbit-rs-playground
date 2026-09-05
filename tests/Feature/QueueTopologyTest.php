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
        $this->assertSame(['default', 'high-priority'], config('horizon.environments.local.supervisor-horizon.queue'));
        $this->assertSame('redis-sentinel', config('horizon.environments.local.supervisor-horizon.connection'));
    }
}
