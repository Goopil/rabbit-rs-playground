# Rabbit RS Playground v2

A Laravel Sail full-stack playground for the [php-rabbit-rs](https://github.com/Goopil/php-rabbit-rs) native PHP extension (Rust-powered RabbitMQ transport), running on **Laravel Octane (Swoole)**, **Horizon on Valkey Sentinel**, and **Inertia SSR orchestrated by ClusterKit** — with code organized in [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules).

## Goopil packages under test

| Package | Role here | Dossier |
|---------|-----------|---------|
| [php-rabbit-rs](https://github.com/Goopil/php-rabbit-rs) + [rabbit-rs-laravel](https://github.com/Goopil/rabbit-rs-laravel) | Rust-native RabbitMQ transport for Laravel queues (Horizon-compatible, adaptive prefetch, delay buckets, dead-letter tooling) | [docs/upstream-rabbit-rs-laravel.md](docs/upstream-rabbit-rs-laravel.md) |
| [laravel-redis-sentinel](https://github.com/Goopil/laravel-redis-sentinel) | Redis Sentinel driver for Laravel/Horizon — failover chaos-tested here (read/write splitting, master stickiness, `sentinel:status`) | [docs/upstream-laravel-redis-sentinel.md](docs/upstream-laravel-redis-sentinel.md) · [features](docs/features-laravel-redis-sentinel.md) |
| [clusterkit](https://github.com/Goopil/clusterkit) | Inertia SSR process orchestrator (multi-worker fleet, crash escalation, Prometheus plugin) | [docs/upstream-clusterkit.md](docs/upstream-clusterkit.md) |

Each dossier tracks upstream bugs, fixes (with playground verification evidence) and feature proposals — every finding has an executable guard in `tests/Feature/`.

## Architecture

Everything runs in the single `laravel.test` Sail container under supervisord:

| Program | Role |
|---------|------|
| `php` | Octane/Swoole HTTP server (port 80) |
| `horizon` | Queue workers on `redis-sentinel` + `rabbit-rs` for `default` / `high-priority` / `bulk` (flat names, identical on both transports), via `RABBIT_RS_WORKER=horizon` (requires `goopil/rabbit-rs-laravel` >= 0.1.1) |
| `ssr` | Node ClusterKit orchestrator running the Inertia SSR server (`POST /render` on 127.0.0.1:13715) |

### Valkey HA set (compose)

1 master + 2 replicas + 3 sentinels (`valkey-master`, `valkey-1/2`, `sentinel-1/2/3`). Cache, sessions, Horizon and the `redis-sentinel` queue connection all go through the sentinels via [goopil/laravel-redis-sentinel](https://github.com/Goopil/laravel-redis-sentinel) (read/write splitting, master stickiness, failover events logged to `storage/logs/sentinel.log`).

### RabbitMQ infra

Single node (`rabbitmq-simple`), one vhost, exchange `laravel.jobs` (direct, publish-side), `dead-letters` (fanout catch-all — see the DLX trap in `docs/PLAYGROUND.md`), 7 quorum queues (`default`, `high-priority`, `bulk`, `work`, `ia-summary`, `ia-embed`, `queue-lab-safety`) + the `failed-jobs` dead-letter queue. **Flat queue names, identical on both transports.**

Consumer map:

| connection | queues | consumer |
|------------|--------|----------|
| `redis-sentinel` | default, high-priority, bulk | Horizon (`supervisor-horizon` / `supervisor-bulk`) |
| `rabbit-rs` | default, high-priority, bulk | Horizon (`supervisor-rabbit`) |
| `rabbit-rs-work` | work | manual `queue:work rabbit-rs-work` |
| `rabbit-rs-ia` | ia-summary, ia-embed | manual `rabbit-rs:work --connection=rabbit-rs-ia` |

All jobs dispatched to the two Horizon connections appear in the Horizon dashboard. The `work`/`ia` consumers are manual lab processes — read `docs/PLAYGROUND.md` ("The consumer map") before any manual run.

## Modules

| Module | Contents |
|--------|----------|
| `RabbitRs` | App-side wiring, broker topology setup, sample jobs + demo dispatch (`rabbit-rs:setup-topology`, `rabbit-rs:demo --connection=redis-sentinel\|rabbit-rs\|both`) |
| `QueueLab` | `queue-lab:stress` burst command + `StressJob` |
| `SafetyLab` | Safety-mode comparison blind / unsafe / safe (`queue-lab:safety`) |
| `LifecycleLab` | Terminating-close repros: publish-and-exit + broker poll (`queue-lab:dispatch-and-exit`) |
| `RabbitRsExamples` / `SentinelExamples` / `ClusterkitExamples` | Didactic usage examples per lib — `examples:*` commands + module READMEs (wiring cheatsheets) |
| `FrontLab` | `/lab` dashboard (Horizon stats, queue depths, dispatch panel, 3s live polling) |

One lab module per probe domain — new domains get their own module, never lumped into an existing lab (see `docs/PLAYGROUND.md`).

## Quick Start

```bash
# 1. Build the Sail image (installs the rabbit_rs extension)
make build

# 2. Start all containers (Laravel + MySQL + RabbitMQ + Valkey HA)
make up

# 3. Create the RabbitMQ topology
make setup

# 4. Migrate + seed (login: test@example.com / password)
sail artisan migrate --force && sail artisan db:seed --force

# 5. Open the lab
# http://localhost  →  redirects to /lab (auth-gated)
```

## Make Targets

| Target | Description |
|--------|-------------|
| `make demo` | Dispatch demo jobs to **both** connections |
| `make demo-redis` / `make demo-rabbit` | One connection only |
| `make stress` | 100 StressJob on `bulk` queue (Horizon) |
| `make horizon` / `make horizon-probes` | Horizon status / ready+alive probes |
| `make ssr-build` | Client + SSR bundles |
| `make sentinel-watch` | Subscribe to sentinel events (`+switch-master`, `+sdown`, `+odown`, ...) |
| `make chaos-kill-master` / `make chaos-heal` | Stop/start `valkey-master` (failover drill) |

## Dual Dispatch

```bash
sail artisan rabbit-rs:demo --connection=redis-sentinel   # → consumed by Horizon (redis-sentinel)
sail artisan rabbit-rs:demo --connection=rabbit-rs        # → consumed by Horizon (rabbit-rs supervisor)
sail artisan rabbit-rs:demo --connection=both             # → both
```

From the UI: `/lab` dispatch panel posts to `POST /lab/dispatch` (`{job, connection, queue, count}`).

Usage examples: `/lab/examples` dispatches the example jobs (routing, delay, failure path) and shows the live sentinel snapshot.

## Tests

```bash
sail artisan test --compact             # full suite (~100s) — RabbitMQ broker must be up
sail artisan test --group upstream      # pins + bug guards only
```

- `tests/Feature/UpstreamFindingsTest.php` — API-surface pins (green) + bug guards (skipped while the bug is live, auto-activate when the upstream fix lands)
- `tests/Feature/RabbitRsLiveTopologyTest.php` — live broker behavior: poison → DLX → `failed-jobs` dead-lettering, competing consumers on a shared physical queue, coherent DLX pair contract, classic/`delivery_limit` declare guard
- `tests/Feature/RabbitRsTopologyConfigTest.php` — broker-free config pins (compiler accepts/rejects, compiled topology shape) + one live classic-queue round-trip
- `tests/Feature/ChildProcessReproTest.php` — spawns real artisan children and polls the Management API; covers the terminating-close findings
- `tests/Feature/LabDispatchTest.php` — `/lab/dispatch` endpoint (connection/queue validation, Horizon tags)

The suite hits the real broker: keep `rabbitmq-simple` up. Coverage details and manual-only surfaces live in `docs/PLAYGROUND.md` (coverage matrix).

## Chaos Testing (Sentinel Failover)

```bash
make sentinel-watch    # terminal 1
make chaos-kill-master # terminal 2
# watch: +sdown master → +odown → +switch-master mymaster → valkey-1/2
make horizon-probes    # horizon still ready/alive
make chaos-heal        # old master rejoins as replica
# events captured in storage/logs/sentinel.log
```

## Management UIs

| What | URL | Credentials |
|------|-----|-------------|
| Lab dashboard | http://localhost/lab | test@example.com / password |
| Horizon | http://localhost/horizon | — |
| RabbitMQ | http://localhost:15672 | guest / guest |

## Configuration

- `config/rabbit-rs.php` — rabbit-rs cross-cutting defaults (0.1.0 schema)
- `config/database.php` — sentinel-backed `default` + `cache` redis connections
- `config/horizon.php` — supervisors on `redis-sentinel` + `rabbit-rs`
- `config/queue.php` — `redis-sentinel` (`phpredis-sentinel` driver) + `rabbit-rs` connections
- `docker/8.5/supervisord.conf` — php / horizon / ssr programs
- `compose.yaml` — RabbitMQ, MySQL, Valkey HA set
- `.env` — `QUEUE_CONNECTION=redis-sentinel`, `SUPERVISOR_*` gates, `SSR_PORT`, `INERTIA_SSR_*`

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Horizon not connecting | Check sentinels: `sail exec sentinel-1 valkey-cli -p 26379 sentinel get-master-addr-by-name mymaster` |
| SSR down | Pages fall back to client-side rendering; check `sail exec laravel.test supervisorctl status ssr` |
| Extension not loaded | Rebuild: `make build && make up` |
| Publish fails (unroutable) | `make setup` |
| Composer rejects rabbit-rs-laravel | Must run inside Sail (`ext-rabbit_rs` only exists in the container); use `--ignore-platform-req=ext-rabbit_rs` when composer's platform check desyncs |
| 500 on first request of a fresh worker | Warm-up listener in `AppServiceProvider` resolves sentinel connections at Octane worker start (vendor lib fix tracked in `docs/upstream-laravel-redis-sentinel.md`) |

## Lib notes

- Bugs found + fixes suggested: `docs/upstream-rabbit-rs-laravel.md`, `docs/upstream-laravel-redis-sentinel.md`
- Feature proposals: `docs/features-rabbit-rs.md`, `docs/features-clusterkit.md`, `docs/features-laravel-redis-sentinel.md`

## License

MIT — see [LICENSE](LICENSE).
