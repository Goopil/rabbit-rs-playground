<?php

namespace Modules\RabbitRsExamples\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Didactic job for the delay demo: with RABBIT_RS_DELAY_MODE=ttl the
 * later() publish lands in a quantized bucket queue
 * (rabbit-rs.delay.<fingerprint>.<id>.<ms>) and the broker releases it
 * when the bucket TTL expires.
 */
class DelayedReportJob implements ShouldQueue
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
            'job:examples-delayed-report',
        ];
    }

    public function handle(): void
    {
        Log::info('DelayedReportJob delivered', $this->payload);
    }
}
