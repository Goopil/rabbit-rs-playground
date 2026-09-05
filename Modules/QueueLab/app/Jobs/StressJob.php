<?php

namespace Modules\QueueLab\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $id,
        public int $sleepMs = 0,
        public int $failEvery = 0,
    ) {}

    public function tags(): array
    {
        return [
            'connection:'.($this->connection ?? config('queue.default')),
            'queue:'.($this->queue ?? 'default'),
            'job:stress',
        ];
    }

    public function handle(): void
    {
        if ($this->sleepMs > 0) {
            usleep($this->sleepMs * 1000);
        }

        if ($this->failEvery > 0 && $this->id % $this->failEvery === 0) {
            throw new \RuntimeException("StressJob #{$this->id} simulated failure");
        }
    }
}
