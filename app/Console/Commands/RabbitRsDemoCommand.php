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
                            {--delay : Dispatch some jobs with delays}';

    protected $description = 'Dispatch demo jobs to RabbitMQ across all vhosts and queues';

    public function handle(): int
    {
        $setup = $this->option('setup');
        $useDelay = $this->option('delay');

        if (! in_array($setup, ['simple', 'cluster', 'both'])) {
            $this->error("Invalid setup: {$setup}. Use: simple, cluster, or both");

            return 1;
        }

        $setups = $setup === 'both' ? ['simple', 'cluster'] : [$setup];
        $dispatched = 0;

        foreach ($setups as $s) {
            $this->info("Dispatching to {$s} setup...");

            // /default vhost
            ProcessDefaultJob::dispatch(['id' => $dispatched + 1, 'message' => "Default job on {$s}"])
                ->onQueue("{$s}.default.default");
            $dispatched++;
            $this->line("  ✓ ProcessDefaultJob → {$s}.default.default");

            ProcessHighPriorityJob::dispatch(['id' => $dispatched + 1, 'message' => "High priority on {$s}"])
                ->onQueue("{$s}.default.high-priority");
            $dispatched++;
            $this->line("  ✓ ProcessHighPriorityJob → {$s}.default.high-priority");

            // /orders vhost
            ProcessOrderCreated::dispatch(['order_id' => $dispatched + 1, 'customer' => 'John Doe', 'total' => 99.99])
                ->onQueue("{$s}.orders.created");
            $dispatched++;
            $this->line("  ✓ ProcessOrderCreated → {$s}.orders.created");

            if ($useDelay) {
                ProcessOrderPaid::dispatch(['order_id' => $dispatched + 1, 'payment_method' => 'credit_card', 'amount' => 99.99])
                    ->onQueue("{$s}.orders.paid")
                    ->delay(now()->addSeconds(5));
                $this->line("  ✓ ProcessOrderPaid → {$s}.orders.paid (delayed 5s)");
            } else {
                ProcessOrderPaid::dispatch(['order_id' => $dispatched + 1, 'payment_method' => 'credit_card', 'amount' => 99.99])
                    ->onQueue("{$s}.orders.paid");
                $this->line("  ✓ ProcessOrderPaid → {$s}.orders.paid");
            }
            $dispatched++;

            ProcessOrderShipped::dispatch(['order_id' => $dispatched + 1, 'tracking_number' => 'TRK'.rand(100000, 999999), 'carrier' => 'UPS'])
                ->onQueue("{$s}.orders.shipped");
            $dispatched++;
            $this->line("  ✓ ProcessOrderShipped → {$s}.orders.shipped");

            // /notifications vhost
            SendEmailNotification::dispatch(['recipient' => 'user@example.com', 'subject' => "Welcome from {$s}"])
                ->onQueue("{$s}.notifications.email");
            $dispatched++;
            $this->line("  ✓ SendEmailNotification → {$s}.notifications.email");

            if ($useDelay) {
                SendSmsNotification::dispatch(['phone' => '+1234567890', 'message' => 'Your code: '.rand(1000, 9999)])
                    ->onQueue("{$s}.notifications.sms")
                    ->delay(now()->addSeconds(10));
                $this->line("  ✓ SendSmsNotification → {$s}.notifications.sms (delayed 10s)");
            } else {
                SendSmsNotification::dispatch(['phone' => '+1234567890', 'message' => 'Your code: '.rand(1000, 9999)])
                    ->onQueue("{$s}.notifications.sms");
                $this->line("  ✓ SendSmsNotification → {$s}.notifications.sms");
            }
            $dispatched++;

            SendPushNotification::dispatch(['device_token' => 'token_'.bin2hex(random_bytes(8)), 'title' => "Push from {$s}", 'body' => 'Hello!'])
                ->onQueue("{$s}.notifications.push");
            $dispatched++;
            $this->line("  ✓ SendPushNotification → {$s}.notifications.push");
        }

        $this->newLine();
        $this->info("Dispatched {$dispatched} jobs to ".implode(', ', $setups).' setup(s).');
        $this->info('Run "sail artisan rabbit-rs:work --queue=<worker-profile>" to consume.');
        $this->info('Worker profiles: simple.default, simple.orders, simple.notifications, cluster.default, cluster.orders, cluster.notifications');

        return 0;
    }
}
