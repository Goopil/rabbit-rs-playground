<?php

namespace Modules\RabbitRsExamples\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Didactic job for the failure demo: always throws, so the worker runs
 * the full tries → retries → failed recording path.
 */
class FlakyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $orderId
    ) {}

    public function tags(): array
    {
        return [
            'connection:'.($this->connection ?? config('queue.default')),
            'queue:'.($this->queue ?? 'default'),
            'job:examples-flaky',
        ];
    }

    public function handle(): void
    {
        throw new RuntimeException("FlakyJob: order {$this->orderId} always fails (demo)");
    }
}
