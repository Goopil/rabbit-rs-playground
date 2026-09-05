# Playground v2 — Octane, Horizon/Sentinel, SSR ClusterKit, Modules

## Overview

Evolution of the rabbit-rs-playground from a rabbit-rs-only test bench into a full
stack playground exercising all of Goopil's libraries together:

- **Laravel Octane (Swoole)** replaces `artisan serve` as the HTTP server
- **Laravel Horizon** becomes the single queue runtime, consuming a **Valkey
  Sentinel cluster** via `goopil/laravel-redis-sentinel` (read/write splitting on)
- **Inertia SSR** served by a Node server orchestrated by **`@goopil/clusterkit`**
  (multi-worker, SO_REUSEPORT) instead of `@inertiajs/server`
- **nwidart/laravel-modules** structures the code into domain modules
- Everything (composer, npm, Docker base image) updated to latest

The rabbit-rs driver, topology, and demo jobs are kept intact and dispatched on
demand, but their `queue:work` supervisor programs are removed for now — they
come back later behind the dedicated `rabbit-rs:work` command.

## Decisions (validated with user)

| Topic | Decision |
|---|---|
| Container layout | Approach A: everything in `laravel.test` under supervisord |
| Valkey topology | HA: 1 master + 2 replicas + 3 sentinels, R/W splitting on, chaos testing |
| ClusterKit | Orchestrates the Inertia SSR Node server only |
| Modules | By domain: `RabbitRs`, `QueueLab`, `FrontLab` |
| Frontend | Dashboard playground: dispatch jobs, live stats, stress pages |
| Node | 26 |
| Queue runtime now | Horizon only (redis-sentinel queues); rabbit-rs workers removed from supervisord, revisit later with `rabbit-rs:work` |
| RabbitMQ | Infra + topology + config kept; dispatch to rabbit queues stays possible via `--connection` |

## Architecture

```
┌────────────────────────────────────────────────────────────────┐
│ Docker Compose                                                  │
│                                                                 │
│ laravel.test (Ubuntu 24.04, PHP 8.5, Node 26)                   │
│  supervisord:                                                   │
│   ├── php     → artisan octane:start --server=swoole :80        │
│   ├── horizon → php artisan horizon          (redis-sentinel)   │
│   ├── ssr     → node node/clusterkit-server.mjs :13715          │
│   └── (rabbit-rs queue:work programs REMOVED)                   │
│                                                                 │
│ valkey-master (6380) ← replic ← valkey-1 (6381), valkey-2 (6382)│
│ sentinel-1/2/3 (26379-81) monitor "mymaster", quorum 2          │
│                                                                 │
│ mysql 8.4 (unchanged)                                           │
│ rabbitmq-simple + rabbitmq-1/2/3 cluster (unchanged, idle)      │
└────────────────────────────────────────────────────────────────┘
```

## Stack & Dependencies

- `composer update` (Laravel 13.x latest) + `npm update` (React/Vite/Inertia latest)
- Dockerfile: `ARG NODE_VERSION=26` (only change; php8.5-swoole, php8.5-redis, PIE/rabbit_rs already present)
- Composer additions: `laravel/octane`, `laravel/horizon`, `goopil/laravel-redis-sentinel`,
  `nwidart/laravel-modules` (brings `wikimedia/composer-merge-plugin` — add to allow-plugins)
- npm addition: `@goopil/clusterkit` (dependencies, not dev)
- Composer commands run inside Sail (`sail composer ...`) because of the
  `ext-rabbit_rs` platform requirement

## Redis Sentinel (Valkey)

- Compose services: `valkey-master`, `valkey-1`, `valkey-2` (image `valkey/valkey:8`,
  replicas via `replicaof valkey-master 6379`), `sentinel-1/2/3` (internal port 26379,
  host ports 26379/26380/26381, one shared conf, writable copy in /data because
  sentinel rewrites its conf on failover)
- Sentinel conf: `sentinel monitor mymaster valkey-master 6379 2`,
  `resolve-hostnames yes`, `announce-hostnames yes`
- `config/database.php`: `client = phpredis-sentinel`, `default` connection with
  3 sentinels + `read_only_replicas: true` (R/W splitting exercised for real)
- `REDIS_SENTINEL_OVERRIDE_LARAVEL_REDIS=true` (default): cache + sessions +
  broadcast transparently go through Sentinel
- Publish + tune `config/phpredis-sentinel.php` (retry, node_cache.ttl default 15s)
- Exact Horizon wiring (`'use'` connection name, queue driver naming) follows the
  lib's published docs/source at implementation time — verified live, not guessed

## Horizon

- `horizon:install`; `config/horizon.php`: `'use'` → the sentinel connection
- `local` environment, 2 supervisors on connection `redis-sentinel`:
  - `supervisor-horizon`: queues `default,high-priority`, balance `auto`, 2 processes
  - `supervisor-bulk`: queue `bulk`, balance `simple`, 4 processes (stress target)
- Dashboard at `/horizon` (Blade, coexists with Inertia); gate: allow in local
- Lib probes (`horizon:ready`, `horizon:alive`, `horizon:pre-stop`) exposed via Makefile
- Sentinel events (`RedisSentinel*`) logged to a dedicated channel for observability

## Dual dispatch

- `config/queue.php` keeps `rabbit-rs` and adds `redis-sentinel`
- `.env`: `QUEUE_CONNECTION=redis-sentinel` (new default — everything through Horizon)
- Demo dispatch command gains `--connection=redis-sentinel|rabbit-rs|both`
  (default follows `QUEUE_CONNECTION`); jobs are agnostic, queue name passed at dispatch
- Dispatching to rabbit-rs queues without a running rabbit worker is expected:
  messages pile up, drained later when `rabbit-rs:work` returns

## Octane

- `octane:install --server=swoole`; `config/octane.php` warm/flush defaults
- Dockerfile `SUPERVISOR_PHP_COMMAND` → `artisan octane:start --host=0.0.0.0 --port=80`
- Vite dev flow unchanged (`sail npm run dev`); Octane serves built assets in prod mode
- The sentinel lib already resets sticky state on Octane lifecycle events — nothing to wire

## SSR via ClusterKit

- Add SSR entrypoint `resources/js/ssr.jsx` (createInertiaApp ssr:true), Vite ssr
  build → `bootstrap/ssr/ssr.mjs`; npm script `build:ssr`
- `node/clusterkit-server.mjs`: Orchestrator + HTTP server on `127.0.0.1:13715`
  exposing `POST /render` (same protocol as `@inertiajs/server`), importing the built
  ssr bundle; `WEB_CONCURRENCY` env to size workers (SO_REUSEPORT inside Linux container)
- `config/inertia.php`: `ssr.enabled=true`, `ssr.url=http://127.0.0.1:13715`
- supervisord program `ssr` gated by `SUPERVISOR_SSR`
- Fallback: if SSR server is down, Inertia renders client-side (Laravel default behaviour)

## Modules (nwidart v13)

- Install, publish config, `Modules\\` psr-4 autoload
- `php artisan module:make RabbitRs QueueLab FrontLab`
- **RabbitRs**: the 8 demo jobs + `rabbit-rs:setup-vhosts`, `rabbit-rs:setup-topology`,
  `rabbit-rs:status`, `rabbit-rs:demo` (gains `--connection`); `config/rabbit-rs.php`
  stays app-level (package-published)
- **QueueLab**: `queue-lab:stress` (burst dispatch N jobs to a target queue/connection,
  with configurable sleep/fail ratios for Horizon retries), sentinel event listeners
- **FrontLab**: dashboard controllers + Inertia pages, module web routes
- Old `app/Jobs`, `app/Console/Commands` deleted after migration

## Frontend dashboard (FrontLab)

- Inertia + React, existing Breeze layout, Ziggy routes
- **Dashboard**: cards (Octane uptime/workers via `octane:status`-style data,
  Horizon throughput, rabbit-rs pool status, Valkey master address from Sentinel) +
  dispatch panel (job × connection × count) + activity feed (polling)
- **Stress** page: form → `queue-lab:stress` logic via controller, live queue depth
- Keep it thin: 2 pages, polling, no websockets for v2

## Supervisord (final)

| Program | Command | Gate |
|---|---|---|
| php | octane:start (swoole) | always |
| horizon | `php artisan horizon` | `SUPERVISOR_HORIZON=true` |
| ssr | node clusterkit-server | `SUPERVISOR_SSR=true` |

Removed: the 4 `rabbit-rs-*` `queue:work` programs and `SUPERVISOR_RABBIT_RS_WORKERS`.

## Makefile

New: `ssr`, `horizon-status`, `horizon-probes`, `chaos-kill-master`, `chaos-heal`,
`sentinel-watch` (stream `+switch-master` events), `demo` gains `--connection`.
Removed: `workers-status/stop/restart`, `check-queues` simplified.

## Cleanup

- supervisord rabbit-rs programs, `SUPERVISOR_RABBIT_RS_WORKERS`
- `app/Jobs/*`, `app/Console/Commands/*` (migrated to RabbitRs module)
- Makefile worker targets, README rewritten for the new architecture

## Verification

1. `make build && make up` — image builds with Node 26, all services healthy
2. `sail artisan migrate && make setup` — rabbit topology still created
3. `sail php -m` shows swoole + redis; `sail artisan octane:status` OK
4. Horizon boots; `/horizon` reachable; `make horizon-probes` pass
5. `make demo` → jobs consumed by Horizon (redis-sentinel) — visible in dashboard
6. `make demo -- --connection=rabbit-rs` → messages accumulate in Rabbit (expected)
7. `curl /` returns SSR-rendered HTML (data-page attribute); clusterkit logs show N workers
8. Chaos: `make chaos-kill-master` → sentinel elects replica (`+switch-master` in
   `sentinel-watch`), Horizon reconnects via lib retry/events, `make demo` still works
9. `sail composer test` (phpunit) green
