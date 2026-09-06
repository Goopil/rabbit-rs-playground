# Upstream bugs — goopil/rabbit-rs-laravel

> **Status: ALL FOUR FIXED in v0.1.1** (lib) + `rabbit-rs-native` 0.1.1 (extension),
> verified 2026-09-05 in this playground:
> 1. `readyNow()` ships upstream on `Horizon\RabbitMqQueue` — local vendor patch removed.
> 2. `worker` now falls back to the package defaults (`$config['worker'] ?? $this->defaults['worker'] ?? 'default'`) — the connection-level workaround was removed from `config/queue.php`.
> 3. `publish deadline expired` after idle: 10/10 publishes through an Octane worker idle ~25 min, zero exceptions (was 1 failure then stall on v0.1.0).
> 4. Failed rabbit-rs jobs now land in Horizon's `failed_jobs` zset with `status: failed` (6/6 after `maxTries=1` exhaustion; they also appear in Failed Jobs in the dashboard).
> Kept below for the record.

## Bug: `Horizon\RabbitMqQueue` missing `readyNow()` — Horizon supervisor crash-loop

### Symptom

With a Horizon supervisor consuming the `rabbit-rs` connection, the supervisor
process dies on its first loop iteration (jobs never consumed, 0 consumers on
all AMQP queues):

```
Error: Call to undefined method Goopil\RabbitRs\Laravel\Horizon\RabbitMqQueue::readyNow()
  at vendor/laravel/horizon/src/AutoScaler.php:81
```

### Root cause

`Supervisor::loop()` calls `autoScale()` every iteration — **regardless of the
`balance` option** (the `balance !== 'simple'` guard lives in
`SupervisorOptions::autoScaling()`, which `AutoScaler` never consults before
measuring). `AutoScaler::timeToClearPerQueue()` then calls
`readyNow($queue)` on the connection's queue instance.

`readyNow()` only exists on `Laravel\Horizon\RedisQueue` (the Redis
implementation returns `LLEN` of the queue). `Horizon\RabbitMqQueue` extends
the base `RabbitMqQueue`, so any Horizon supervisor pointed at the connection
crashes — `balance=auto` or `simple`, both.

### Suggested fix

One method, delegating to the standard queue-size contract:

```php
public function readyNow($queue = null)
{
    return $this->size($queue);
}
```


(`size()` is already implemented per the `Illuminate\Contracts\Queue\Queue`
contract — AMQP queue depth via `rabbit_rs`.)

### Playground workaround

A **local vendor patch** adds exactly the fix above to
`vendor/goopil/rabbit-rs-laravel/src/Horizon/RabbitMqQueue.php` (marked
`LOCAL VENDOR PATCH`, overwritten by `composer install`). With it, a Horizon
supervisor consumes the `rabbit-rs` connection normally (`balance=simple`,
jobs recorded end-to-end in the dashboard).

### Regression test

Configure a Horizon supervisor on a rabbit-rs connection (`balance=auto` and
`simple`), run `php artisan horizon`, and assert the supervisor stays up and
`rabbitmqctl list_queues` shows `consumers > 0`.

## Bug: `worker` key not inherited from cross-cutting defaults

`RabbitMqConnector::connect()` reads `$config['worker']` from the **raw
connection config** (config/queue.php) to pick `Horizon\RabbitMqQueue` vs
`RabbitMqQueue` — but the cross-cutting defaults from `config/rabbit-rs.php`
are only merged by `ConnectionCompiler::compile()` for the compiled native
config, not for this lookup. Result: setting `RABBIT_RS_WORKER=horizon` in
`config/rabbit-rs.php` (the documented place for cross-cutting keys) has no
effect unless the connection itself also declares `'worker' => ...`.

### Suggested fix

Merge `$this->defaults` into `$config` before the `worker` lookup (or read
`$compiled['worker']`), so all cross-cutting keys behave uniformly.

### Playground workaround

`config/queue.php` declares `'worker' => env('RABBIT_RS_WORKER', 'default')`
on the connection itself.

The readyNow() supervisor fix above also depends on this: the
`Horizon\RabbitMqQueue` class is only selected when `worker=horizon` resolves.

## Bug: `publish deadline expired` on first publish after idle (long-lived workers)

In a long-lived process (Octane worker keeping the app in memory), the **first** publish
through a rabbit-rs connection fails with:

```
Goopil\RabbitRs\Laravel\Exceptions\QueueException(code: 0): publish deadline expired
  at vendor/goopil/rabbit-rs-laravel/src/Exceptions/QueueException.php:14
  fromNative() at RabbitMqQueue.php:522 → publish() at RabbitMqQueue.php:179
```

### Context

- Config: `safety=safe` (confirms + mandatory), `confirm_timeout=30000` ms, `heartbeat=30`,
  `topology_mode=external`.
- RabbitMQ brokers healthy throughout (all nodes healthy, management API responding 200).
- The failing publish was the **first one through that Octane worker after ~6 minutes idle**.
- Every subsequent publish succeeded (messages landed, no further exceptions), including
  during a Redis Sentinel failover happening on other connections.

### Reproduction sketch

1. Boot an Octane (or any long-lived) worker, publish a job via a rabbit-rs connection → OK.
2. Let the process idle longer than the broker/heartbeat idle window.
3. Publish again → `publish deadline expired` (30 s stall), the next publish works.

A stale/half-open native pool connection is not detected, and the deadline
(`confirm_timeout`) expires before the pool re-establishes the connection.

### Impact

Synchronous dispatch loops abort on the first throw — a UI batch of 10 jobs dispatched 0
(or 1) instead of 10, and the user request 500s. Requires a retry at the caller.

### Suggested fix

In the native pool (or `RabbitMqQueue::publish`), recover instead of surfacing the deadline:

- mark the connection dead on publish deadline / transport error and retry **once** on a
  fresh connection (transparent to the caller), or
- background heartbeat/keepalive of pool connections when idle, or
- at minimum, emit a typed `ConnectionLost` event so callers can retry cheaply.

### Regression test

Park a connected pool longer than the heartbeat window (or inject a closed connection),
publish once, and assert the publish succeeds (or retries) instead of throwing
`publish deadline expired`.

## Bug: exhausted rabbit-rs jobs are recorded "completed" in Horizon, not failed

### Symptom

With `worker=horizon`, a job that exhausts its retries (`maxTries=3`) on the
rabbit-rs connection:

- IS persisted in the `failed_jobs` table (framework path works),
- but shows **status=completed** in Horizon's Recent Jobs page,
- and never appears in Horizon's Failed Jobs page (`failed_jobs` zset).

Observed: 4 redis-sentinel failures correctly listed; 3 rabbit-rs failures of
the same batch missing from the zset while marked `completed` (with
`completed_at` set) in their job hashes.

### Root cause (sketch)

`Illuminate\Queue\Jobs\Job::fail()` calls `$this->delete()` **before** raising
the `JobFailed` event. `Horizon\RabbitMqQueue::delete()` fires Horizon's
`JobDeleted` event, and Horizon's `MarkJobAsComplete` listener marks the job
completed and adds it to `completed_jobs`. Horizon's `MarkJobAsFailed` then
either does not run or is superseded by the completed state. Horizon's own
`RedisQueue` job (`Laravel\Horizon\Jobs\RedisJob`) overrides `delete()/fail()`
to avoid this ordering problem.

### Suggested fix

Follow Horizon's RedisJob implementation: in the rabbit-rs `RabbitMqJob`,
override `fail($e)` to record the failure (or set a flag) before delegating to
`delete()`, so `MarkJobAsFailed` wins over the `JobDeleted`-triggered
"completed" status — e.g. delete the message with a `nack`-style handling that
skips firing `JobDeleted` when the job is failing.

### Regression test

Dispatch a failing job (`maxTries=1`) on the rabbit-rs connection with
`worker=horizon`; assert the job appears in Horizon's `failed_jobs` zset with
status `failed` and NOT in `completed_jobs`.
