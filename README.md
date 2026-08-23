# Rabbit RS Playground

A Laravel Sail playground for the [php-rabbit-rs](https://github.com/Goopil/php-rabbit-rs) native PHP extension (Rust-powered RabbitMQ transport) and [php-rabbit-rs-laravel](https://github.com/Goopil/php-rabbit-rs-laravel) queue driver.

## Features

- **Two simultaneous RabbitMQ setups**: single-node + 3-node quorum cluster
- **3 vhosts** per setup: `/default`, `/orders`, `/notifications`
- **32 queues** total (16 dedicated + 16 combined) with quorum type, dead-lettering, and publisher confirms
- **8 demo jobs** exercising all vhosts and queues
- **4 workers managed by supervisord** — 2 single-queue + 2 combined-queue, running in parallel on separate queues
- **Multi-broker configuration** showcasing rabbit-rs's multi-vhost and weighted-fair-scheduling capabilities

## Prerequisites

- Docker + Docker Compose v2
- Node.js (for frontend assets)

## Quick Start

```bash
# 1. Build the Sail image (installs the rabbit_rs extension)
make build

# 2. Start all containers (Laravel + MySQL + 2 RabbitMQ setups + 4 workers)
make up

# 3. Create RabbitMQ vhosts and topology (exchanges, queues, bindings)
make setup

# 4. Dispatch demo jobs (single mode by default)
make demo

# 5. Check worker status
make workers-status

# 6. Check rabbit-rs pool status
make status
```

## Supervised Workers

4 workers managed by supervisord, started automatically with `sail up`:

| Worker | Mode | Profile | Queues |
|--------|------|---------|--------|
| `rabbit-rs-simple-single` | Single | `simple.default` | `simple.default.default`, `simple.default.high-priority` |
| `rabbit-rs-cluster-single` | Single | `cluster.default` | `cluster.default.default`, `cluster.default.high-priority` |
| `rabbit-rs-simple-all` | Combined | `simple.all` | 8 queues `simple.all.*` (all vhosts) |
| `rabbit-rs-cluster-all` | Combined | `cluster.all` | 8 queues `cluster.all.*` (all vhosts) |

Workers run on **separate queues** — no overlap between single and combined modes.

### Worker Management

```bash
make workers-status     # Show all worker statuses
make workers-stop       # Stop all 4 workers
make workers-restart    # Restart all 4 workers

# Or manage individually:
sail exec supervisorctl stop rabbit-rs-simple-single
sail exec supervisorctl start rabbit-rs-simple-all
```

### Disable Workers

Set `SUPERVISOR_RABBIT_RS_WORKERS=false` in `.env` and rebuild:

```bash
SUPERVISOR_RABBIT_RS_WORKERS=false
```

## Demo Dispatch Modes

| Command | Mode | Queues | Jobs |
|---------|------|-------|------|
| `make demo` | `--mode=single` (default) | `*.default.*` | 16 |
| `make demo-combined` | `--mode=combined` | `*.all.*` | 16 |
| `make demo-both` | `--mode=both` | All | 32 |

## Management UIs

| Setup | URL | Credentials |
|-------|-----|------------|
| Simple | http://localhost:15672 | guest / guest |
| Cluster | http://localhost:15673 | guest / guest |

## Queue Topology

### Dedicated queues (single mode)

| Vhost | Queue | Exchange |
|-------|-------|----------|
| `/default` | `simple.default.default`, `simple.default.high-priority` | `laravel.jobs` |
| `/orders` | `simple.orders.created`, `simple.orders.paid`, `simple.orders.shipped` | `laravel.orders` |
| `/notifications` | `simple.notifications.email`, `simple.notifications.sms`, `simple.notifications.push` | `laravel.notifications` |

### Combined queues (combined mode)

| Vhost | Queue | Exchange |
|-------|-------|----------|
| `/default` | `simple.all.default`, `simple.all.high-priority` | `laravel.jobs` |
| `/orders` | `simple.all.orders.created`, `simple.all.orders.paid`, `simple.all.orders.shipped` | `laravel.orders` |
| `/notifications` | `simple.all.notifications.email`, `simple.all.notifications.sms`, `simple.all.notifications.push` | `laravel.notifications` |

Same pattern applies for `cluster.*` queues. All queues use the `<setup>.<vhost>.<queue>` naming convention.

## Configuration

- `config/rabbit-rs.php` — 6 brokers, 32 routes, 8 worker profiles
- `docker/8.5/Dockerfile` — rabbit-rs-native extension (manual binary download from GitHub Releases)
- `docker/8.5/supervisord.conf` — 4 worker programs + PHP server
- `compose.yaml` — 4 RabbitMQ services (1 simple + 3 cluster nodes)
- `docker/rabbitmq/cluster/` — cluster peer discovery config
- `.env` — `RABBIT_RS_TOPOLOGY_MODE=external`, `SUPERVISOR_RABBIT_RS_WORKERS=true`

## Setup Commands

| Command | Description |
|---------|-------------|
| `make setup-vhosts` | Create 3 vhosts on both RabbitMQ setups |
| `make setup-topology` | Create exchanges, queues, bindings, and dead-letter topology |
| `make setup` | Run both setup-vhosts and setup-topology |
| `make demo` | Dispatch 16 demo jobs (single mode) |
| `make demo-combined` | Dispatch 16 demo jobs (combined mode) |
| `make demo-both` | Dispatch 32 demo jobs (both modes) |
| `make status` | Show rabbit-rs pool status and metrics |
| `make workers-status` | Show supervisord worker statuses |
| `make workers-stop` | Stop all 4 workers |
| `make workers-restart` | Restart all 4 workers |

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Extension not loaded | Rebuild: `make build` then `make up` |
| Cluster not forming | Destroy volumes: `sail down -v && sail up -d` (clears stale Erlang cookies) |
| Publish fails (unroutable) | Run `make setup-topology` to create exchanges and bindings |
| Workers not starting | Check `SUPERVISOR_RABBIT_RS_WORKERS=true` in `.env`, rebuild with `make build` |
| Composer rejects rabbit-rs-laravel | Must run inside Sail: `sail composer require ...` (extension is in the container, not on macOS) |
| Vhost creation fails | Ensure RabbitMQ is healthy: `docker compose ps`, then re-run `make setup` |
| `rabbitmq:4.3-management` not found | Use `rabbitmq:4-management` in compose.yaml as fallback |
