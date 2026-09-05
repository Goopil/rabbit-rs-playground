# Rabbit RS Playground v2

A Laravel Sail full-stack playground for the [php-rabbit-rs](https://github.com/Goopil/php-rabbit-rs) native PHP extension (Rust-powered RabbitMQ transport), running on **Laravel Octane (Swoole)**, **Horizon on Valkey Sentinel**, and **Inertia SSR orchestrated by ClusterKit** — with code organized in [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules).

## Architecture

Everything runs in the single `laravel.test` Sail container under supervisord:

| Program | Role |
|---------|------|
| `php` | Octane/Swoole HTTP server (port 80) |
| `horizon` | Queue workers for `redis-sentinel` connection (`default`, `high-priority`, `bulk`) |
| `ssr` | Node ClusterKit orchestrator running the Inertia SSR server (`POST /render` on 127.0.0.1:13715) |
| `rabbit-rs-*` | RabbitMQ workers — **off by default** (`SUPERVISOR_RABBIT_RS_WORKERS=false`), re-enable for rabbit-rs consumption |

### Valkey HA set (compose)

1 master + 2 replicas + 3 sentinels (`valkey-master`, `valkey-1/2`, `sentinel-1/2/3`). Cache, sessions, Horizon and the `redis-sentinel` queue connection all go through the sentinels via [goopil/laravel-redis-sentinel](https://github.com/Goopil/laravel-redis-sentinel) (read/write splitting, master stickiness, failover events logged to `storage/logs/sentinel.log`).

### RabbitMQ infra

Two setups (single-node + 3-node quorum cluster), 3 vhosts, 32 queues — untouched by v2. RabbitMQ workers are disabled: dispatching to `rabbit-rs` accumulates messages in RabbitMQ until you re-enable workers or run `rabbit-rs:work`.

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

# 3. Create RabbitMQ vhosts and topology
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
| `make status` | rabbit-rs pool status |

## Dual Dispatch

```bash
sail artisan rabbit-rs:demo --connection=redis-sentinel   # → Horizon processes immediately
sail artisan rabbit-rs:demo --connection=rabbit-rs        # → messages accumulate in RabbitMQ
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
| RabbitMQ simple | http://localhost:15672 | guest / guest |
| RabbitMQ cluster | http://localhost:15673 | guest / guest |

## Configuration

- `config/rabbit-rs.php` — rabbit-rs brokers/topology (0.1.0 schema, untouched)
- `config/database.php` — sentinel-backed `default` + `cache` redis connections
- `config/horizon.php` — supervisors on `redis-sentinel`
- `config/queue.php` — `redis-sentinel` connection (`phpredis-sentinel` driver)
- `docker/8.5/supervisord.conf` — php / horizon / ssr / rabbit-rs programs
- `compose.yaml` — RabbitMQ, MySQL, Valkey HA set
- `.env` — `QUEUE_CONNECTION=redis-sentinel`, `SUPERVISOR_*` gates, `SSR_PORT`, `INERTIA_SSR_*`

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Horizon not connecting | Check sentinels: `sail exec sentinel-1 valkey-cli -p 26379 sentinel get-master-addr-by-name mymaster` |
| SSR down | Pages fall back to client-side rendering; check `sail exec laravel.test supervisorctl status ssr` |
| Extension not loaded | Rebuild: `make build && make up` |
| Cluster not forming | `sail down -v && sail up -d` (clears stale Erlang cookies) |
| Publish fails (unroutable) | `make setup-topology` |
| Composer rejects rabbit-rs-laravel | Must run inside Sail (`ext-rabbit_rs` only exists in the container); use `--ignore-platform-req=ext-rabbit_rs` when composer's platform check desyncs |
| 500 on first request of a fresh worker | Warm-up listener in `AppServiceProvider` resolves sentinel connections at Octane worker start (vendor lib fix tracked in `docs/upstream-fix-laravel-redis-sentinel.md`) |
