<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDefaultJob;
use App\Jobs\ProcessHighPriorityJob;
use App\Jobs\ProcessOrderCreated;
use App\Jobs\ProcessOrderPaid;
use App\Jobs\ProcessOrderShipped;
use App\Jobs\SendEmailNotification;
use App\Jobs\SendPushNotification;
use App\Jobs\SendSmsNotification;
use Illuminate\Console\Command;

class RabbitRsDemoCommand extends Command
{
    protected $signature = 'rabbit-rs:demo
                            {--setup=both : Setup to use (simple, cluster, or both)}
                            {--mode=single : Mode to use (single, combined, or both)}
                            {--count=1 : Number of iterations (8 jobs per iteration per setup)}
                            {--delay : Dispatch some jobs with delays}';

    protected $description = 'Dispatch demo jobs to RabbitMQ across all vhosts and queues';

    private const VHOST_QUEUES = [
        'default' => ['default', 'high-priority'],
        'orders' => ['created', 'paid', 'shipped'],
        'notifications' => ['email', 'sms', 'push'],
    ];

    public function handle(): int
    {
        $setup = $this->option('setup');
        $mode = $this->option('mode');
        $count = (int) $this->option('count');
        $useDelay = $this->option('delay');

        if (! in_array($setup, ['simple', 'cluster', 'both'])) {
            $this->error("Invalid setup: {$setup}. Use: simple, cluster, or both");

            return 1;
        }

        if (! in_array($mode, ['single', 'combined', 'both'])) {
            $this->error("Invalid mode: {$mode}. Use: single, combined, or both");

            return 1;
        }

        if ($count < 1) {
            $this->error("Invalid count: {$count}. Must be >= 1");

            return 1;
        }

        $setups = $setup === 'both' ? ['simple', 'cluster'] : [$setup];
        $modes = $mode === 'both' ? ['single', 'combined'] : [$mode];
        $dispatched = 0;
        $totalExpected = $count * 8 * count($setups) * count($modes);

        $this->info("Dispatching {$totalExpected} jobs ({$count} iterations × 8 queues × ".count($setups).' setup(s) × '.count($modes).' mode(s))...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalExpected);
        $progressBar->start();

        for ($iter = 0; $iter < $count; $iter++) {
            foreach ($setups as $s) {
                foreach ($modes as $m) {
                    foreach (self::VHOST_QUEUES as $vhost => $queues) {
                        foreach ($queues as $queue) {
                            $queueName = $m === 'single'
                                ? "{$s}.{$vhost}.{$queue}"
                                : "{$s}.all.{$this->combinedQueueName($vhost, $queue)}";

                            $job = $this->dispatchJob($vhost, $queue, $dispatched + 1, $s, $m);

                            if ($useDelay && $queue === 'paid') {
                                $job->delay(now()->addSeconds(5));
                            } elseif ($useDelay && $queue === 'sms') {
                                $job->delay(now()->addSeconds(10));
                            }

                            // 0.1.0 is connection-first: pick the connection
                            // named after the broker owning this vhost (dot
                            // names are illegal in connection keys), the
                            // queue name itself is the routing key ({queue}).
                            $job->onConnection("{$s}-{$vhost}")->onQueue($queueName);
                            $dispatched++;
                            $progressBar->advance();
                        }
                    }
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Dispatched {$dispatched} jobs to ".implode(', ', $setups).' setup(s), mode: '.implode(', ', $modes).'.');
        $this->info('Workers are managed by supervisord. Check with: make workers-status');

        return 0;
    }

    private function dispatchJob(string $vhost, string $queue, int $id, string $setup, string $mode)
    {
        $label = "{$setup} ({$mode})";

        return match ($vhost.'.'.$queue) {
            'default.default' => ProcessDefaultJob::dispatch(['id' => $id, 'message' => "Default job #{$id} on {$label}"]),
            'default.high-priority' => ProcessHighPriorityJob::dispatch(['id' => $id, 'message' => "High priority #{$id} on {$label}"]),
            'orders.created' => ProcessOrderCreated::dispatch(['order_id' => $id, 'customer' => 'John Doe', 'total' => 99.99]),
            'orders.paid' => ProcessOrderPaid::dispatch(['order_id' => $id, 'payment_method' => 'credit_card', 'amount' => 99.99]),
            'orders.shipped' => ProcessOrderShipped::dispatch(['order_id' => $id, 'tracking_number' => 'TRK'.rand(100000, 999999), 'carrier' => 'UPS']),
            'notifications.email' => SendEmailNotification::dispatch(['recipient' => 'user@example.com', 'subject' => "Welcome #{$id} from {$label}"]),
            'notifications.sms' => SendSmsNotification::dispatch(['phone' => '+1234567890', 'message' => 'Your code: '.rand(1000, 9999)]),
            'notifications.push' => SendPushNotification::dispatch(['device_token' => 'token_'.bin2hex(random_bytes(8)), 'title' => "Push #{$id} from {$label}", 'body' => 'Hello!']),
            default => ProcessDefaultJob::dispatch(['id' => $id, 'message' => "Unknown job #{$id} on {$label}"]),
        };
    }

    private function combinedQueueName(string $vhost, string $queue): string
    {
        if ($vhost === 'default') {
            return $queue;
        }

        return "{$vhost}.{$queue}";
    }
}
