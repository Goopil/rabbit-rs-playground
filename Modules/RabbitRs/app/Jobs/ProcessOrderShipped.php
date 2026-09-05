<?php

namespace Modules\RabbitRs\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderShipped implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('ProcessOrderShipped', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'order_id' => $this->payload['order_id'] ?? null,
            'tracking_number' => $this->payload['tracking_number'] ?? null,
            'carrier' => $this->payload['carrier'] ?? null,
        ]);

        Log::info('Order shipped processed', [
            'order_id' => $this->payload['order_id'] ?? null,
        ]);
    }
}
