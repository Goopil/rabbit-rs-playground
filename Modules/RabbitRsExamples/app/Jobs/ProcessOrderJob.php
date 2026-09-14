<?php

namespace Modules\RabbitRsExamples\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Didactic job: one class, three queues — the connection's `routing_key =
 * {queue}` mapping does the routing, no per-queue job classes needed.
 */
class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function tags(): array
    {
        return [
            'connection:'.($this->connection ?? config('queue.default')),
            'queue:'.($this->queue ?? 'default'),
            'job:examples-order',
        ];
    }

    public function handle(): void
    {
        // A real app processes the order here; the example only proves the
        // transport delivers on the queue the publisher chose.
    }
}
