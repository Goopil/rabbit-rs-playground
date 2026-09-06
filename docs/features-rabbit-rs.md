# goopil/rabbit-rs — feature proposals

Grounded in playground evidence (`docs/upstream-rabbit-rs-laravel.md` records the bugs
these complement). Ordered by expected integration impact.

---

## 1. `rabbit-rs:doctor` — one-shot integration diagnostics

### Context

Every integration failure we hit in the playground was a **silent config miss**, not an
error message:

- `worker=horizon` set in `config/rabbit-rs.php` (the documented place) but read from the
  raw connection config → the default queue class was silently used → Horizon supervisor
  crash-loop on `readyNow()` (bug: missing method, but the *diagnosable* root cause was
  "which class will actually be instantiated?")
- ext (`rabbit_rs` 0.1.1) and lib (`rabbit-rs-laravel` 0.1.0) versions drifting, each
  fixing different bugs — impossible to tell which fix you have from outside
- `safety=blind` vs `safe` changed publish latency profile by 20x; no way to confirm what
  the running pool actually uses

A new user repeating any of these hits a wall: wrong class instantiated shows up as a
crash in Horizon internals, not as "your config key was ignored".

### Proposed command

```
php artisan rabbit-rs:doctor {--connection=* : connections to check, default: all}
```

Output as an ok/warn/fail table:

| Check | What it validates |
| ----- | ----------------- |
| extension | `extension_loaded('rabbit_rs')` + version; compare against the version range the installed lib requires (fail on drift) |
| worker class | Run the connector's resolution logic against the compiled config and **print the FQCN that will be instantiated** (`Goopil\...\Horizon\RabbitMqQueue` vs `RabbitMqQueue`). Warn loudly when `worker` resolves through defaults vs the connection itself (the known inheritance trap) |
| broker | Reachability of every configured host (AMQP connect + management API if configured), auth, vhost exists |
| topology | Queues declared in `topology`/external mode actually exist; exchange + routing keys match subscriptions; DLX wiring present |
| safety | Effective `safety` mode, `confirm_timeout`, `heartbeat` — as resolved after `ConnectionCompiler::compile()` |
| horizon | Supervisors in `config/horizon.php` pointing at this connection; queue names on the supervisor exist in the connection's subscriptions; if `balance=auto`, remind that `readyNow()` must exist in the installed lib version |
| events | Quick note of which lib events are actually listened to in the app (advisory only) |

Exit `0` when everything passes, `1` on any fail (CI-friendly). Suggested commit:
`feat: rabbit-rs:doctor — validate ext/lib versions, worker class resolution, broker, topology, horizon wiring`

### Regression tests

- Fresh app with `RABBIT_RS_WORKER=horizon` only in `config/rabbit-rs.php` (not on the
  connection): doctor **fails/warns** with an actionable message pointing at the
  inheritance trap.
- Same config with the key on the connection: doctor reports the Horizon class.
- Broker down: doctor exits `1` with a fail line for the broker check, others still run.

---

## 2. `rabbit-rs:dlq {list|replay}` — dead-letter management

### Context

The playground had to build DLQ wiring by hand
(`Modules/RabbitRs/app/Console/RabbitRsSetupTopologyCommand.php`: per-queue
dead-letters + per-queue DLX + routing). Once messages are there, there is no tooling:
you inspect them with `rabbitmqctl` or the management UI, and replay by hand.

Combined with the Horizon failed-status bug fix (bug 4 in the upstream doc), a replay
command makes the failure path first-class instead of a black hole.

### Proposed behavior

```
php artisan rabbit-rs:dlq list   {--queue=default} {--limit=20} {--json}
php artisan rabbit-rs:dlq replay {--queue=default} {--limit=100} {--dry-run}
```

- `list`: message count, age of oldest, sample payloads + `x-death` headers (original
  queue, reason, count).
- `replay`: re-publish to the **original queue** (from `x-first-death-queue`),
  preserving the payload and custom headers, **stripping `x-death`** so the message is
  not immediately dead-lettered again; increment `retry_count` header. `--dry-run`
  prints what would move. A per-message max `retry_count` (config
  `rabbit-rs.dlq.max_replay`, default 5) skips poison messages.
- Config convention: derive DLX/DLQ names from the queue name
  (`{queue}.dead-letters`), overridable per queue in config.

### Regression tests

- Dead-letter a message (inject a poison payload), `replay` it, assert it lands back on
  the original queue without `x-death` and gets consumed.
- `--dry-run` leaves the DLQ untouched.
- `retry_count >= max_replay` messages are skipped and reported.

---

## 3. Fake driver for application tests

### Context

The lib's own suite runs without a broker, but an application integrating the driver
has no equivalent of `Queue::fake()` for asserting *publishes* (which queue, which
payload, delay, batch). Today, testing a dispatching service requires either a live
broker or ignoring the rabbit side entirely.

### Proposed behavior

- Register a `rabbit-rs-fake` connection (documented snippet for `config/queue.php`
  test env) that captures publishes in memory instead of hitting the broker.
- Testing helpers (on the connection instance, so no new facade API):

```php
$rabbit = Queue::connection('rabbit-rs-fake');
$rabbit->assertPushed('emails', fn ($payload) => $payload['type'] === 'report');
$rabbit->assertPushedCount('emails', 3);
$rabbit->pushed('emails'); // raw captured messages for custom asserts
```

Captured shape mirrors the wire format (routing key, payload, properties) so tests
assert what the broker would receive, not a wrapper.

### Regression tests

- The fake driver round-trips a publish/`size()`/`pop()` cycle in-process.
- Assertion helpers produce readable failure messages.

---

## 4. `rabbit-rs-prometheus` — broker-level metrics

### Context

Horizon covers job lifecycle metrics for `worker=horizon`, and the lib already
dispatches `BackpressureDetected` / `ConnectionStateChanged` — but nothing scrapable
exposes **broker-side health**. In the playground, diagnosing the publish-after-idle
bug meant reading exception logs, not metrics.

### Proposed package

Mirror `@goopil/clusterkit-prometheus`'s shape (plugin + plain registry, no HTTP
server mandated — but see the clusterkit doc for the metrics-server helper that makes
this ergonomic):

- `rabbitrs_publish_duration_seconds` histogram (by connection/queue)
- `rabbitrs_confirm_wait_seconds` histogram (safe mode confirm latency)
- `rabbitrs_publish_failures_total` counter, labeled by error type
  (`deadline_expired`, `connection_lost`, `backpressure`)
- `rabbitrs_pool_in_flight` / `rabbitrs_pool_generation` gauges (from
  `ConnectionStateChanged` payloads)
- `rabbitrs_queue_depth` gauge per subscription (from `stats()`)
- replay-able from both Octane workers (aggregated over cluster IPC, same pattern as
  clusterkit-prometheus worker metrics)

### Regression tests

- Metrics reflect a publish burst (histogram counts, failure counter increments on an
  injected transport error).
- Aggregation works across forked workers.

---

## Priority

1. `rabbit-rs:doctor` — converts every known trap into a diagnostic; highest
   adoption ROI, low cost.
2. Horizon bug trio release (readyNow / worker key / failed status — upstream doc) —
   prerequisite: it removes the local vendor patch dependency.
3. `rabbit-rs:dlq replay` — differentiating feature, pairs with bug 4 fix.
4. Fake driver — removes the biggest testing friction for adopters.
5. `rabbit-rs-prometheus` — completes the observability story across your libs.
