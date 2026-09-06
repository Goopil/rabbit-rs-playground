# Rabbit RS Playground v2

A Laravel Sail full-stack playground for the [php-rabbit-rs](https://github.com/Goopil/php-rabbit-rs) native PHP extension (Rust-powered RabbitMQ transport), running on **Laravel Octane (Swoole)**, **Horizon on Valkey Sentinel**, and **Inertia SSR orchestrated by ClusterKit** — with code organized in [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules).

## Architecture

Everything runs in the single `laravel.test` Sail container under supervisord:

| Program | Role |
|---------|------|
| `php` | Octane/Swoole HTTP server (port 80) |
| `horizon` | Queue workers for **all** queues on both transports — `redis-sentinel` and `rabbit-rs` (same flat names), via `RABBIT_RS_WORKER=horizon` + a local vendor patch (see docs/upstream-rabbit-rs-laravel.md) |
| `ssr` | Node ClusterKit orchestrator running the Inertia SSR server (`POST /render` on 127.0.0.1:13715) |

### Valkey HA set (compose)

1 master + 2 replicas + 3 sentinels (`valkey-master`, `valkey-1/2`, `sentinel-1/2/3`). Cache, sessions, Horizon and the `redis-sentinel` queue connection all go through the sentinels via [goopil/laravel-redis-sentinel](https://github.com/Goopil/laravel-redis-sentinel) (read/write splitting, master stickiness, failover events logged to `storage/logs/sentinel.log`).

### RabbitMQ infra

Single node (`rabbitmq-simple`), one vhost, one exchange (`laravel.jobs`), 3 quorum queues (`default`, `high-priority`, `bulk`) — **flat queue names, identical on both transports**. Horizon consumes BOTH connections (`supervisor-horizon`/`supervisor-bulk` on redis-sentinel, `supervisor-rabbit` on rabbit-rs): all jobs appear in the Horizon dashboard.

## Modules

| Module | Contents |
|--------|----------|
| `RabbitRs` | All rabbit-rs jobs + commands (`rabbit-rs:demo --connection=redis-sentinel|rabbit-rs|both`) |
| `QueueLab` | `queue-lab:stress` burst command + sentinel event listeners |
| `FrontLab` | `/lab` dashboard (Horizon stats, queue depths, dispatch panel, 3s live polling) |

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
