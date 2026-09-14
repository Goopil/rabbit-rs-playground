# Example modules — design (2026-09-14)

## Goal

Three didactic modules, one per demonstrated lib (`goopil/rabbit-rs-laravel`,
`goopil/laravel-redis-sentinel`, `@goopil/clusterkit`), so a reader of the
published repo can see working usage — copyable commands + snippets — while
the repo itself keeps per-module wiring notes. One minimal UI surface in
FrontLab launches what HTTP can launch; shell-only examples stay documented,
never faked.

Distinct from the `*Lab` modules: labs probe and record findings; examples
show intended usage.

## Modules

### RabbitRsExamples
| Command | Demonstrates |
|---|---|
| `examples:rabbit-rs:dispatch` | Routing: same job class on `default` / `high-priority` / `bulk` via `routing_key = {queue}` |
| `examples:rabbit-rs:delay` | `later(N)` in ttl mode: bucket queue → release → delivery (N configurable, default 30) |
| `examples:rabbit-rs:fail` | Failure path: `tries`, retries, failure recording (`failed-jobs` / Horizon failed) — DLX visibility follows the settle semantics the live topology tests pin |

One didactic job class per example (named for what it shows), Horizon tags
like the lab jobs. Reuses existing topology — no new queues.

### SentinelExamples
| Command | Demonstrates |
|---|---|
| `examples:sentinel:topology` | Resolved master, replicas, sentinel set state from the package config |
| `examples:sentinel:cache` | Cache round-trip through the sentinel connection + read path |

Failover drills stay in the Makefile (`chaos-kill-master` / `chaos-heal`) —
README points there.

### ClusterkitExamples
| Command | Demonstrates |
|---|---|
| `examples:clusterkit:render` | N renders against the running SSR server (`SSR_PORT`), count + graceful error when down |

Plus one demo page owned by the module (Inertia component
`ClusterkitExamples/Demo`, route registered by its ServiceProvider): rendering
it through the browser/SSR path proves the ClusterKit round-trip. Wiring notes
(supervisord `ssr` program, ports, metrics) in the module README.

## Shared conventions

- Module skeleton, `module.json`, `composer.json`, ServiceProviders: same
  shape as the lab modules (`sail artisan module:make` + manual trim).
- Command namespace: `examples:<lib>:<action>` — one greppable prefix.
- Each module gets a `README.md`: what it demonstrates, wiring/config notes,
  copyable snippets, pointer to the matching `docs/upstream-*.md`.
- No new dependencies, no new broker topology, no changes to existing lab
  modules except FrontLab.

## FrontLab: `/lab/examples`

One page (`FrontLab/Examples.jsx`), auth-gated like `/lab`, three cards:

1. **rabbit-rs** — pick example (dispatch / delay / fail) + count →
   `POST /lab/examples/rabbit-rs` (dispatches the module's job classes
   directly; delay shows the ETA).
2. **sentinel** — button → `GET /lab/examples/sentinel` → topology JSON.
3. **clusterkit** — link to the SSR demo page + render count.

Controller stays thin; no shell exec from HTTP.

## Error handling

Commands fail gracefully with the repo's style: typed config errors surface
as-is (they are the libs' contract); broker/SSR unreachable → clear error
line, exit 1. The UI endpoints return `null`/error strings, never 500
(same contract as `DashboardController::rabbitDepth`).

## Testing

- rabbit-rs commands: feature tests with `Queue::fake` asserting job, queue,
  connection and tags (pattern: `LabDispatchTest`).
- sentinel topology: live test asserting exit 0 + master line (sentinels run
  in the Sail stack; same spirit as the broker-up tests).
- clusterkit render: skip when the SSR server is unreachable
  (`markTestSkipped` + pointer), assert rendering works when it is up.
- FrontLab endpoints: follow `LabDispatchTest` (auth + validation + fake).

## Out of scope

New libs/deps, new queues, UI dispatch of shell commands, docs restructure.
