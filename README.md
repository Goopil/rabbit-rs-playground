# Rabbit RS Playground

A Laravel Sail playground for the [php-rabbit-rs](https://github.com/Goopil/php-rabbit-rs) native PHP extension (Rust-powered RabbitMQ transport) and [php-rabbit-rs-laravel](https://github.com/Goopil/php-rabbit-rs-laravel) queue driver.

## Features

- **Two simultaneous RabbitMQ setups**: single-node + 3-node quorum cluster
- **3 vhosts** per setup: `/default`, `/orders`, `/notifications`
- **8 queues** per setup (16 total) with quorum type, dead-lettering, and publisher confirms
- **8 demo jobs** exercising all vhosts and queues
- **Multi-broker configuration** showcasing rabbit-rs's multi-vhost and weighted-fair-scheduling capabilities

## Prerequisites

- Docker + Docker Compose v2
- Node.js (for frontend assets)

## Quick Start

```bash
# 1. Build the Sail image (installs the rabbit_rs extension)
make build

# 2. Start all containers (Laravel + MySQL + 2 RabbitMQ setups)
make up

# 3. Create RabbitMQ vhosts and topology (exchanges, queues, bindings)
make setup

# 4. Dispatch demo jobs to both setups
make demo

# 5. Start workers (in a separate terminal)
make workers-simple
make workers-cluster

# 6. Check status
make status
```

## Management UIs

| Setup | URL | Credentials |
|-------|-----|------------|
| Simple | http://localhost:15672 | guest / guest |
| Cluster | http://localhost:15673 | guest / guest |

## Queue Topology

| Vhost | Queue | Exchange |
|-------|-------|----------|
| `/default` | `default`, `high-priority` | `laravel.jobs` |
| `/orders` | `created`, `paid`, `shipped` | `laravel.orders` |
| `/notifications` | `email`, `sms`, `push` | `laravel.notifications` |

All queues use the `<setup>.<vhost>.<queue>` naming convention (e.g. `simple.default.default`).

## Worker Commands

```bash
# Simple setup workers
sail artisan rabbit-rs:work --queue=simple.default
sail artisan rabbit-rs:work --queue=simple.orders
sail artisan rabbit-rs:work --queue=simple.notifications

# Cluster setup workers
sail artisan rabbit-rs:work --queue=cluster.default
sail artisan rabbit-rs:work --queue=cluster.orders
sail artisan rabbit-rs:work --queue=cluster.notifications
```

## Configuration

- `config/rabbit-rs.php` — 6 brokers, 16 routes, 6 worker profiles
- `docker/8.5/Dockerfile` — rabbit-rs-native extension (manual binary download from GitHub Releases)
- `compose.yaml` — 4 RabbitMQ services (1 simple + 3 cluster nodes)
- `docker/rabbitmq/cluster/` — cluster peer discovery config
- `.env` — `RABBIT_RS_TOPOLOGY_MODE=external` (topology managed by `rabbit-rs:setup-topology` command)

## Setup Commands

| Command | Description |
|---------|-------------|
| `make setup-vhosts` | Create 3 vhosts on both RabbitMQ setups |
| `make setup-topology` | Create exchanges, queues, bindings, and dead-letter topology |
| `make setup` | Run both setup-vhosts and setup-topology |
| `make demo` | Dispatch 16 demo jobs across both setups |
| `make demo --delay` | Dispatch with some delayed jobs |
| `make status` | Show rabbit-rs pool status and metrics |

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Extension not loaded | Rebuild: `make build` then `make up` |
| Cluster not forming | Destroy volumes: `sail down -v && sail up -d` (clears stale Erlang cookies) |
| Publish fails (unroutable) | Run `make setup-topology` to create exchanges and bindings |
| Composer rejects rabbit-rs-laravel | Must run inside Sail: `sail composer require ...` (extension is in the container, not on macOS) |
| Vhost creation fails | Ensure RabbitMQ is healthy: `docker compose ps`, then re-run `make setup` |
| `rabbitmq:4.3-management` not found | Use `rabbitmq:4-management` in compose.yaml as fallback |
