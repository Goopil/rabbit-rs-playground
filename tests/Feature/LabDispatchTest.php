<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\RabbitRs\Jobs\ProcessDefaultJob;
use Tests\TestCase;

class LabDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_redis_sentinel_uses_the_selected_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/lab/dispatch', [
            'job' => 'default',
            'connection' => 'redis-sentinel',
            'queue' => 'bulk',
            'count' => 3,
        ]);

        $response->assertRedirect();
        Queue::assertPushedOn('bulk', ProcessDefaultJob::class);
        Queue::assertPushed(ProcessDefaultJob::class, 3);
    }

    public function test_rabbit_rs_uses_flat_queue_names(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/lab/dispatch', [
            'job' => 'default',
            'connection' => 'rabbit-rs',
            'queue' => 'default',
            'count' => 1,
        ]);

        $this->actingAs($user)->post('/lab/dispatch', [
            'job' => 'default',
            'connection' => 'rabbit-rs',
            'queue' => 'high-priority',
            'count' => 1,
        ]);

        $this->actingAs($user)->post('/lab/dispatch', [
            'job' => 'default',
            'connection' => 'rabbit-rs',
            'queue' => 'bulk',
            'count' => 1,
        ]);

        Queue::assertPushed(ProcessDefaultJob::class, fn ($job, $queue) => $queue === 'default');
        Queue::assertPushed(ProcessDefaultJob::class, fn ($job, $queue) => $queue === 'high-priority');
        Queue::assertPushed(ProcessDefaultJob::class, fn ($job, $queue) => $queue === 'bulk');
    }
}
