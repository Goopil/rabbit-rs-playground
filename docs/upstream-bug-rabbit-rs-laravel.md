# Upstream bug — goopil/rabbit-rs-laravel

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
