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
                            {--delay : Dispatch some jobs with delays}';

    protected $description = 'Dispatch demo jobs to RabbitMQ across all vhosts and queues';

    public function handle(): int
    {
        $setup = $this->option('setup');
        $mode = $this->option('mode');
        $useDelay = $this->option('delay');

        if (! in_array($setup, ['simple', 'cluster', 'both'])) {
            $this->error("Invalid setup: {$setup}. Use: simple, cluster, or both");

            return 1;
        }

        if (! in_array($mode, ['single', 'combined', 'both'])) {
            $this->error("Invalid mode: {$mode}. Use: single, combined, or both");

            return 1;
        }

        $setups = $setup === 'both' ? ['simple', 'cluster'] : [$setup];
        $modes = $mode === 'both' ? ['single', 'combined'] : [$mode];
        $dispatched = 0;

        foreach ($setups as $s) {
            foreach ($modes as $m) {
                $prefix = $m === 'single' ? $s : "{$s}.all";
                $this->info("Dispatching to {$s} setup (mode: {$m})...");

                // /default vhost
                ProcessDefaultJob::dispatch(['id' => $dispatched + 1, 'message' => "Default job on {$s} ({$m})"])
                    ->onQueue("{$prefix}.default");
                $dispatched++;
                $this->line("  ✓ ProcessDefaultJob → {$prefix}.default");

                ProcessHighPriorityJob::dispatch(['id' => $dispatched + 1, 'message' => "High priority on {$s} ({$m})"])
                    ->onQueue("{$prefix}.high-priority");
                $dispatched++;
                $this->line("  ✓ ProcessHighPriorityJob → {$prefix}.high-priority");

                // /orders vhost
                ProcessOrderCreated::dispatch(['order_id' => $dispatched + 1, 'customer' => 'John Doe', 'total' => 99.99])
                    ->onQueue("{$prefix}.orders.created");
                $dispatched++;
                $this->line("  ✓ ProcessOrderCreated → {$prefix}.orders.created");

                if ($useDelay) {
                    ProcessOrderPaid::dispatch(['order_id' => $dispatched + 1, 'payment_method' => 'credit_card', 'amount' => 99.99])
                        ->onQueue("{$prefix}.orders.paid")
                        ->delay(now()->addSeconds(5));
                    $this->line("  ✓ ProcessOrderPaid → {$prefix}.orders.paid (delayed 5s)");
                } else {
                    ProcessOrderPaid::dispatch(['order_id' => $dispatched + 1, 'payment_method' => 'credit_card', 'amount' => 99.99])
                        ->onQueue("{$prefix}.orders.paid");
                    $this->line("  ✓ ProcessOrderPaid → {$prefix}.orders.paid");
                }
                $dispatched++;

                ProcessOrderShipped::dispatch(['order_id' => $dispatched + 1, 'tracking_number' => 'TRK'.rand(100000, 999999), 'carrier' => 'UPS'])
                    ->onQueue("{$prefix}.orders.shipped");
                $dispatched++;
                $this->line("  ✓ ProcessOrderShipped → {$prefix}.orders.shipped");

                // /notifications vhost
                SendEmailNotification::dispatch(['recipient' => 'user@example.com', 'subject' => "Welcome from {$s} ({$m})"])
                    ->onQueue("{$prefix}.notifications.email");
                $dispatched++;
                $this->line("  ✓ SendEmailNotification → {$prefix}.notifications.email");

                if ($useDelay) {
                    SendSmsNotification::dispatch(['phone' => '+1234567890', 'message' => 'Your code: '.rand(1000, 9999)])
                        ->onQueue("{$prefix}.notifications.sms")
                        ->delay(now()->addSeconds(10));
                    $this->line("  ✓ SendSmsNotification → {$prefix}.notifications.sms (delayed 10s)");
                } else {
                    SendSmsNotification::dispatch(['phone' => '+1234567890', 'message' => 'Your code: '.rand(1000, 9999)])
                        ->onQueue("{$prefix}.notifications.sms");
                    $this->line("  ✓ SendSmsNotification → {$prefix}.notifications.sms");
                }
                $dispatched++;

                SendPushNotification::dispatch(['device_token' => 'token_'.bin2hex(random_bytes(8)), 'title' => "Push from {$s} ({$m})", 'body' => 'Hello!'])
                    ->onQueue("{$prefix}.notifications.push");
                $dispatched++;
                $this->line("  ✓ SendPushNotification → {$prefix}.notifications.push");
            }
        }

        $this->newLine();
        $this->info("Dispatched {$dispatched} jobs to ".implode(', ', $setups).' setup(s), mode: '.implode(', ', $modes).'.');
        $this->info('Workers are managed by supervisord. Check with: docker exec <container> supervisorctl status');

        return 0;
    }
}
