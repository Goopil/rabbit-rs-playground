# goopil/laravel-redis-sentinel — feature proposals

Grounded in playground evidence (v1.9.0, live failover chaos test passing). The lib
is production-ready; these harden ops ergonomics and prevent the bug class we
documented in `docs/upstream-laravel-redis-sentinel.md`.

---

## 1. `sentinel:status [--watch] [--json]` — fleet topology command

### Context (evidence: playground)

To follow the failover chaos test we hand-rolled a Makefile target:

```make
sentinel-watch:
	./vendor/bin/sail exec sentinel-1 valkey-cli -p 26379 --json subscribe \
		"+switch-master" "+failover-end" "+sdown" "+odown"
```

Every operator integrating the package rebuilds the same thing (plus a status view
via `SENTINEL masters`/`replicas`, which is even more boilerplate). The package
already owns the sentinel client abstraction — a console command is the natural
home.

### Proposed behavior

```
php artisan sentinel:status {--connection=*} {--watch} {--json}
```

- Default: table per connection — master name, current addr, flags
  (`s_down`, `o_down`, `failover_in_progress`), replicas (+lag, +last ping),
  sentinels count, quorum, `down-after-ms`, `failover-timeout`, last
  `+switch-master` epoch when readable.
- `--watch`: live SUBSCRIBE on `+switch-master +failover-end +sdown +odown
  +convert-to-slave +promoted-slave`, human-readable lines with timestamps
  (`23:57:42 MASTER->REPLICA failover: mymaster 172.23.0.5:6379 -> 172.23.0.10:6379`).
- `--json`: machine-readable for scripts/CI.
- Uses the same client resolution as the connector (`phpredis` sentinel client), so
  what it reports is what the runtime actually sees.

Suggested commit: `feat: sentinel:status command (fleet topology + --watch event feed)`

### Regression tests

- Against a mocked/contract sentinel client: assert the table renders masters,
  replicas, flags; assert `--watch` maps PubSub payloads to the documented lines.
- Broken sentinel host: command exits non-zero with a clear error (not a stack trace).

---

## 2. Config-shape contract tests (prevent the 1.9.0 bug class)

### Context (evidence: both v1.9.0 bugs)

Both bugs shipped to 1.9.0 were **schema disagreements between readers of the same
config**:

- liveness read `database.redis.{conn}.sentinel.service` while the published config
  put `service` at connection level (`horizon:alive` failed deterministically);
- `connections()` crashed on fresh instances because `RedisManager::$connections`
  is uninitialized in some Laravel versions.

Today the lib supports both key locations (nested first, connection-level fallback)
— which is tolerant but doubles the surface where a *third* reader can disagree with
the first two.

### Proposed behavior

One contract test file in the package:

- Enumerate every documented config key (from the published
  `phpredis-sentinel.php`) and assert each reader resolves it identically:
  connector, `HorizonWorkerLiveness`, octane stickiness listener, retry policy.
- Matrix test: for `service` — nested form, connection-level form, both set
  (document precedence), neither (document the error).
- A CI job that runs the suite against the **oldest and newest supported Laravel**
  to catch `RedisManager` shape drift early.

Optional runtime hardening: at config-cache/boot time, warn when a connection
declares keys in the legacy nested form *and* the modern form simultaneously.

Suggested commit: `test: config-shape contract between connector, liveness and published schema`

### Regression tests

- The contract test itself is the regression test; add the `service` null-guard
  case from the bug-2 repro (`getMasterAddrByName(null)` must never be reached).

---

## 3. Events → counters bridge

### Context (evidence: playground)

The package dispatches rich events (`RedisSentinelMasterFailed`,
`RedisSentinelMasterReconnected`, `RedisSentinelReplicaFallback`,
worker lifecycle from the probes). During the chaos test, "how many failovers did
we survive today?" required grepping logs. The events already carry the facts;
nothing accumulates them.

### Proposed behavior

Zero-dependency counters on Laravel's cache (no prom-client):

- `sentinel:counters` — artisan command printing, per connection:
  master failures, reconnections, max-retry failures, replica fallbacks, probe
  failures — with a time window option (`--since=24h`) when backed by
  time-bucketed cache keys.
- Increment listeners are opt-in (`'counters' => ['enabled' => true]` in config) so
  high-churn environments can skip it.
- Later: this becomes the seam for a real metrics exporter (Prometheus) without
  changing the events API.

Suggested commit: `feat: opt-in event counters + sentinel:counters command`

### Regression tests

- Fire each event through a test dispatch → counters increment exactly once,
  labels per connection.
- Opt-out config → no counters written, no cache writes at all.

---

## Priority

1. Config-shape contract tests (feature 2) — cheapest, kills the bug class that
   produced both 1.9.0 fixes.
2. `sentinel:status --watch` (feature 1) — the ops tooling everyone rebuilds by
   hand; pairs with the existing K8s probe story.
3. Event counters (feature 3) — small, opt-in, future seam for metrics export.
