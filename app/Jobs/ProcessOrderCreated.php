<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('ProcessOrderCreated', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'order_id' => $this->payload['order_id'] ?? null,
            'customer' => $this->payload['customer'] ?? null,
            'total' => $this->payload['total'] ?? null,
        ]);

        sleep(1);

        Log::info('Order created processed', [
            'order_id' => $this->payload['order_id'] ?? null,
        ]);
    }
}
