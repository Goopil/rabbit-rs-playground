<?php

namespace Modules\RabbitRs\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RabbitRsSetupVhostsCommand extends Command
{
    protected $signature = 'rabbit-rs:setup-vhosts';

    protected $description = 'Create RabbitMQ vhosts on both simple and cluster setups';

    private const VHOSTS = ['/default', '/orders', '/notifications'];

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

    public function handle(): int
    {
        foreach (self::BROKERS as $name => $config) {
            $this->info("Setting up vhosts on {$name} broker ({$config['host']})...");

            $base = "http://{$config['host']}:{$config['port']}/api";
            $auth = [$config['user'], $config['pass']];

            foreach (self::VHOSTS as $vhost) {
                $encoded = urlencode($vhost);

                $response = Http::withBasicAuth(...$auth)
                    ->withBody('{}', 'application/json')
                    ->put("{$base}/vhosts/{$encoded}");

                if ($response->status() === 201 || $response->status() === 204) {
                    $this->line("  ✓ vhost {$vhost} created or already exists");
                } else {
                    $this->error("  ✗ Failed to create vhost {$vhost}: {$response->status()} {$response->body()}");

                    return 1;
                }

                $permissions = [
                    'configure' => '.*',
                    'write' => '.*',
                    'read' => '.*',
                ];

                $permResponse = Http::withBasicAuth(...$auth)
                    ->withBody(json_encode($permissions), 'application/json')
                    ->put("{$base}/permissions/{$encoded}/{$config['user']}");

                if ($permResponse->status() === 201 || $permResponse->status() === 204) {
                    $this->line("  ✓ permissions set for {$config['user']} on {$vhost}");
                } else {
                    $this->warn("  ! Could not set permissions on {$vhost}: {$permResponse->status()}");
                }
            }
        }

        $this->info('All vhosts created successfully.');

        return 0;
    }
}
