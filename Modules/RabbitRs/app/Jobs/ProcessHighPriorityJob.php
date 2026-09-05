<?php

namespace Modules\RabbitRs\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessHighPriorityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('ProcessHighPriorityJob', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'payload' => $this->payload,
        ]);

        Log::info('ProcessHighPriorityJob completed', [
            'id' => $this->payload['id'] ?? null,
        ]);
    }
}
