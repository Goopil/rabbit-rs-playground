<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('SendEmailNotification', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'recipient' => $this->payload['recipient'] ?? null,
            'subject' => $this->payload['subject'] ?? null,
        ]);

        usleep(100000);

        Log::info('Email sent', [
            'recipient' => $this->payload['recipient'] ?? null,
        ]);
    }
}
