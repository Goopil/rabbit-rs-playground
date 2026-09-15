<?php

namespace Modules\SentinelExamples\Support;

use Goopil\LaravelRedisSentinel\Connectors\RedisSentinelConnector;
use Goopil\LaravelRedisSentinel\RedisSentinelManager;
use Throwable;

/**
 * App-side snapshot of what the sentinel driver resolved for THIS process —
 * the copyable core is three lines: resolveConnector() → createSentinel() →
 * master($service). Consumed by the whoami command and the /lab/examples
 * sentinel endpoint. Distinct from the package's own `sentinel:status`
 * (full diagnostics): this shows what an application asks for.
 */
class SentinelWhoami
{
    /**
     * @return array{service: string, sentinels: list<string>, master: string, role: ?string, connection: string, ok: bool}
     */
    public static function capture(): array
    {
        $manager = app(RedisSentinelManager::class);
        $config = (array) config('database.redis.default');
        $service = RedisSentinelConnector::serviceFromConfig($config);

        $connector = $manager->resolveConnector('default');
        $sentinel = $connector->createSentinel('default');

        $master = (array) $sentinel->master($service);
        $connection = $manager->resolve('default');
        $info = (array) ($connection->info('REPLICATION') ?? []);

        return [
            'service' => $service,
            'sentinels' => collect((array) ($config['sentinels'] ?? []))
                ->map(fn ($s) => ($s['host'] ?? '?').':'.($s['port'] ?? '?'))
                ->all(),
            'master' => ($master['ip'] ?? '?').':'.($master['port'] ?? '?'),
            'role' => $info['role'] ?? null,
            'connection' => $info['master_host'] ?? '?',
            'ok' => true,
        ];
    }

    /**
     * @return array{service: string, sentinels: list<string>, master: string, role: ?string, connection: string, ok: bool}
     */
    public static function captureOrError(): array
    {
        try {
            return self::capture();
        } catch (Throwable $e) {
            return [
                'service' => 'mymaster',
                'sentinels' => [],
                'master' => '?',
                'role' => null,
                'connection' => 'unreachable: '.$e->getMessage(),
                'ok' => false,
            ];
        }
    }
}
