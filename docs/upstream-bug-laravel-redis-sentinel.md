# Upstream bug — goopil/laravel-redis-sentinel

## Bug 1: `horizon:alive` always fails with the documented config schema

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

## Bug 2: `connections()` returns null on fresh instances

See `docs/upstream-fix-laravel-redis-sentinel.md` — `RedisManager::$connections` is
uninitialized in current Laravel versions, so the Octane stickiness reset crashes with
`foreach() argument must be of type array|object, null given` on fresh workers.
