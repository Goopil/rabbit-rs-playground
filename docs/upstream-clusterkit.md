# Upstream bugs — goopil/clusterkit (+ prometheus & sizing plugins)

> **Session 2026-09-10 — v2.1.0** (clusterkit 2.0.0 → 2.1.0, clusterkit-prometheus
> 1.3.0 → 1.4.0, sizing stays 1.2.1; the version delta was derived from the README
> diff — no changelog ships upstream). Extended roast: the original battery (900
> renders, `kill -9`/SIGTERM of the primary mid-load, `workers: 1` always-fork) plus
> wedged-worker SIGKILL escalation, RSS recycling, partial-fleet recovery — **all
> green, 0 lost renders** — and one real, delimited finding: when the ENTIRE fleet
> dies at once, the primary exits silently instead of re-forking (**W1** below).
> Two earlier findings (L1, L2) were retracted after goopil's direct repro on main —
> both were lab artifacts (stale probe scripts through the bind-mount lag, and probe
> shapes outside the documented contract). Repro probes: `node/ck-roast.mjs`,
> `node/ck-lag.mjs`, `node/ck-edge.mjs`.

## Roast battery (2.1.0)

| Scenario | Result |
|---|---|
| 300 + 600 renders, sequential waves (3 workers, sizing) | 900/900 OK, 0 lost, 0 5xx, ~4.1k renders/s |
| `kill -9` primary mid-load (600 renders in flight) | 600/600 OK, 0 lost — workers keep serving through SO_REUSEPORT; failover transparent |
| SIGTERM primary mid-load (600 renders in flight) | 600/600 OK, 0 lost — bounded drain + `server.close()` hold in-flight requests |
| `WEB_CONCURRENCY=1` (always-fork, prometheus 1.4.0 contract) | target=1, active=1, `/metrics` + `/healthz` served from the primary, renders OK — explicit count wins; sizing only overrides `workers.count: 'auto'` |
| Wedged worker (`SIGSTOP`, `health.wedgedTimeoutMs: 1200`) | detected in ~1.2 s → `worker:draining {reason: "wedged"}` → drain escalates to SIGKILL → renders served by the remaining workers throughout, 0 lost |
| Partial fleet loss (2 of 3 workers `kill -9`) | primary alive, both slots re-forked through the restart queue with progressive backoff (1000 → 2000 ms), renders 15/15, 0 lost |
| RSS recycling (`workers.maxRssMb: 100`, two 70 MB buffers per worker) | both over-budget workers drained and recycled with `reason: "rss"`, primary alive, 0 lost |

**Config trap found while testing (not a bug):** `maxRssMb` lives under `workers`,
not `health` — `health: { maxRssMb }` is silently ignored and the recycle never
fires. The README has it right; the two tables just sit close enough to bite.

## Bug W1 (2.1.0): total fleet loss — the primary exits silently instead of re-forking

Killing **every** worker at once (`kill -9` of all 3, or the equivalent
`process.exit(1)` storm) leaves the pool dead **with no self-healing and no
alarm**:

- the restart coordinator records each crash and logs `Waiting before restart
  { delayMs: 1000, workerId: … }` — the queue is armed;
- the primary then **exits with code 1 within ~1 s, silently** (no stack, no
  log line, no event) before executing the first replacement;
- result: 0 workers, 0 re-forks (`Worker online` stays at boot count), all
  in-flight and subsequent renders lost with connection refused;
- reproduced on the production shape (`node/clusterkit-server.mjs` with the
  prometheus plugin, whose primary-side metrics server holds a handle — the
  exit is not an empty event loop).

Delimited: losing 2 of 3 workers recovers perfectly (see battery) — the defect
is exactly `active → 0`. The README promises "the worker is restarted with
backoff and the crash-loop breaker" (Migration to 2.0, count-1 section) and
"every non-graceful worker exit is … queued for replacement unless shutdown is
in progress or the circuit breaker has tripped" — neither exception applies
here (2 crashes < threshold 5, no shutdown). A simultaneous fleet-wide OOM or a
shared memory bug takes the whole SSR pool down without a single warning
beyond the initial `worker:crash` lines — the one situation where an
orchestrator must survive.

Suggested repro (4 lines): boot 3 workers → `kill -9` all worker pids → the
primary exits 1 within a second instead of re-forking through the queue.
Probes: `node/ck-edge.mjs` (core shape), `node/ck-deadtest.sh` (prod shape,
fresh ports).

## Retracted findings (were raised against 2.1.0, disproven with our own probes)

### L1 (retracted): `health.maxEventLoopLagMs` — the metric sees sync blocks, the policy fires

We reported that `eventLoopLagMs` read 0–2 ms during busy-wait blocks and that the
lag policy never fired. Wrong on both counts, for two compounding reasons:

1. **Stale probe scripts.** Several negative observations ran against stale versions
   of `node/ck-lag.mjs` through the container bind-mount lag (the lab's own trap
   note, not applied strictly enough). The "core alone produces no beats" run that
   seeded L2 was one of these.
2. **Wrong shape for the contract.** The policy is *sustained lag*: recycle when
   `lagRecycleBeats` **consecutive** beats exceed `maxEventLoopLagMs`. Our two shapes
   both legitimately cannot fire:
   - 10 alternating 400 ms blocks across 2 workers → each worker never had 2
     consecutive beats above threshold → correct no-op;
   - one 1500 ms block → exactly one overdue beat (~1200 ms drift), the next beats
     return to 0 → correct no-op. By design: the contract is "sustained lag", not
     "kill a worker for a hiccup".

**Verified correct on our side (2026-09-10, md5-checked probe):** the reported value
is beat drift (`now - lastBeat - interval`) — a beat overdue inside a sync block fires
at release with drift ≈ block duration; under continuous saturation (1 worker, 260 ms
blocks in a loop, `heartbeatMs: 300`, `maxEventLoopLagMs: 50`, `lagRecycleBeats: 2`),
the policy fires exactly as documented:

```
[4599] block 260ms start  (x3 sustained)
>>> draining {"workerId":1,"pid":4599,"reason":"lag"}
>>> recycle  {"workerId":1,"pid":4599,"ageMs":7071,"reason":"lag"}
[4638] block 260ms start  (replacement serves next requests)
```

### L2 (retracted): health heartbeats are core, not plugin-gated

We reported that `worker:health` beats required the sizing plugin. The beat reporter
lives in the core (`runWorker()` → health-monitor) and flows with
`health: { heartbeatMs: 300 }` and **no plugin** — re-verified with a probe whose
in-container md5 matched the host file: beats arrive at the configured interval with
`sizing=OFF`. The seeding observation was a stale script (see L1). No upstream
change needed.

## Verified — carries over

- **`worker:draining` (new in 2.1.0)**: now empirically observed — fires immediately
  before the bounded drain, with every recycle reason we exercised: `"lag"`,
  `"wedged"` and `"rss"` (traces in the battery + L1 above).
- prometheus 1.4.0 `workers: 1` alignment verified: the forked worker is tracked via
  orchestrator events, `clusterkit_active_workers` reads 1, primary serves the metrics.
- Graceful shutdown + SO_REUSEPORT failover semantics unchanged from the 2.0.0 roast
  (0 lost across both kill shapes).

## Proposals status (`docs/features-clusterkit.md`, dated 2026-09-05)

| # | Proposal | Status in 2.0/2.1 |
|---|---|---|
| 1 | `prometheus.serve()` metrics-server helper | **Implemented** (2.0.0) — the SSR server now runs it; verified live (primary-only `/metrics` + `/healthz`, clean shutdown) |
| 2 | `orchestrator.isPrimary` getter | **Resolved by design** — 2.0's always-fork removed the blurred single-worker mode; the primary is always a pure supervisor |
| 3 | Warn when safety features are silently disabled | **Resolved** — always-fork removed the count-1 blind spot the proposal targeted (health beats work at every count; the plugin-gating we suspected was a lab artifact, see L2 retraction) |
| 4 | Grafana dashboard shipped in the prometheus plugin | Not checked (not in the SSR path) |
| 5 | `/healthz` JSON endpoint | **Implemented** (2.0.0) — verified live: `{"status":"healthy","target":…,"active":…}` on the primary |

## Note (lab-side): stale-file execs are now a protocol step

The container bind lags behind host edits of `node/` probes; three false negatives
this session (one roast batch, both L1/L2 seeders) traced to it. The roast protocol
now REQUIRES, before every probe run: `md5 -q node/<probe>` on the host vs
`md5sum` in the container — rerun until they match. Nothing else to fix upstream.
