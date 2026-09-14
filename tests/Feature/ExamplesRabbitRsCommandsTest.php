<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Modules\RabbitRsExamples\Jobs\DelayedReportJob;
use Modules\RabbitRsExamples\Jobs\FlakyJob;
use Modules\RabbitRsExamples\Jobs\ProcessOrderJob;
use Tests\TestCase;

/**
 * The examples:* commands show intended lib usage — this suite guards the
 * dispatch surface (job, connection, queue, tags) with Queue::fake.
 */
class ExamplesRabbitRsCommandsTest extends TestCase
{
    public function test_dispatch_command_routes_one_order_job_per_queue_and_connection(): void
    {
        Queue::fake();

        Artisan::call('examples:rabbit-rs:dispatch', ['--count' => 1]);

        Queue::assertPushed(ProcessOrderJob::class, 6); // 3 queues × 2 connections
        Queue::assertPushed(ProcessOrderJob::class, fn ($job, $queue) => in_array($queue, ['default', 'high-priority', 'bulk']));
        Queue::assertPushed(ProcessOrderJob::class, fn ($job) => in_array($job->connection ?? 'redis-sentinel', ['redis-sentinel', 'rabbit-rs']));
    }

    public function test_dispatch_command_rejects_an_unknown_connection(): void
    {
        $exitCode = Artisan::call('examples:rabbit-rs:dispatch', ['--connection' => 'nope']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid --connection', Artisan::output());
    }

    public function test_delay_command_pushes_a_delayed_report_job(): void
    {
        Queue::fake();

        Artisan::call('examples:rabbit-rs:delay', ['--seconds' => 30]);

        Queue::assertPushed(DelayedReportJob::class, 1);
    }

    public function test_fail_command_pushes_a_flaky_job_on_the_bulk_queue(): void
    {
        Queue::fake();

        Artisan::call('examples:rabbit-rs:fail');

        Queue::assertPushedOn('bulk', FlakyJob::class);
    }
}
