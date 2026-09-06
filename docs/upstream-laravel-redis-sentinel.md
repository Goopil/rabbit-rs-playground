# goopil/laravel-redis-sentinel — upstream bugs & fixes

> **Status**: bugs 1 & 2 **FIXED in v1.9.0** (verified 2026-09-05 in this playground:
> fresh-instance `connections()` returns an array, `horizon:alive` exits 0 with the
> connection-level `service` config shape, live failover chaos test passes).
> Bug 3 is **NEW in v1.10.0** (`sentinel:status` detection, see below).

## Bug 1: `connections()` returns null on fresh instances

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

### Fix (root cause) — src/RedisSentinelManager.php

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

### Fix (optional hardening) — src/RedisSentinelServiceProvider.php:72

```diff
-        foreach ($app->make(RedisSentinelManager::class)->connections() as $connection) {
+        foreach ($app->make(RedisSentinelManager::class)->connections() ?? [] as $connection) {
```

### Regression test (Pest, no live Redis needed)

```php
it('returns an array of connections on a fresh instance', function () {
    expect(app(RedisSentinelManager::class)->connections())->toBeArray();
});
```

Commit: `fix: initialize connections array so fresh workers never hit null foreach`

## Bug 2: `horizon:alive` always fails with the documented config schema

`HorizonWorkerLiveness::checkSentinel()` (src/Commands/HorizonWorkerLiveness.php:62) reads:

```php
$service = config(sprintf('database.redis.%s.sentinel.service', $connectionName));
```

The config key is **nested under `sentinel`**, but the lib's own published config
(`config/phpredis-sentinel.php:174`) and the documented schema put `service` at the
**connection level**:

```php
'service' => env('REDIS_SENTINEL_SERVICE', 'master'),
```

Consequences with the documented schema:

1. `$service` is `null`
2. `getMasterAddrByName(null)` on line 72 → PHP 8.5 deprecation
   (`RedisSentinel::getMasterAddrByName(): Passing null to parameter #1 ($master) of type string is deprecated`)
3. `checkSentinel()` returns `1` → `horizon:alive` exits `1` **deterministically**, on every run,
   failover or not.

### Repro

```bash
php artisan horizon:ready    # exit 0
php artisan horizon:alive    # exit 1, always
```

Evidence log (PHP 8.5, sentinel cluster healthy, no failover involved):

```
<warning> DEPRECATED </warning> RedisSentinel::getMasterAddrByName(): Passing null to
parameter #1 ($master) of type string is deprecated in
vendor/goopil/laravel-redis-sentinel/src/Commands/HorizonWorkerLiveness.php on line 72.
sentinel: 1        # checkSentinel
connection: 0      # checkConnection — passes
```

### Suggested fix

Read the connection-level key first, keep the nested form as fallback, and guard the null
instead of passing it to the native client:

```php
$service = config(sprintf('database.redis.%s.service', $connectionName))
    ?? config(sprintf('database.redis.%s.sentinel.service', $connectionName));

if ($service === null) {
    throw new ConfigurationException(
        sprintf('database.redis.%s.service is not configured', $connectionName)
    );
}
```

### Regression test

Assert `checkSentinel()` returns `0` when the connection uses the published config shape
(`service` at connection level).

Commit: `fix: read horizon liveness service from the documented config schema`

## Bug (v1.10.0): `sentinel:status` ignores the global `database.redis.client`

### Symptom

With the standard config shape — sentinel client set **globally** in
`config/database.php` (`'client' => env('REDIS_CLIENT', 'phpredis-sentinel')`) and
sentinel connections that do **not** re-declare a per-connection `client` key —
the command reports:

```
No Redis Sentinel connection defined in `database.redis`.
```

while every other feature (connector, Octane stickiness, horizon:alive) works fine
on the same connections.

### Root cause

`SentinelStatus::sentinelConnectionNames()` (src/Commands/SentinelStatus.php:109)
only inspects the **per-connection** `client` key:

```php
if (! is_array($config) || ($config['client'] ?? null) !== 'phpredis-sentinel') {
    continue;
}
```

Laravel's config semantics make the top-level `database.redis.client` the default
for every connection unless a connection overrides it. The command's detection is
therefore blind to the documented/standard shape — same config-shape class as the
v1.9.0 `horizon:alive` bug (Bug 2 above).

### Suggested fix

```php
$globalClient = config('database.redis.client');

foreach ((array) config('database.redis') as $name => $config) {
    if (! is_array($config)) {
        continue;
    }

    $client = $config['client'] ?? $globalClient;

    if ($client !== 'phpredis-sentinel') {
        continue;
    }
    ...
}
```

### Regression test

Config with global client only (no per-connection `client`) + `sentinels` key →
`sentinel:status` lists the connection and renders its topology. Config with a
per-connection client override to `phpredis` → correctly excluded.

### Playground workaround

Declared `'client' => env('REDIS_CLIENT', 'phpredis-sentinel')` on each sentinel
connection (config/database.php) — explicit and harmless, but shouldn't be required.

### Evidence

- Playground 2026-09-06, laravel-redis-sentinel v1.10.0: "No Redis Sentinel
  connection defined" before the workaround, full topology after.
