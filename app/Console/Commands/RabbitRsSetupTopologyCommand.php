<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RabbitRsSetupTopologyCommand extends Command
{
    protected $signature = 'rabbit-rs:setup-topology';

    protected $description = 'Create RabbitMQ exchanges, queues, and bindings on both simple and cluster setups';

    private const BROKERS = [
        'simple' => [
            'host' => 'rabbitmq-simple',
            'port' => 15672,
            'user' => 'guest',
            'pass' => 'guest',
        ],
        'cluster' => [
            'host' => 'rabbitmq-1',
            'port' => 15672,
            'user' => 'guest',
            'pass' => 'guest',
        ],
    ];

    private const VHOST_EXCHANGES = [
        '/default' => 'laravel.jobs',
        '/orders' => 'laravel.orders',
        '/notifications' => 'laravel.notifications',
    ];

    private const VHOST_QUEUES = [
        '/default' => [
            'simple.default.default',
            'simple.default.high-priority',
            'cluster.default.default',
            'cluster.default.high-priority',
        ],
        '/orders' => [
            'simple.orders.created',
            'simple.orders.paid',
            'simple.orders.shipped',
            'cluster.orders.created',
            'cluster.orders.paid',
            'cluster.orders.shipped',
        ],
        '/notifications' => [
            'simple.notifications.email',
            'simple.notifications.sms',
            'simple.notifications.push',
            'cluster.notifications.email',
            'cluster.notifications.sms',
            'cluster.notifications.push',
        ],
    ];

    private const DEAD_LETTER_EXCHANGE = 'dead-letters';

    private const DEAD_LETTER_QUEUE = 'failed-jobs';

    public function handle(): int
    {
        foreach (self::BROKERS as $name => $config) {
            $this->info("Setting up topology on {$name} broker ({$config['host']})...");

            $base = "http://{$config['host']}:{$config['port']}/api";
            $auth = [$config['user'], $config['pass']];

            foreach (self::VHOST_EXCHANGES as $vhost => $exchange) {
                $encoded = urlencode($vhost);

                if (! $this->createExchange($base, $auth, $encoded, $exchange)) {
                    return 1;
                }

                if (! $this->createExchange($base, $auth, $encoded, self::DEAD_LETTER_EXCHANGE)) {
                    return 1;
                }

                if (! $this->createDeadLetterQueue($base, $auth, $encoded)) {
                    return 1;
                }

                if (! $this->bindDeadLetterQueue($base, $auth, $encoded)) {
                    return 1;
                }

                foreach (self::VHOST_QUEUES[$vhost] as $queue) {
                    if (! $this->createQuorumQueue($base, $auth, $encoded, $queue)) {
                        return 1;
                    }

                    if (! $this->bindQueue($base, $auth, $encoded, $queue, $exchange)) {
                        return 1;
                    }
                }
            }
        }

        $this->info('Topology setup completed successfully.');

        return 0;
    }

    private function createExchange(string $base, array $auth, string $vhost, string $exchange): bool
    {
        $body = json_encode([
            'type' => 'direct',
            'durable' => true,
            'auto_delete' => false,
            'internal' => false,
            'arguments' => new \stdClass,
        ]);

        $response = Http::withBasicAuth(...$auth)
            ->withBody($body, 'application/json')
            ->put("{$base}/exchanges/{$vhost}/{$exchange}");

        if ($response->status() === 201 || $response->status() === 204) {
            $this->line("  ✓ exchange {$exchange} on {$vhost}");

            return true;
        }

        $this->error("  ✗ Failed to create exchange {$exchange} on {$vhost}: {$response->status()} {$response->body()}");

        return false;
    }

    private function createDeadLetterQueue(string $base, array $auth, string $vhost): bool
    {
        $body = json_encode([
            'durable' => true,
            'auto_delete' => false,
            'arguments' => new \stdClass,
        ]);

        $response = Http::withBasicAuth(...$auth)
            ->withBody($body, 'application/json')
            ->put("{$base}/queues/{$vhost}/".self::DEAD_LETTER_QUEUE);

        if ($response->status() === 201 || $response->status() === 204) {
            $this->line('  ✓ dead-letter queue '.self::DEAD_LETTER_QUEUE." on {$vhost}");

            return true;
        }

        $this->error("  ✗ Failed to create dead-letter queue on {$vhost}: {$response->status()} {$response->body()}");

        return false;
    }

    private function bindDeadLetterQueue(string $base, array $auth, string $vhost): bool
    {
        $body = json_encode([
            'routing_key' => '#',
            'arguments' => new \stdClass,
        ]);

        $response = Http::withBasicAuth(...$auth)
            ->withBody($body, 'application/json')
            ->post("{$base}/bindings/{$vhost}/e/".self::DEAD_LETTER_EXCHANGE.'/q/'.self::DEAD_LETTER_QUEUE);

        if ($response->status() === 201 || $response->status() === 204) {
            $this->line('  ✓ bind '.self::DEAD_LETTER_QUEUE.' → '.self::DEAD_LETTER_EXCHANGE.' (routing key: #)');

            return true;
        }

        $this->error("  ✗ Failed to bind dead-letter queue on {$vhost}: {$response->status()} {$response->body()}");

        return false;
    }

    private function createQuorumQueue(string $base, array $auth, string $vhost, string $queue): bool
    {
        $body = json_encode([
            'durable' => true,
            'auto_delete' => false,
            'arguments' => [
                'x-queue-type' => 'quorum',
                'x-dead-letter-exchange' => self::DEAD_LETTER_EXCHANGE,
                'x-delivery-limit' => 20,
            ],
        ]);

        $response = Http::withBasicAuth(...$auth)
            ->withBody($body, 'application/json')
            ->put("{$base}/queues/{$vhost}/{$queue}");

        if ($response->status() === 201 || $response->status() === 204) {
            $this->line("  ✓ queue {$queue} on {$vhost}");

            return true;
        }

        $this->error("  ✗ Failed to create queue {$queue} on {$vhost}: {$response->status()} {$response->body()}");

        return false;
    }

    private function bindQueue(string $base, array $auth, string $vhost, string $queue, string $exchange): bool
    {
        $body = json_encode([
            'routing_key' => $queue,
            'arguments' => new \stdClass,
        ]);

        $response = Http::withBasicAuth(...$auth)
            ->withBody($body, 'application/json')
            ->post("{$base}/bindings/{$vhost}/e/{$exchange}/q/{$queue}");

        if ($response->status() === 201 || $response->status() === 204) {
            $this->line("  ✓ bind {$queue} → {$exchange} (routing key: {$queue})");

            return true;
        }

        $this->error("  ✗ Failed to bind queue {$queue} on {$vhost}: {$response->status()} {$response->body()}");

        return false;
    }
}
