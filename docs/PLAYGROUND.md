# rabbit-rs playground

Live lab to test and validate **goopil/rabbit-rs-laravel** (+ `rabbit-rs-native`)
against a real RabbitMQ 4.x broker. Every finding ends up in
`docs/upstream-rabbit-rs-laravel.md` with an executable test case — pins guard
verified behaviors, bug guards auto-activate when upstream ships a fix.

## Stack

- Laravel 13 (PHP 8.5) on Laravel Sail — `docker compose`
- RabbitMQ 4.x single node (`rabbitmq-simple`) with Management API
- mysql, redis-sentinel (`QUEUE_CONNECTION=redis-sentinel`)
- nwidart modules: labs are split by concern (see module map below)

## Boot

```bash
vendor/bin/sail up -d
vendor/bin/sail artisan rabbit-rs:setup-topology   # exchange, queues, bindings, DLQ
vendor/bin/sail npm run build                      # only if you need the FrontLab UI
vendor/bin/sail artisan test --compact             # full suite (~100s), broker must be up
```

## The consumer map — READ BEFORE ANY MANUAL RUN

| connection        | queues                          | consumer                                   |
|-------------------|---------------------------------|--------------------------------------------|
| `rabbit-rs`       | default, high-priority, bulk    | Horizon `supervisor-rabbit`                |
| `rabbit-rs-work`  | work                            | plain `queue:work rabbit-rs-work`          |
| `rabbit-rs-ia`    | ia-summary, ia-embed            | `rabbit-rs:work --connection=rabbit-rs-ia` |

**Trap:** Horizon runs in the background and drains `default` / `high-priority`
/ `bulk` **instantly** (deliver_get climbs while ready stays 0 — your "lost"
messages are being consumed). Stop it before manual lab runs:

```bash
vendor/bin/sail artisan horizon:terminate
```

## Management API

- from the host: `http://localhost:15675` (guest / guest, vhost `/`)
- from the containers: `http://rabbitmq-simple:15672`
- queue depth: `/api/queues/%2F/{queue}` → `messages_ready`
- `deliver_get` tells you whether a consumer is eating the queue

## Module map — labs are split by concern

| module         | purpose                                                     | command / signature            |
|----------------|-------------------------------------------------------------|--------------------------------|
| `RabbitRs`     | app-side wiring, topology setup, sample jobs                | `rabbit-rs:setup-topology`     |
| `QueueLab`     | generic publish utilities                                   | `queue-lab:stress`             |
| `SafetyLab`    | safety-mode comparison (blind / unsafe / safe)              | `queue-lab:safety`             |
| `LifecycleLab` | terminating-close repros (publish-and-exit + broker poll)   | `queue-lab:dispatch-and-exit`  |
| `FrontLab`     | HTTP dispatch UI (connection-name validation)               | —                              |

Command signatures are stable — they are referenced by the upstream doc shared
with goopil. New probe domains get their own module (`DelayLab` / `TopologyLab`
are the planned homes), never lumped into an existing lab.

## Config switches under test (`config/rabbit-rs.php` via env)

- `RABBIT_RS_SAFETY`: `blind` | `unsafe` | `safe` — live comparison:
  `queue-lab:safety`
- `RABBIT_RS_TOPOLOGY_MODE`: `external` | `declare` — only `declare` verifies,
  through `rabbit-rs:topology` (not the doctor: bug 6)
- `RABBIT_RS_DELAY_MODE`: `auto` | `plugin` | `ttl` — 0.2.0 default `ttl`:
  real bucket queues, but see upstream bug 15 (early visibility + lazy quorum
  TTL makes delivery unboundedly late on an idle broker)
- `RABBIT_RS_DELAY_BUCKETS`: bucket families in seconds, default `1,5,30,120`
  — delays quantize UP (later(10) → the 30s bucket)

### Upgrade 0.1.x → 0.2.0 (undocumented in the upstream changelog)

1. `pie install goopil/rabbit-rs-native` **in the container** — the package
   0.2.0 requires `ext-rabbit_rs ^0.2`; the old ext answers
   `unknown field 'flush_interval'` on every resolution. A `sail restart`
   keeps the PIE-installed `.so`, but a **container recreation wipes it** —
   re-run PIE.
2. Subscription knobs `priority_class` / `starvation_after` are gone
   (accepted: `queue, weight, prefetch, early_ack, no_ack`).
3. The connection-level `delay_mode` scalar is gone — use
   `rabbit-rs.delay.{mode, buckets, max_buckets, queue_expiry_margin}`.

### Upgrade 0.2.0 → 0.2.1

1. `composer update goopil/rabbit-rs-laravel` **and** `pie install
   goopil/rabbit-rs-native` in the container — the 0.2.1 package requires
   `^0.2.1` (the compiled config now carries the publish `routes` inside the
   native section; a 0.2.0 ext rejects it at pool creation).
2. No config changes; the fixes are compile/declare-side (bugs 13, 11, 14)
   plus one new one: **the publish buffer no longer age-flushes** (bug 16 —
   `flush_interval` ignored, depth reads unreliable inside the publishing
   process). Depth assertions must poll the broker, not `size()`.

### Upgrade 0.2.1 → 0.2.2

1. `composer update goopil/rabbit-rs-laravel` **and** `pie install
   goopil/rabbit-rs-native` in the container (lockstep — the 0.2.2 package
   requires `^0.2.2`).
2. Fixes bug 16 (publish buffer age-flush restored via a background timer).
   Latency note: lone publishes can still take ~4–5 s to land under load, not
   the 1 ms the contract advertises.

## Topology coverage matrix

What the suite covers on the topology config surface — and what is still
manual (candidates for a future `TopologyLab` module):

| Surface | Status | Where |
|---|---|---|
| `topology_mode` external / declare | covered | `UpstreamFindingsTest` (declare + `rabbit-rs:topology`) |
| `queue_type` quorum + **classic** | covered — compile pins + live classic round-trip (API pre-declare, driver publish, API poll) | `RabbitRsTopologyConfigTest` |
| `delivery_limit` + dead_letter wiring | covered at compile level (+ broker `x-delivery-limit` verified in the doc) | `RabbitRsTopologyConfigTest` |
| Dead-letter END TO END (poison → DLX → `failed-jobs`) | manual only — needs ~20 redeliveries; verified in doc bug 4 | — |
| `queue_durable=false` | not covered — non-durable queues die with their declaring connection; needs `rabbit-rs:topology --fix` (blocked by bug 11's 30s gate) | — |
| `delay.buckets` custom + `max_buckets` validation | compile-time covered; 0.2.0 creates real buckets but timing is untestable on an idle broker (lazy quorum TTL, bug 15) | `RabbitRsTopologyConfigTest` |
| Invalid config rejection (unknown keys, bad mode/type/wait_timeout, delivery_limit without dead_letter) | covered | `RabbitRsTopologyConfigTest` |
| Queue shared by two connections (advertised dual-consumption) | compile-level covered; live dual-consume untested | `RabbitRsTopologyConfigTest` |
| Subscription `weight` | not covered — weights only observed in doc bug 8's round-robin (`priority_class`/`starvation_after` removed in 0.2.0) | — |
| `prefetch` under load | not covered | — |
| Runtime config swap (connection re-resolve after `config()->set`) | covered | `UpstreamFindingsTest` |

## Tests

```bash
vendor/bin/sail artisan test --compact            # everything (~100s)
vendor/bin/sail artisan test --group upstream     # pins + bug guards only
```

- `tests/Feature/UpstreamFindingsTest.php` — API-surface pins (green) + bug
  guards (skipped while the bug is live, auto-activate on the upstream fix)
- `tests/Feature/ChildProcessReproTest.php` — spawns real artisan children and
  polls the Management API; covers the terminating-close findings
- `tests/Feature/RabbitRsTopologyConfigTest.php` — broker-free config pins
  (compiler accepts/rejects, compiled topology shape) + one live classic-queue
  round-trip; also documents the chunky async flush (bug 10: 47-48/50 waves)
- The suite hits the real broker: keep `rabbitmq-simple` up, expect ~100 s

## SSR roast (clusterkit)

The Inertia SSR pool is orchestrated by `@goopil/clusterkit` — findings live in
`docs/upstream-clusterkit.md` (L1/L2 retracted as lab artifacts; health semantics
verified). Manual battery:

```bash
# inside the container:
SSR_PORT=13715 SSR_METRICS_PORT=13716 WEB_CONCURRENCY=3 node node/clusterkit-server.mjs &
node node/ck-roast.mjs http://127.0.0.1:13715 20 15    # 300 renders, accounting
# kill -9 the primary mid-load, or SIGTERM it — 0 lost renders expected
node node/ck-lag.mjs 13900                              # health/lag verification
node node/ck-edge.mjs 13950                             # wedged / crash breaker / RSS roast
# total fleet loss repro (bug W1): kill -9 every worker pid -> primary exits
```

**Protocol step (mandatory): after editing any probe under `node/`, verify the
container sees the same file before running it — three false negatives this session
came from the bind-mount lag:**

```bash
md5 -q node/ck-lag.mjs                                   # host
vendor/bin/sail exec laravel.test md5sum node/ck-lag.mjs # container — must match
```

Render payload needs `props.auth.user` + a `props.ziggy` route map (the app
shell calls `route()` in SSR) — `ck-roast.mjs` embeds a working one. Logical
component names resolve as `<Module>/<Sub>/Page` (`FrontLab/Dashboard`,
`Welcome`).

## Known traps

- **Management API publish (`POST /api/exchanges/%2F/{exchange}/publish`)**:
  `properties` must be a JSON **object** — PHP's `[]` encodes as `[]` and the
  API answers **500** with an empty body. Use `(object) []` (curl's `{}` works).
- **Management stats can lie** after a broker incident: `messages_ready` kept
  reporting phantom depths while the authoritative
  `POST /api/queues/%2F/{queue}/get` returned nothing. Trust `/get`.
- `size()` right after a publish reads a stale 0 (bug 10) — wait 1–5 s for the
  async flush, or poll the Management API.
- `clear()` does not force-flush either: messages can land AFTER a purge.
- **Publish from a cold pool** (no broker op before the push) can sit for
  seconds or die with an artisan one-shot — prime the pool with `clear()` /
  `size()` before publishing in lab scripts (bug 10).
- **In-process pool staleness**: 0.1.x shares pools per config fingerprint per
  process — after tests resolve+close pools, an in-process lab command can
  inherit a dead pool (publishes vanish). Run lab commands as child processes
  (see `SafetyCommandTest`).
- Workers keep their compiled profile from boot — restart them after config
  changes (`horizon:terminate` + restart, `rabbit-rs:work` relaunch).
- `queue:work --queue=<new-name>` never declares an unknown queue; publish to
  it and the message is unroutable (silently dropped under blind).
- **DLX wiring**: `dead-letters` is a **fanout** exchange (setup-topology) — a
  `direct` DLX with a `#` binding silently drops every dead-lettered message
  (`#` is a literal key on direct exchanges; the DL publish has no `mandatory`
  flag). The driver's own declare uses direct + a coherent
  `x-dead-letter-routing-key`/binding pair — never mix the two models.
- Classic queues + inherited `delivery_limit` can never be declared
  (`x-delivery-limit` is quorum-only — bug 13): keep probe queues quorum or
  unset `delivery_limit`.
- **Deleting a route binding via the API**: the URL needs the source-type
  segment — `DELETE /api/bindings/%2F/e/{exchange}/q/{queue}/{properties_key}`.
  Omitting the `/e/` (or `/q/`) answers **405**, which reads like a broken
  endpoint. `properties_key` comes from `GET /api/bindings/%2F` (for a plain
  routing-key binding it IS the routing key).
- **Delay probes are residue-prone**: bucket queues hold messages past their
  TTL (lazy quorum expiry) and release them into `work` minutes later — a
  `clear()` at probe start cannot see in-flight bucket residue. Read depth
  deltas relative to a drained baseline, not absolutes.
  **This bit hard on 2026-09-10**: the bug-15 "early main-queue visibility"
  claim was entirely residue DLX releases landing inside the observation
  window — clean-state probes that DELETE every `rabbit-rs.delay.*` bucket
  and purge `work` first (`node/ck15-decisive.sh`, `node/ck15-auto.sh`)
  showed ttl goes straight to the bucket. The in-suite guard now calls
  `purgeDelayBuckets()` before its window; any new delay observation must do
  the same. In-suite `delay.mode` switching is also unreliable (cached
  compiled pool per connection name) — verify mode differences with fresh
  processes (`sail exec … sh node/ck15-*.sh`), not by re-resolving.
