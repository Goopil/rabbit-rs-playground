# Upstream fix — goopil/laravel-redis-sentinel

## Bug

On fresh processes (new Octane workers, fresh artisan runs), `resetAllStickiness()`
crashes with `foreach() argument must be of type array|object, null given` at
`src/RedisSentinelServiceProvider.php:72`.

Root cause: current `Illuminate\Redis\RedisManager` declares
`protected $connections;` without a default, so `connections()` returns `null`
until a connection is resolved. The package's Octane `RequestReceived` listener
iterates it before any connection exists.

Reproduction:

```bash
php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); var_dump(\$app->make(Goopil\LaravelRedisSentinel\RedisSentinelManager::class)->connections());"
// NULL
```

## Fix 1 (root cause) — src/RedisSentinelManager.php

```diff
 class RedisSentinelManager extends RedisManager
 {
     private const HORIZON_REDIS_CONNECTOR = 'Laravel\\Horizon\\Connectors\\RedisConnector';

     /**
      * Cache for horizon context.
      */
     protected ?bool $isHorizonContext = null;
+
+    /**
+     * Initialized so connections() always returns an array — some Laravel
+     * versions leave RedisManager::$connections uninitialized, which makes
+     * consumers (e.g. the Octane stickiness reset) crash on fresh workers.
+     */
+    protected $connections = [];
```

## Fix 2 (optional hardening) — src/RedisSentinelServiceProvider.php:72

```diff
-        foreach ($app->make(RedisSentinelManager::class)->connections() as $connection) {
+        foreach ($app->make(RedisSentinelManager::class)->connections() ?? [] as $connection) {
```

## Regression test (Pest, no live Redis needed)

```php
it('returns an array of connections on a fresh instance', function () {
    expect(app(RedisSentinelManager::class)->connections())->toBeArray();
});
```

## Commit

`fix: initialize connections array so fresh workers never hit null foreach`

Run `composer lint` before `composer test` (CI gate).
