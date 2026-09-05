<?php

namespace Modules\RabbitRs\Console;

use Illuminate\Console\Command;
use Modules\RabbitRs\Jobs\ProcessDefaultJob;
use Modules\RabbitRs\Jobs\ProcessHighPriorityJob;
use Modules\RabbitRs\Jobs\ProcessOrderCreated;
use Modules\RabbitRs\Jobs\ProcessOrderPaid;
use Modules\RabbitRs\Jobs\ProcessOrderShipped;
use Modules\RabbitRs\Jobs\SendEmailNotification;
use Modules\RabbitRs\Jobs\SendPushNotification;
use Modules\RabbitRs\Jobs\SendSmsNotification;

class RabbitRsDemoCommand extends Command
{
    protected $signature = 'rabbit-rs:demo
                            {--setup=both : Setup to use (simple, cluster, or both)}
                            {--mode=single : Mode to use (single, combined, or both)}
                            {--count=1 : Number of iterations (8 jobs per iteration per setup)}
                            {--delay : Dispatch some jobs with delays}
                            {--connection= : redis-sentinel, rabbit-rs, or both (default: QUEUE_CONNECTION)}';

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

        $connectionOption = $this->option('connection') ?: config('queue.default');
        $connections = match ($connectionOption) {
            'redis-sentinel' => ['redis-sentinel'],
            'rabbit-rs' => ['rabbit-rs'],
            'both' => ['redis-sentinel', 'rabbit-rs'],
            default => null,
        };
        if ($connections === null) {
            $this->error("Invalid --connection: {$connectionOption}. Use: redis-sentinel, rabbit-rs, or both");

            return 1;
        }

        $setups = $setup === 'both' ? ['simple', 'cluster'] : [$setup];
        $modes = $mode === 'both' ? ['single', 'combined'] : [$mode];
        $dispatched = 0;
        $totalExpected = $count * 8 * count($setups) * count($modes) * count($connections);

        $this->info("Dispatching {$totalExpected} jobs ({$count} iterations × 8 queues × ".count($setups).' setup(s) × '.count($modes).' mode(s) × '.count($connections).' connection(s))...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalExpected);
        $progressBar->start();

        for ($iter = 0; $iter < $count; $iter++) {
            foreach ($connections as $connection) {
                foreach ($setups as $s) {
                    foreach ($modes as $m) {
                        foreach (self::VHOST_QUEUES as $vhost => $queues) {
                            foreach ($queues as $queue) {
                                $job = $this->dispatchJob($vhost, $queue, $dispatched + 1, $s, $m);

                                if ($useDelay && $queue === 'paid') {
                                    $job->delay(now()->addSeconds(5));
                                } elseif ($useDelay && $queue === 'sms') {
                                    $job->delay(now()->addSeconds(10));
                                }

                                if ($connection === 'redis-sentinel') {
                                    // Horizon consumes flat queues: default vhost
                                    // keeps its own queue names, everything else
                                    // funnels into the bulk queue.
                                    $job->onConnection('redis-sentinel')->onQueue($this->redisQueueFor($vhost, $queue));
                                } else {
                                    // 0.1.0 is connection-first: pick the connection
                                    // named after the broker owning this vhost (dot
                                    // names are illegal in connection keys), the
                                    // queue name itself is the routing key ({queue}).
                                    $queueName = $m === 'single'
                                        ? "{$s}.{$vhost}.{$queue}"
                                        : "{$s}.all.{$this->combinedQueueName($vhost, $queue)}";

                                    $job->onConnection("{$s}-{$vhost}")->onQueue($queueName);
                                }

                                $dispatched++;
                                $progressBar->advance();
                            }
                        }
                    }
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Dispatched {$dispatched} jobs to ".implode(', ', $setups).' setup(s), mode: '.implode(', ', $modes).', connection(s): '.implode(', ', $connections).'.');
        $this->info('Horizon dashboard: /horizon');
        if (in_array('rabbit-rs', $connections, true)) {
            $this->line('rabbit-rs jobs stay in RabbitMQ until rabbit workers run.');
        }

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

    private function redisQueueFor(string $vhost, string $queue): string
    {
        return $vhost === 'default' ? $queue : 'bulk';
    }

    private function combinedQueueName(string $vhost, string $queue): string
    {
        if ($vhost === 'default') {
            return $queue;
        }

        return "{$vhost}.{$queue}";
    }
}
