# goopil/clusterkit — feature proposals

Grounded in playground evidence (SSR orchestrator running 1.3.0 + sizing +
prometheus plugins). Ordered by expected impact.

---

## 1. Metrics-server helper in `@goopil/clusterkit-prometheus`

### Context (evidence: playground, 2026-09-05)

The README example for the prometheus plugin is **broken in cluster mode**: it creates
the HTTP server inside `run()`, which only executes in workers — while
`getMetrics()` is primary-only (it throws in a worker by design). Naively following
the README yields an empty reply on `/metrics` because the async handler rejects in
every worker. We had to hand-roll the correct pattern:

```js
if (cluster.isPrimary) {
    http.createServer(async (req, res) => {
        res.setHeader('Content-Type', prometheus.registry.contentType);
        res.end(await prometheus.getMetrics());
    }).listen({ port: SSR_METRICS_PORT, host: '127.0.0.1' });
}
```

…on a **separate port** from the worker render server (sharing the port via
SO_REUSEPORT would non-deterministically route `/metrics` requests to workers).

### Proposed API

```ts
const prometheus = createPrometheusPlugin({ prefix: 'clusterkit_' });

// binds in the primary only, ignores calls in workers (no-op), owns its lifecycle
prometheus.serve({ port: 9090, host: '127.0.0.1' });
```

Semantics:
- No-op in workers (log at debug level), binds in the primary.
- Refuses to share the worker port: if `port` equals the main server's port, throw at
  startup with a message explaining the SO_REUSEPORT cross-routing problem.
- Honors orchestrator shutdown (close on `shutdown:complete`).

Fix the README example to use it (the current example teaches a broken pattern).
Suggested commit: `feat(prometheus): primary-side metrics server helper + fix cluster-mode README example`

### Regression tests

- Fork mode (2 workers): `GET :9090/metrics` returns 200 with `clusterkit_` series;
  `GET :renderport/metrics` returns 404; worker-side `serve()` call is a no-op.
- Single-worker mode (primary runs the app): metrics served on 9090 works too.

---

## 2. `orchestrator.isPrimary`

### Context

Same evidence: to place the metrics listener we imported `node:cluster` and relied on
`cluster.isPrimary` — an implementation detail of the orchestrator (it also has a
single-worker mode where the distinction is blurred). The plugin API
("listeners bind on primary only") actively requires users to know which process
they're in.

### Proposed API

Instance getter reflecting clusterkit's own mode logic (including single-worker
mode, where `cluster.isPrimary` and "the primary runs the app" diverge):

```ts
orchestrator.isPrimary; // true in primary, false in forked workers
```

Suggested commit: `feat: expose orchestrator.isPrimary`

### Regression tests

- Fork mode: primary logs true, each worker logs false (asserted from
  worker-started/user bootstrap logs).
- Single-worker mode: value matches whatever the internal mode flag says.

---

## 3. Warn when safety features are silently disabled

### Context (evidence: playground, 2026-09-05)

`workers.maxRssMb: 1` with `count: 1` produced **no recycle for 20 seconds** —
single-worker mode runs without forking/IPC, so health heartbeats never fire and
RSS recycling never evaluates. A no-op on a safety feature is the worst failure
mode: the user believes they have memory protection until the container OOMs.

Wedged-worker detection (`health.wedgedTimeoutMs`) and fleet degradation
(`health.degradedAfterMs`) live in the same health subsystem and presumably share
the blind spot.

### Proposed behavior

At primary startup, when single-worker mode is active:

```
WARN [clusterkit:health-monitor] maxRssMb is set but single-worker mode (no fork)
disables health heartbeats — RSS recycling will never trigger. Set workers.count >= 2
to enable it.
```

Same warning for `wedgedTimeoutMs`. (In single-worker mode the primary *is* the
worker, so an OOM kills the app anyway — but the message should say the feature is
off, not let the user assume it's on.)
Suggested commit: `feat: warn when health policies are configured but disabled by single-worker mode`

### Regression tests

- Config `maxRssMb > 0` + `count: 1` → warning emitted at startup.
- Config `maxRssMb > 0` + `count: 2` → no warning, recycle fires (existing behavior).

---

## 4. Grafana dashboard shipped in the prometheus plugin

### Context

All the metrics exist (orchestration gauges/counters, per-worker RSS/heap/lag from
heartbeats, recycle/wedged-kill counters, fleet gauges, `recovery_duration_seconds`).
Every consumer rebuilds the same panels by hand.

### Proposed behavior

- `grafana/clusterkit-dashboard.json` shipped in the package (pinned to the
  `clusterkit_` prefix by default, documented variable override).
- Panels: fleet slots (active/target/quarantined), per-worker RSS vs
  `maxRssMb` threshold line, event-loop lag p95, recycle rate by reason, wedged
  kills, recovery duration, circuit-breaker trips.
- Bonus: export the sizing plan as an info metric
  (`clusterkit_sizing_info{computed_workers, configured_workers, constrained, source} = 1`)
  so the "configured 2, computed 4" mismatch we only saw in logs becomes scrapeable.
Suggested commit: `feat(prometheus): ship Grafana dashboard + sizing info metric`

### Regression tests

- Dashboard JSON validates (importable schema); metric names match the registry.
- Sizing info gauge present after plugin install with the computed plan.

---

## 5. `/healthz` JSON endpoint

### Context

1.3.0 added `getFleetHealth()` and `fleet:degraded`/`fleet:recovered` — perfect for
K8s probes, but nothing exposes it. The metrics server (feature 1) is the natural
home.

### Proposed behavior

On the primary metrics server: `GET /healthz` →
`{"status":"healthy|degraded","active":2,"target":3,"quarantined":0,...}` with the
appropriate HTTP status (503 when degraded). K8s readiness without a Prometheus dependency.
Suggested commit: `feat(prometheus): /healthz JSON endpoint backed by getFleetHealth()`

### Regression tests

- Healthy fleet → 200; simulate a wedged worker / boot-loop quarantine → 503 with
  the degraded payload.

---

## Priority

1. Metrics-server helper + README fix (feature 1) — the README currently teaches a
   broken pattern; every integrator trips on it.
2. Single-worker safety warnings (feature 3) — silent no-op on OOM protection.
3. `isPrimary` (feature 2) — trivial, unblocks plugin authors.
4. Grafana dashboard + sizing metric (feature 4) — polish, high visibility.
5. `/healthz` (feature 5) — K8s ergonomics.
