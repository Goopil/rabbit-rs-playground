# Rabbit RS Playground — Design Spec

## Overview

A Laravel application using Laravel Sail as the Docker environment, with the
`goopil/rabbit-rs` native PHP extension (Rust-powered RabbitMQ transport) and
`goopil/rabbit-rs-laravel` queue driver installed. The playground provides two
simultaneously running RabbitMQ setups — a single-node broker and a 3-node
quorum cluster — each with 3 vhosts and multiple queues, to exercise the
extension's multi-broker, multi-vhost, and weighted-fair-scheduling features.

## Prerequisites Confirmed

- macOS host (darwin)
- PHP 8.5.6, Composer 2.10.0, Laravel installer 5.11.0
- npm 10.8.2, bun 1.2.21
- Docker 29.5.2 with Compose v2
- Target directory: `/Users/zacharyvolpi/dev/perso/rabbit-rs-playground` (empty)

## Constraints

- The `rabbit_rs.so` native extension ships as pre-compiled Linux binaries
  (x86_64/ARM64, glibc/musl). macOS is not supported. The extension must run
  inside Docker containers.
- The Laravel bridge's `composer.json` declares `"ext-rabbit_rs": "^1.0"`,
  meaning Composer will refuse to install `goopil/rabbit-rs-laravel` unless the
  extension is already loaded in the PHP runtime. The extension must be
  baked into the Docker image before any `composer require` command runs.
- RabbitMQ 4.3.x is required by the extension.
- PIE (PHP Installer for Extensions) 1.5+ is the installation tool for the
  native extension.

## Architecture

```
┌─────────────────────────────────────────────────────┐
│  Host: macOS                                         │
│                                                      │
│  Docker Compose (Laravel Sail)                       │
│  ┌──────────────────────────────────────────────┐   │
│  │ sail container (Ubuntu 24.04, PHP 8.5)        │   │
│  │  ├── ext-rabbit_rs.so (via PIE)               │   │
│  │  ├── goopil/rabbit-rs-laravel (Composer)      │   │
│  │  ├── Laravel app (React starter kit)          │   │
│  │  ├── MySQL client                             │   │
│  │  └── Demo jobs + routes + workers             │   │
│  └──────────────────────────────────────────────┘   │
│                                                      │
│  ┌─────────────────┐  ┌──────────────────────────┐  │
│  │ rabbitmq-simple  │  │ Cluster (3 nodes)        │  │
│  │ (single node)    │  │ rabbitmq-1 / -2 / -3     │  │
│  │ 3 vhosts         │  │ 3 vhosts (replicated)    │  │
│  │ 8 queues         │  │ 8 quorum queues          │  │
│  └─────────────────┘  └──────────────────────────┘  │
│                                                      │
│  ┌─────────────────┐                                 │
│  │ MySQL           │                                 │
│  └─────────────────┘                                 │
└─────────────────────────────────────────────────────┘
```

## Naming Convention

All route names, Laravel queue names, and AMQP queue names use a **dotted
prefix** to distinguish between setups and vhosts:

```
<setup>.<vhost>.<queue>
```

Examples: `simple.default.default`, `cluster.orders.paid`

This ensures:
- Route names are unique across both setups
- `routing_key: '{queue}'` produces the correct AMQP queue name
- Worker subscription `queue` values match the route names exactly
- Dispatch uses `->onQueue('simple.default.default')` — clear and explicit

## Step-by-Step Plan

### Step 1: Create the Laravel Application

```bash
laravel new . --database=mysql --react --npm --boost --no-interaction
```

- Installs into the current directory (`.`)
- MySQL as default database
- React starter kit
- npm as JS package manager
- `--boost` for AI-assisted development guidelines

After creation, read `CLAUDE.md` or `AGENTS.md` for Boost guidelines.

### Step 2: Install Laravel Sail

```bash
composer require laravel/sail --dev
php artisan sail:install --with=mysql
```

This publishes:
- `docker/8.5/Dockerfile` — the PHP image definition
- `docker/8.5/php.ini` — PHP CLI config
- `docker/8.5/start-container` — container entrypoint
- `docker/8.5/supervisord.conf` — supervisor config
- `docker-compose.yml` — service definitions

### Step 3: Customize the Dockerfile for the Rabbit RS Extension

Modify `docker/8.5/Dockerfile` to add PIE and the extension **after** the
PHP packages are installed but **before** any Composer commands.

Insert after the `PHP_EXTENSIONS` conditional block and before `setcap`:

```dockerfile
# Install PIE (PHP Installer for Extensions)
RUN curl -L https://github.com/php/pie/releases/latest/download/pie.phar -o /usr/local/bin/pie \
    && chmod +x /usr/local/bin/pie

# Install the Rabbit RS native extension
RUN pie install goopil/rabbit-rs-native

# Verify the extension is loaded
RUN php --ri rabbit_rs
```

The Sail Dockerfile already installs `php8.5-dev` (providing `phpize` and
`php-config` needed by PIE) and uses the Sury PPA packages on Ubuntu 24.04
with glibc. PIE will download the matching pre-compiled binary.

### Step 4: Add RabbitMQ Services to docker-compose.yml

Add both RabbitMQ setups as always-on services (no profiles). Both run
simultaneously.

#### Simple Setup — Single Node

```yaml
rabbitmq-simple:
    image: rabbitmq:4.3-management
    hostname: rabbitmq-simple
    ports:
        - '${RABBITMQ_SIMPLE_AMQP_PORT:-5672}:5672'
        - '${RABBITMQ_SIMPLE_MGMT_PORT:-15672}:15672'
    environment:
        RABBITMQ_DEFAULT_USER: '${RABBITMQ_SIMPLE_USER:-guest}'
        RABBITMQ_DEFAULT_PASS: '${RABBITMQ_SIMPLE_PASS:-guest}'
        RABBITMQ_DEFAULT_VHOST: '${RABBITMQ_SIMPLE_VHOST:-/}'
    volumes:
        - 'sail-rabbitmq-simple:/var/lib/rabbitmq'
    networks:
        - sail
    healthcheck:
        test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
        interval: 10s
        timeout: 5s
        retries: 5
```

#### Cluster Setup — 3 Nodes

Three services forming a RabbitMQ cluster via classic config peer discovery.
All share the same Erlang cookie. Node 1 is the seed; nodes 2 and 3 join it.

```yaml
rabbitmq-1:
    image: rabbitmq:4.3-management
    hostname: rabbitmq-1
    ports:
        - '${RABBITMQ_CLUSTER_AMQP_PORT:-5673}:5672'
        - '${RABBITMQ_CLUSTER_MGMT_PORT:-15673}:15672'
    environment:
        RABBITMQ_ERLANG_COOKIE: 'rabbit-rs-cluster-cookie'
        RABBITMQ_DEFAULT_USER: '${RABBITMQ_CLUSTER_USER:-guest}'
        RABBITMQ_DEFAULT_PASS: '${RABBITMQ_CLUSTER_PASS:-guest}'
        RABBITMQ_DEFAULT_VHOST: '${RABBITMQ_CLUSTER_VHOST:-/}'
    volumes:
        - 'sail-rabbitmq-1:/var/lib/rabbitmq'
        - './docker/rabbitmq/cluster/rabbitmq.conf:/etc/rabbitmq/rabbitmq.conf:ro'
        - './docker/rabbitmq/cluster/enabled_plugins:/etc/rabbitmq/enabled_plugins:ro'
    networks:
        - sail
    healthcheck:
        test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
        interval: 10s
        timeout: 5s
        retries: 5

rabbitmq-2:
    image: rabbitmq:4.3-management
    hostname: rabbitmq-2
    environment:
        RABBITMQ_ERLANG_COOKIE: 'rabbit-rs-cluster-cookie'
        RABBITMQ_DEFAULT_USER: '${RABBITMQ_CLUSTER_USER:-guest}'
        RABBITMQ_DEFAULT_PASS: '${RABBITMQ_CLUSTER_PASS:-guest}'
        RABBITMQ_DEFAULT_VHOST: '${RABBITMQ_CLUSTER_VHOST:-/}'
    volumes:
        - 'sail-rabbitmq-2:/var/lib/rabbitmq'
        - './docker/rabbitmq/cluster/rabbitmq.conf:/etc/rabbitmq/rabbitmq.conf:ro'
        - './docker/rabbitmq/cluster/enabled_plugins:/etc/rabbitmq/enabled_plugins:ro'
    networks:
        - sail
    depends_on:
        rabbitmq-1:
            condition: service_healthy
    healthcheck:
        test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
        interval: 10s
        timeout: 5s
        retries: 5

rabbitmq-3:
    image: rabbitmq:4.3-management
    hostname: rabbitmq-3
    environment:
        RABBITMQ_ERLANG_COOKIE: 'rabbit-rs-cluster-cookie'
        RABBITMQ_DEFAULT_USER: '${RABBITMQ_CLUSTER_USER:-guest}'
        RABBITMQ_DEFAULT_PASS: '${RABBITMQ_CLUSTER_PASS:-guest}'
        RABBITMQ_DEFAULT_VHOST: '${RABBITMQ_CLUSTER_VHOST:-/}'
    volumes:
        - 'sail-rabbitmq-3:/var/lib/rabbitmq'
        - './docker/rabbitmq/cluster/rabbitmq.conf:/etc/rabbitmq/rabbitmq.conf:ro'
        - './docker/rabbitmq/cluster/enabled_plugins:/etc/rabbitmq/enabled_plugins:ro'
    networks:
        - sail
    depends_on:
        rabbitmq-1:
            condition: service_healthy
    healthcheck:
        test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
        interval: 10s
        timeout: 5s
        retries: 5
```

#### Volumes

Add to the `volumes:` top-level key:

```yaml
sail-rabbitmq-simple:
    driver: local
sail-rabbitmq-1:
    driver: local
sail-rabbitmq-2:
    driver: local
sail-rabbitmq-3:
    driver: local
```

### Step 5: RabbitMQ Cluster Config Files

Create `docker/rabbitmq/cluster/rabbitmq.conf`:

```ini
# Cluster formation via classic config
cluster_formation.peer_discovery_backend = classic_config
cluster_formation.classic_config.nodes.1 = rabbitmq-1
cluster_formation.classic_config.nodes.2 = rabbitmq-2
cluster_formation.classic_config.nodes.3 = rabbitmq-3

# Auto-handle partitioning (pause-minority for quorum queues)
cluster_partition_handling = pause_minority

# Enable management plugin by default
management.tcp.port = 15672

# Default vhost (additional vhosts created via init script)
default_v_host = /
```

Create `docker/rabbitmq/cluster/enabled_plugins`:

```erlang
[rabbitmq_management,rabbitmq_peer_discovery_classic_config].
```

### Step 6: Vhost Provisioning

RabbitMQ starts with only `/` vhost. We need 3 vhosts: `/default`,
`/orders`, `/notifications` in both setups.

Create an Artisan command `rabbit-rs:setup-vhosts` that:
1. Connects to each RabbitMQ setup via the Management API (HTTP, port 15672)
2. Creates the 3 vhosts on each broker
3. Sets permissions for the guest user

This command runs after `sail up` and before dispatching demo jobs.

Alternatively, a shell script `docker/rabbitmq/init-vhosts.sh` can be run
from the sail container:

```bash
#!/bin/bash
# Simple setup
for vhost in "/default" "/orders" "/notifications"; do
    rabbitmqctl -n rabbitmq-simple add_vhost "$vhost"
    rabbitmqctl -n rabbitmq-simple set_permissions -p "$vhost" guest ".*" ".*" ".*"
done
# Cluster setup
for vhost in "/default" "/orders" "/notifications"; do
    rabbitmqctl -n rabbitmq-1 add_vhost "$vhost"
    rabbitmqctl -n rabbitmq-1 set_permissions -p "$vhost" guest ".*" ".*" ".*"
done
```

This script executes inside the rabbitmq containers. It can be called via:
```bash
docker exec rabbitmq-simple /path/to/init-vhosts.sh
```

Or embedded in a Makefile target.

### Step 7: Install the Laravel Bridge

After the Docker image is built with the extension, install the Composer
package from within the Sail container:

```bash
sail composer require goopil/rabbit-rs-laravel
```

Then publish the config:

```bash
sail artisan vendor:publish --tag="rabbit-rs-config"
```

This creates `config/rabbit-rs.php` with defaults.

### Step 8: Configure rabbit-rs.php

Replace the published `config/rabbit-rs.php` with the full multi-broker,
multi-vhost configuration.

#### Brokers (6 total — 2 setups × 3 vhosts)

Each unique vhost gets its own connection pool. The extension manages
one AMQP connection per vhost per broker.

```php
'brokers' => [
    // === Simple Setup ===
    'simple.default' => [
        'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
        'vhost' => '/default',
        'credentials' => [
            'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
            'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
        ],
        'heartbeat' => 30,
    ],
    'simple.orders' => [
        'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
        'vhost' => '/orders',
        'credentials' => [
            'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
            'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
        ],
        'heartbeat' => 30,
    ],
    'simple.notifications' => [
        'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
        'vhost' => '/notifications',
        'credentials' => [
            'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
            'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
        ],
        'heartbeat' => 30,
    ],
    // === Cluster Setup ===
    'cluster.default' => [
        'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
        'vhost' => '/default',
        'credentials' => [
            'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
            'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
        ],
        'heartbeat' => 30,
    ],
    'cluster.orders' => [
        'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
        'vhost' => '/orders',
        'credentials' => [
            'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
            'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
        ],
        'heartbeat' => 30,
    ],
    'cluster.notifications' => [
        'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
        'vhost' => '/notifications',
        'credentials' => [
            'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
            'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
        ],
        'heartbeat' => 30,
    ],
],
```

#### Routes (16 — one per queue per setup)

Routes map Laravel queue names to broker + exchange + routing key.
The route name = the Laravel queue name = the AMQP queue name (since
`routing_key: '{queue}'` substitutes the queue name).

```php
'routes' => [
    // Simple — /default vhost
    'simple.default.default' => [
        'broker' => 'simple.default',
        'exchange' => 'laravel.jobs',
        'routing_key' => '{queue}',
    ],
    'simple.default.high-priority' => [
        'broker' => 'simple.default',
        'exchange' => 'laravel.jobs',
        'routing_key' => '{queue}',
    ],
    // Simple — /orders vhost
    'simple.orders.created' => [
        'broker' => 'simple.orders',
        'exchange' => 'laravel.orders',
        'routing_key' => '{queue}',
    ],
    'simple.orders.paid' => [
        'broker' => 'simple.orders',
        'exchange' => 'laravel.orders',
        'routing_key' => '{queue}',
    ],
    'simple.orders.shipped' => [
        'broker' => 'simple.orders',
        'exchange' => 'laravel.orders',
        'routing_key' => '{queue}',
    ],
    // Simple — /notifications vhost
    'simple.notifications.email' => [
        'broker' => 'simple.notifications',
        'exchange' => 'laravel.notifications',
        'routing_key' => '{queue}',
    ],
    'simple.notifications.sms' => [
        'broker' => 'simple.notifications',
        'exchange' => 'laravel.notifications',
        'routing_key' => '{queue}',
    ],
    'simple.notifications.push' => [
        'broker' => 'simple.notifications',
        'exchange' => 'laravel.notifications',
        'routing_key' => '{queue}',
    ],
    // Cluster — /default vhost
    'cluster.default.default' => [
        'broker' => 'cluster.default',
        'exchange' => 'laravel.jobs',
        'routing_key' => '{queue}',
    ],
    'cluster.default.high-priority' => [
        'broker' => 'cluster.default',
        'exchange' => 'laravel.jobs',
        'routing_key' => '{queue}',
    ],
    // Cluster — /orders vhost
    'cluster.orders.created' => [
        'broker' => 'cluster.orders',
        'exchange' => 'laravel.orders',
        'routing_key' => '{queue}',
    ],
    'cluster.orders.paid' => [
        'broker' => 'cluster.orders',
        'exchange' => 'laravel.orders',
        'routing_key' => '{queue}',
    ],
    'cluster.orders.shipped' => [
        'broker' => 'cluster.orders',
        'exchange' => 'laravel.orders',
        'routing_key' => '{queue}',
    ],
    // Cluster — /notifications vhost
    'cluster.notifications.email' => [
        'broker' => 'cluster.notifications',
        'exchange' => 'laravel.notifications',
        'routing_key' => '{queue}',
    ],
    'cluster.notifications.sms' => [
        'broker' => 'cluster.notifications',
        'exchange' => 'laravel.notifications',
        'routing_key' => '{queue}',
    ],
    'cluster.notifications.push' => [
        'broker' => 'cluster.notifications',
        'exchange' => 'laravel.notifications',
        'routing_key' => '{queue}',
    ],
],
```

#### Workers (6 — one per vhost per setup)

Each worker profile defines subscriptions for all queues in that vhost.
The subscription `queue` values match the route names exactly.

```php
'workers' => [
    // === Simple — /default vhost ===
    'simple.default' => [
        'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 64],
        'subscriptions' => [
            'default' => [
                'enabled' => true,
                'broker' => 'simple.default',
                'queue' => 'simple.default.default',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 16],
                'starvation_after' => 30,
            ],
            'high-priority' => [
                'enabled' => true,
                'broker' => 'simple.default',
                'queue' => 'simple.default.high-priority',
                'weight' => 4,
                'priority_class' => -1,
                'prefetch' => ['mode' => 'fixed', 'value' => 16],
                'starvation_after' => 15,
            ],
        ],
    ],
    // === Simple — /orders vhost ===
    'simple.orders' => [
        'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 64],
        'subscriptions' => [
            'created' => [
                'enabled' => true,
                'broker' => 'simple.orders',
                'queue' => 'simple.orders.created',
                'weight' => 2,
                'priority_class' => -1,
                'prefetch' => ['mode' => 'fixed', 'value' => 16],
                'starvation_after' => 30,
            ],
            'paid' => [
                'enabled' => true,
                'broker' => 'simple.orders',
                'queue' => 'simple.orders.paid',
                'weight' => 2,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 16],
                'starvation_after' => 30,
            ],
            'shipped' => [
                'enabled' => true,
                'broker' => 'simple.orders',
                'queue' => 'simple.orders.shipped',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 16],
                'starvation_after' => 30,
            ],
        ],
    ],
    // === Simple — /notifications vhost ===
    'simple.notifications' => [
        'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 64],
        'subscriptions' => [
            'email' => [
                'enabled' => true,
                'broker' => 'simple.notifications',
                'queue' => 'simple.notifications.email',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 16],
                'starvation_after' => 30,
            ],
            'sms' => [
                'enabled' => true,
                'broker' => 'simple.notifications',
                'queue' => 'simple.notifications.sms',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 16],
                'starvation_after' => 30,
            ],
            'push' => [
                'enabled' => true,
                'broker' => 'simple.notifications',
                'queue' => 'simple.notifications.push',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 16],
                'starvation_after' => 30,
            ],
        ],
    ],
    // === Cluster — /default vhost ===
    'cluster.default' => [
        'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 128],
        'subscriptions' => [
            'default' => [
                'enabled' => true,
                'broker' => 'cluster.default',
                'queue' => 'cluster.default.default',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 32],
                'starvation_after' => 30,
            ],
            'high-priority' => [
                'enabled' => true,
                'broker' => 'cluster.default',
                'queue' => 'cluster.default.high-priority',
                'weight' => 4,
                'priority_class' => -1,
                'prefetch' => ['mode' => 'fixed', 'value' => 32],
                'starvation_after' => 15,
            ],
        ],
    ],
    // === Cluster — /orders vhost ===
    'cluster.orders' => [
        'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 128],
        'subscriptions' => [
            'created' => [
                'enabled' => true,
                'broker' => 'cluster.orders',
                'queue' => 'cluster.orders.created',
                'weight' => 2,
                'priority_class' => -1,
                'prefetch' => ['mode' => 'fixed', 'value' => 32],
                'starvation_after' => 30,
            ],
            'paid' => [
                'enabled' => true,
                'broker' => 'cluster.orders',
                'queue' => 'cluster.orders.paid',
                'weight' => 2,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 32],
                'starvation_after' => 30,
            ],
            'shipped' => [
                'enabled' => true,
                'broker' => 'cluster.orders',
                'queue' => 'cluster.orders.shipped',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 32],
                'starvation_after' => 30,
            ],
        ],
    ],
    // === Cluster — /notifications vhost ===
    'cluster.notifications' => [
        'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 128],
        'subscriptions' => [
            'email' => [
                'enabled' => true,
                'broker' => 'cluster.notifications',
                'queue' => 'cluster.notifications.email',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 32],
                'starvation_after' => 30,
            ],
            'sms' => [
                'enabled' => true,
                'broker' => 'cluster.notifications',
                'queue' => 'cluster.notifications.sms',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 32],
                'starvation_after' => 30,
            ],
            'push' => [
                'enabled' => true,
                'broker' => 'cluster.notifications',
                'queue' => 'cluster.notifications.push',
                'weight' => 1,
                'priority_class' => 0,
                'prefetch' => ['mode' => 'fixed', 'value' => 32],
                'starvation_after' => 30,
            ],
        ],
    ],
],
```

#### Topology (quorum queues, durable, with dead-letter)

```php
'topology' => [
    'queue' => [
        'type' => 'quorum',
        'durable' => true,
        'delivery_limit' => 20,
    ],
    'dead_letter' => [
        'exchange' => 'dead-letters',
        'queue' => 'failed-jobs',
        'routing_key' => null,
    ],
],
```

#### Publisher (confirms + mandatory)

```php
'publisher' => [
    'confirms' => true,
    'mandatory' => true,
    'confirm_timeout' => (int) env('RABBIT_RS_CONFIRM_TIMEOUT', 30000),
],
```

#### Delay (auto-detect plugin, fall back to TTL buckets)

```php
'delay' => [
    'mode' => env('RABBIT_RS_DELAY_MODE', 'auto'),
    'buckets' => array_map('intval', array_filter(array_map('trim', explode(',', env('RABBIT_RS_DELAY_BUCKETS', '1,5,30,120'))))),
    'max_buckets' => (int) env('RABBIT_RS_DELAY_MAX_BUCKETS', 8),
    'queue_expiry_margin' => (int) env('RABBIT_RS_DELAY_QUEUE_EXPIRY_MARGIN', 60),
    'detection_timeout' => (int) env('RABBIT_RS_DELAY_DETECTION_TIMEOUT', 5),
],
```

### Step 9: Environment Variables

Add to `.env`:

```env
QUEUE_CONNECTION=rabbit-rs

# Simple RabbitMQ setup
RABBITMQ_SIMPLE_AMQP_PORT=5672
RABBITMQ_SIMPLE_MGMT_PORT=15672
RABBITMQ_SIMPLE_USER=guest
RABBITMQ_SIMPLE_PASS=guest
RABBITMQ_SIMPLE_VHOST=/
RABBIT_RS_SIMPLE_HOSTS=rabbitmq-simple:5672
RABBIT_RS_SIMPLE_USER=guest
RABBIT_RS_SIMPLE_PASS=guest

# Cluster RabbitMQ setup
RABBITMQ_CLUSTER_AMQP_PORT=5673
RABBITMQ_CLUSTER_MGMT_PORT=15673
RABBITMQ_CLUSTER_USER=guest
RABBITMQ_CLUSTER_PASS=guest
RABBITMQ_CLUSTER_VHOST=/
RABBIT_RS_CLUSTER_HOSTS=rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672
RABBIT_RS_CLUSTER_USER=guest
RABBIT_RS_CLUSTER_PASS=guest

# Extension defaults
RABBIT_RS_EXCHANGE=laravel.jobs
RABBIT_RS_HEARTBEAT=30
RABBIT_RS_MAX_IN_FLIGHT=64
RABBIT_RS_PREFETCH=16
RABBIT_RS_CONFIRM_TIMEOUT=30000
RABBIT_RS_TOPOLOGY_MODE=declare
RABBIT_RS_DELAY_MODE=auto
```

### Step 10: Queue Connection in config/queue.php

Add the `rabbit-rs` connection:

```php
'connections' => [
    // ... existing connections ...
    'rabbit-rs' => [
        'driver' => 'rabbit-rs',
        'queue' => env('RABBIT_RS_QUEUE', 'simple.default.default'),
    ],
],
```

### Step 11: Demo Jobs

Create 8 job classes in `app/Jobs/`:

| Job Class | Vhost | Queue Name | Behaviour |
|-----------|-------|------------|-----------|
| `ProcessDefaultJob` | `/default` | `*.default.default` | Sleeps 2s, logs "Processing default job #{id}" |
| `ProcessHighPriorityJob` | `/default` | `*.default.high-priority` | Sleeps 1s, logs priority info |
| `ProcessOrderCreated` | `/orders` | `*.orders.created` | Simulates order creation (logs order data) |
| `ProcessOrderPaid` | `/orders` | `*.orders.paid` | Simulates payment (logs payment confirmation) |
| `ProcessOrderShipped` | `/orders` | `*.orders.shipped` | Simulates shipping (logs tracking number) |
| `SendEmailNotification` | `/notifications` | `*.notifications.email` | Simulates email send (logs recipient + subject) |
| `SendSmsNotification` | `/notifications` | `*.notifications.sms` | Simulates SMS send (logs phone number) |
| `SendPushNotification` | `/notifications` | `*.notifications.push` | Simulates push notification (logs device token) |

The `*` denotes either `simple` or `cluster` prefix. Each job class is
generic — it works on both setups. The setup is determined by the queue
name passed via `onQueue()`.

Each job:
- Implements `ShouldQueue`
- Uses `Dispatchable`, `Queueable` traits
- Accepts a payload (array or DTO)
- Logs execution with context (job name, queue, broker, payload)
- Has configurable delay option for testing delayed messages

### Step 12: Demo Dispatch Command

Create `app/Console/Commands/RabbitRsDemoCommand.php`:

```
php artisan rabbit-rs:demo [--setup=simple|cluster|both] [--delay]
```

Dispatches jobs across both setups and all vhosts/queues:

- `--setup=simple` — only simple setup
- `--setup=cluster` — only cluster setup
- `--setup=both` (default) — both setups
- `--delay` — dispatches some jobs with delays to test the delay feature

Example dispatch calls within the command:

```php
// Simple setup
ProcessDefaultJob::dispatch($data)->onQueue('simple.default.default');
ProcessHighPriorityJob::dispatch($data)->onQueue('simple.default.high-priority');
ProcessOrderCreated::dispatch($data)->onQueue('simple.orders.created');
// ... etc for all queues

// Cluster setup
ProcessDefaultJob::dispatch($data)->onQueue('cluster.default.default');
ProcessHighPriorityJob::dispatch($data)->onQueue('cluster.default.high-priority');
ProcessOrderCreated::dispatch($data)->onQueue('cluster.orders.created');
// ... etc for all queues
```

The command outputs a summary table showing what was dispatched and to which
broker/vhost/queue.

### Step 13: Vhost Setup Command

Create `app/Console/Commands/RabbitRsSetupVhostsCommand.php`:

```
php artisan rabbit-rs:setup-vhosts
```

Uses the RabbitMQ Management HTTP API (port 15672) to:
1. Create vhosts `/default`, `/orders`, `/notifications` on the simple broker
2. Create the same vhosts on the cluster (via node 1)
3. Set permissions for the guest user on each vhost
4. Output confirmation

This command is idempotent — it skips vhosts that already exist.

### Step 14: Makefile

Create a `Makefile` at the project root:

```makefile
.PHONY: up down build demo setup-vhosts status workers workers-cluster workers-simple

# Build and start all containers
build:
	./vendor/bin/sail build --no-cache

up:
	./vendor/bin/sail up -d

down:
	./vendor/bin/sail down

# Provision RabbitMQ vhosts
setup-vhosts:
	./vendor/bin/sail artisan rabbit-rs:setup-vhosts

# Dispatch demo jobs
demo:
	./vendor/bin/sail artisan rabbit-rs:demo

# Check Rabbit RS status
status:
	./vendor/bin/sail artisan rabbit-rs:status

# Start all simple workers (in separate terminals)
workers-simple:
	@echo "Starting simple workers..."
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.default &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.orders &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.notifications &
	@echo "Simple workers started in background"

# Start all cluster workers
workers-cluster:
	@echo "Starting cluster workers..."
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.default &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.orders &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.notifications &
	@echo "Cluster workers started in background"

# Start all workers
workers: workers-simple workers-cluster
```

### Step 15: Verification

After everything is set up, verify end-to-end:

1. `sail build --no-cache` — builds image with the extension
2. `sail up -d` — starts all containers
3. `sail artisan rabbit-rs:setup-vhosts` — creates vhosts
4. `sail php -m | grep rabbit_rs` — confirms extension loaded
5. `sail artisan rabbit-rs:status` — confirms broker connections
6. `sail artisan rabbit-rs:demo` — dispatches jobs to both setups
7. Open http://localhost:15672 — simple management UI (guest/guest)
8. Open http://localhost:15673 — cluster management UI (guest/guest)
9. Start workers and see jobs being consumed

### Step 16: README

Create a `README.md` documenting:
- Project purpose
- Prerequisites
- Setup steps (build, up, setup-vhosts, demo)
- How to start workers (simple vs cluster)
- Management UI URLs and credentials
- Configuration reference (vhosts, queues, brokers, routes)
- Troubleshooting tips

## Management UI Access

| Setup | URL | Credentials |
|-------|-----|------------|
| Simple | http://localhost:15672 | guest / guest |
| Cluster | http://localhost:15673 | guest / guest |

## Queue Topology Summary

| Vhost | Queue Name (simple) | Queue Name (cluster) | Exchange |
|-------|---------------------|----------------------|----------|
| `/default` | `simple.default.default` | `cluster.default.default` | `laravel.jobs` |
| `/default` | `simple.default.high-priority` | `cluster.default.high-priority` | `laravel.jobs` |
| `/orders` | `simple.orders.created` | `cluster.orders.created` | `laravel.orders` |
| `/orders` | `simple.orders.paid` | `cluster.orders.paid` | `laravel.orders` |
| `/orders` | `simple.orders.shipped` | `cluster.orders.shipped` | `laravel.orders` |
| `/notifications` | `simple.notifications.email` | `cluster.notifications.email` | `laravel.notifications` |
| `/notifications` | `simple.notifications.sms` | `cluster.notifications.sms` | `laravel.notifications` |
| `/notifications` | `simple.notifications.push` | `cluster.notifications.push` | `laravel.notifications` |

All queues: quorum type, durable, delivery_limit=20, dead-letter to `failed-jobs`.

## Files Created/Modified

| File | Action |
|------|--------|
| `docker/8.5/Dockerfile` | Modified — add PIE + rabbit-rs-native |
| `docker-compose.yml` | Modified — add RabbitMQ services + volumes |
| `docker/rabbitmq/cluster/rabbitmq.conf` | Created — cluster peer discovery config |
| `docker/rabbitmq/cluster/enabled_plugins` | Created — enable management + peer discovery |
| `docker/rabbitmq/init-vhosts.sh` | Created — vhost provisioning script |
| `.env` | Modified — RabbitMQ + rabbit-rs env vars |
| `config/queue.php` | Modified — add rabbit-rs connection |
| `config/rabbit-rs.php` | Created — full multi-broker config (published + customized) |
| `app/Jobs/ProcessDefaultJob.php` | Created |
| `app/Jobs/ProcessHighPriorityJob.php` | Created |
| `app/Jobs/ProcessOrderCreated.php` | Created |
| `app/Jobs/ProcessOrderPaid.php` | Created |
| `app/Jobs/ProcessOrderShipped.php` | Created |
| `app/Jobs/SendEmailNotification.php` | Created |
| `app/Jobs/SendSmsNotification.php` | Created |
| `app/Jobs/SendPushNotification.php` | Created |
| `app/Console/Commands/RabbitRsDemoCommand.php` | Created |
| `app/Console/Commands/RabbitRsSetupVhostsCommand.php` | Created |
| `Makefile` | Created |
| `README.md` | Created/Updated |

## Potential Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| PIE can't find matching binary for PHP 8.5 on Ubuntu 24.04 | The extension docs confirm PHP 8.5 support; PIE selects based on version/arch/libc/thread-safety |
| RabbitMQ 4.3-management image tag may not exist yet | Use `rabbitmq:4.3-management` or fall back to `rabbitmq:4-management` if 4.3 tag not available |
| Cluster formation fails if nodes start too fast | `depends_on: condition: service_healthy` on nodes 2 and 3 ensures ordered startup |
| Composer rejects rabbit-rs-laravel if ext not loaded | Extension is baked into the Docker image before Composer runs — `sail composer require` runs inside the container |
| Vhost creation fails if broker not ready | `rabbit-rs:setup-vhosts` command uses HTTP API with retry logic; Makefile runs it after `sail up` |
