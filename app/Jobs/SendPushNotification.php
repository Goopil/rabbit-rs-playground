<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('SendPushNotification', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'device_token' => $this->payload['device_token'] ?? null,
            'title' => $this->payload['title'] ?? null,
            'body' => $this->payload['body'] ?? null,
        ]);

        sleep(1);

        Log::info('Push sent', [
            'device_token' => $this->payload['device_token'] ?? null,
        ]);
    }
}
