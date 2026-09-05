<?php

namespace Modules\RabbitRs\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('SendSmsNotification', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'phone' => $this->payload['phone'] ?? null,
            'message' => $this->payload['message'] ?? null,
        ]);

        Log::info('SMS sent', [
            'phone' => $this->payload['phone'] ?? null,
        ]);
    }
}
