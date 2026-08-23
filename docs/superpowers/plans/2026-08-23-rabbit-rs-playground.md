# Rabbit RS Playground Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Set up a Laravel Sail playground with the php-rabbit-rs native extension and Laravel bridge, two simultaneous RabbitMQ setups (simple + 3-node cluster), 3 vhosts each, demo jobs, and verification tooling.

**Architecture:** Laravel app installed in the current directory via `laravel new .`. Sail provides the Docker environment. The native extension is baked into the Sail Docker image via PIE at build time. Two RabbitMQ setups (single-node + 3-node cluster) run side-by-side in docker-compose.yml. The rabbit-rs Laravel bridge configures 6 brokers (2 setups × 3 vhosts), 16 routes, 6 worker profiles, and 8 demo jobs.

**Tech Stack:** Laravel 13 (PHP 8.5), Laravel Sail (Docker), RabbitMQ 4.3-management, goopil/rabbit-rs-native (PIE), goopil/rabbit-rs-laravel (Composer), MySQL 8, React starter kit, npm.

## Global Constraints

- PHP 8.5 in the Sail container (Ubuntu 24.04, Sury PPA, glibc)
- The extension `ext-rabbit_rs` must be installed via PIE before `composer require goopil/rabbit-rs-laravel` — the Composer package requires `ext-rabbit_rs:^1.0`
- RabbitMQ 4.3.x (`rabbitmq:4.3-management` image, fall back to `rabbitmq:4-management` if tag unavailable)
- All queue names use `<setup>.<vhost>.<queue>` convention (e.g. `simple.default.default`)
- macOS host — all PHP/extension operations happen inside Docker containers via Sail
- App installed in current directory: `laravel new . --database=mysql --react --npm --boost --no-interaction`

---

### Task 1: Create the Laravel Application

**Files:**
- Create: all Laravel scaffold files in current directory

**Interfaces:**
- Produces: a working Laravel app at the project root with `composer.json`, `artisan`, `package.json`, `.env`, `config/queue.php`, etc.

- [ ] **Step 1: Create the Laravel app**

Run:
```bash
laravel new . --database=mysql --react --npm --boost --no-interaction
```

Expected: Laravel installer creates the app in the current directory. If it asks about overwriting (directory not empty), answer yes — the directory only contains `docs/`.

- [ ] **Step 2: Verify the app was created**

Run:
```bash
php artisan --version
```

Expected: `Laravel Framework 13.x.x` (or 12.x)

- [ ] **Step 3: Check for Boost guidelines**

Run:
```bash
ls AGENTS.md CLAUDE.md 2>/dev/null
```

If either file exists, read it for Boost-specific AI development guidelines.

- [ ] **Step 4: Install npm dependencies**

Run:
```bash
npm install
```

Expected: `node_modules/` created, no errors.

- [ ] **Step 5: Commit**

```bash
git init
git add -A
git commit -m "feat: scaffold Laravel application with React starter kit and Boost"
```

---

### Task 2: Install Laravel Sail

**Files:**
- Modify: `composer.json` (adds `laravel/sail` dev dependency)
- Create: `docker/8.5/Dockerfile`
- Create: `docker/8.5/php.ini`
- Create: `docker/8.5/start-container`
- Create: `docker/8.5/supervisord.conf`
- Create: `docker-compose.yml`
- Create: `sail` (composer script alias)

**Interfaces:**
- Produces: `docker-compose.yml` with `laravel.test` and `mysql` services, `docker/8.5/Dockerfile` for the PHP image, and `sail` composer script.

- [ ] **Step 1: Install Sail via Composer**

Run:
```bash
composer require laravel/sail --dev
```

Expected: `laravel/sail` added to `composer.json` dev requirements.

- [ ] **Step 2: Publish Sail's Docker files**

Run:
```bash
php artisan sail:install --with=mysql
```

Expected: Creates `docker/8.5/Dockerfile`, `docker-compose.yml`, and related files. Selects MySQL as the database service.

- [ ] **Step 3: Verify Docker files were created**

Run:
```bash
ls docker/8.5/Dockerfile docker-compose.yml
```

Expected: Both files exist.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: install Laravel Sail with MySQL service"
```

---

### Task 3: Customize the Dockerfile for the Rabbit RS Extension

**Files:**
- Modify: `docker/8.5/Dockerfile` (add PIE + extension install after PHP packages, before `setcap`)

**Interfaces:**
- Produces: a Docker image that has `ext-rabbit_rs` loaded, so that `composer require goopil/rabbit-rs-laravel` succeeds inside the container.

- [ ] **Step 1: Read the current Dockerfile**

Run:
```bash
cat docker/8.5/Dockerfile
```

Identify the line containing `RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.5`. The PIE install block must be inserted **before** this line and **after** the `PHP_EXTENSIONS` conditional block (the `RUN if [ -n "$PHP_EXTENSIONS" ]` block).

- [ ] **Step 2: Add PIE and extension installation to the Dockerfile**

Insert the following block after the `PHP_EXTENSIONS` conditional `RUN` block and before the `setcap` line:

```dockerfile

# Install PIE (PHP Installer for Extensions)
RUN curl -L https://github.com/php/pie/releases/latest/download/pie.phar -o /usr/local/bin/pie \
    && chmod +x /usr/local/bin/pie

# Install the Rabbit RS native extension
RUN pie install goopil/rabbit-rs-native

# Verify the extension is loaded
RUN php --ri rabbit_rs
```

- [ ] **Step 3: Verify the modification**

Run:
```bash
grep -n "pie" docker/8.5/Dockerfile
```

Expected: Three lines matching — `pie.phar` download, `pie install`, and the verification step.

- [ ] **Step 4: Commit**

```bash
git add docker/8.5/Dockerfile
git commit -m "feat: add PIE and rabbit-rs-native extension to Sail Dockerfile"
```

---

### Task 4: Add RabbitMQ Services to docker-compose.yml

**Files:**
- Modify: `docker-compose.yml` (add 4 RabbitMQ services + 4 volumes)

**Interfaces:**
- Produces: `rabbitmq-simple`, `rabbitmq-1`, `rabbitmq-2`, `rabbitmq-3` services in docker-compose.yml, all on the `sail` network, with healthchecks.

- [ ] **Step 1: Read the current docker-compose.yml**

Run:
```bash
cat docker-compose.yml
```

Note the structure: `services:` block with `laravel.test` and `mysql`, `networks:` with `sail`, and `volumes:` with existing volumes.

- [ ] **Step 2: Add the simple RabbitMQ service**

Add this service to the `services:` block in `docker-compose.yml`:

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

- [ ] **Step 3: Add the cluster RabbitMQ services**

Add these three services to the `services:` block:

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

- [ ] **Step 4: Add volumes**

Add these to the `volumes:` top-level key in `docker-compose.yml`:

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

- [ ] **Step 5: Verify the compose file is valid**

Run:
```bash
docker compose config --quiet
```

Expected: No errors. (This may require `.env` to exist — it should since Laravel created it.)

- [ ] **Step 6: Commit**

```bash
git add docker-compose.yml
git commit -m "feat: add RabbitMQ simple and cluster services to docker-compose"
```

---

### Task 5: Create RabbitMQ Cluster Config Files

**Files:**
- Create: `docker/rabbitmq/cluster/rabbitmq.conf`
- Create: `docker/rabbitmq/cluster/enabled_plugins`

**Interfaces:**
- Produces: config files mounted into cluster containers for peer discovery and plugin enabling.

- [ ] **Step 1: Create the cluster config directory**

Run:
```bash
mkdir -p docker/rabbitmq/cluster
```

- [ ] **Step 2: Create rabbitmq.conf**

Create `docker/rabbitmq/cluster/rabbitmq.conf` with this content:

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

# Default vhost (additional vhosts created via init command)
default_v_host = /
```

- [ ] **Step 3: Create enabled_plugins**

Create `docker/rabbitmq/cluster/enabled_plugins` with this content:

```erlang
[rabbitmq_management,rabbitmq_peer_discovery_classic_config].
```

- [ ] **Step 4: Commit**

```bash
git add docker/rabbitmq/
git commit -m "feat: add RabbitMQ cluster config files"
```

---

### Task 6: Build the Sail Image and Start Containers

**Files:**
- No file changes — this is a build/verify step.

**Interfaces:**
- Produces: running Sail + RabbitMQ containers with `ext-rabbit_rs` loaded.

- [ ] **Step 1: Build the Sail image (this installs the extension)**

Run:
```bash
./vendor/bin/sail build --no-cache
```

Expected: Build completes. The PIE download + extension install step should succeed. Watch for `php --ri rabbit_rs` output confirming the extension is loaded.

If the build fails at the PIE step:
- Check that the PIE phar URL is correct: `https://github.com/php/pie/releases/latest/download/pie.phar`
- Check that `php8.5-dev` is installed (it should be from the Sail Dockerfile)
- Try `rabbitmq:4-management` instead of `rabbitmq:4.3-management` if RabbitMQ image pull fails

- [ ] **Step 2: Start all containers**

Run:
```bash
./vendor/bin/sail up -d
```

Expected: All services start — `laravel.test`, `mysql`, `rabbitmq-simple`, `rabbitmq-1`, `rabbitmq-2`, `rabbitmq-3`.

- [ ] **Step 3: Verify the extension is loaded in the container**

Run:
```bash
./vendor/bin/sail php -m | grep rabbit_rs
```

Expected: `rabbit_rs` in the output.

- [ ] **Step 4: Verify RabbitMQ containers are healthy**

Run:
```bash
docker compose ps
```

Expected: All RabbitMQ services show `(healthy)` status. This may take 30-60 seconds for the cluster nodes to form.

---

### Task 7: Install the Rabbit RS Laravel Bridge

**Files:**
- Modify: `composer.json` (adds `goopil/rabbit-rs-laravel`)
- Create: `config/rabbit-rs.php` (published config)

**Interfaces:**
- Produces: `goopil/rabbit-rs-laravel` installed, service provider auto-discovered, `config/rabbit-rs.php` published with defaults.

- [ ] **Step 1: Install the Composer package inside the Sail container**

Run:
```bash
./vendor/bin/sail composer require goopil/rabbit-rs-laravel
```

Expected: Package installed. The `ext-rabbit_rs` check passes because the extension is loaded in the container. Service provider is auto-discovered.

If this fails with "ext-rabbit_rs missing", verify Step 3 of Task 6 — the extension must be loaded in the container's PHP.

- [ ] **Step 2: Publish the config**

Run:
```bash
./vendor/bin/sail artisan vendor:publish --tag="rabbit-rs-config"
```

Expected: Creates `config/rabbit-rs.php`.

- [ ] **Step 3: Verify the config file exists**

Run:
```bash
ls -la config/rabbit-rs.php
```

Expected: File exists.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/rabbit-rs.php
git commit -m "feat: install goopil/rabbit-rs-laravel bridge and publish config"
```

---

### Task 8: Configure Environment Variables

**Files:**
- Modify: `.env`

**Interfaces:**
- Produces: `.env` with all RabbitMQ + rabbit-rs environment variables set.

- [ ] **Step 1: Read the current .env**

Run:
```bash
cat .env
```

Note the existing `QUEUE_CONNECTION` value — it may be `sync` or `database`.

- [ ] **Step 2: Add environment variables**

Add the following to the end of `.env`:

```env

# === Rabbit RS Playground ===
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

- [ ] **Step 3: Commit**

```bash
git add .env
git commit -m "feat: add RabbitMQ and rabbit-rs environment variables"
```

---

### Task 9: Add Queue Connection to config/queue.php

**Files:**
- Modify: `config/queue.php`

**Interfaces:**
- Produces: a `rabbit-rs` connection entry in `config/queue.php`.

- [ ] **Step 1: Read the current queue config**

Run:
```bash
cat config/queue.php
```

Find the `'connections'` array.

- [ ] **Step 2: Add the rabbit-rs connection**

Add this entry inside the `'connections'` array in `config/queue.php`:

```php
        'rabbit-rs' => [
            'driver' => 'rabbit-rs',
            'queue' => env('RABBIT_RS_QUEUE', 'simple.default.default'),
        ],
```

- [ ] **Step 3: Commit**

```bash
git add config/queue.php
git commit -m "feat: add rabbit-rs queue connection"
```

---

### Task 10: Configure config/rabbit-rs.php with Full Multi-Broker Setup

**Files:**
- Modify: `config/rabbit-rs.php` (replace published defaults with full config)

**Interfaces:**
- Produces: 6 brokers (simple.default, simple.orders, simple.notifications, cluster.default, cluster.orders, cluster.notifications), 16 routes, 6 worker profiles, quorum topology with dead-letter, publisher confirms, delay config.

- [ ] **Step 1: Replace config/rabbit-rs.php with the full configuration**

Replace the entire contents of `config/rabbit-rs.php` with:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brokers
    |--------------------------------------------------------------------------
    | Each broker is a named connection pool. A vhost owns a distinct AMQP
    | connection. 6 brokers: 2 setups × 3 vhosts.
    */
    'brokers' => [
        // === Simple Setup ===
        'simple.default' => [
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/default',
            'credentials' => [
                'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
                'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        'simple.orders' => [
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/orders',
            'credentials' => [
                'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
                'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        'simple.notifications' => [
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/notifications',
            'credentials' => [
                'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
                'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        // === Cluster Setup ===
        'cluster.default' => [
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/default',
            'credentials' => [
                'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
                'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        'cluster.orders' => [
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/orders',
            'credentials' => [
                'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
                'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        'cluster.notifications' => [
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/notifications',
            'credentials' => [
                'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
                'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes — map Laravel queue names to broker + exchange + routing key.
    | Route name = Laravel queue name = AMQP queue name (via {queue} placeholder).
    */
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

    /*
    |--------------------------------------------------------------------------
    | Workers — 6 profiles, one per vhost per setup.
    | Subscription queue values match route names exactly.
    */
    'workers' => [
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

    /*
    |--------------------------------------------------------------------------
    | Topology — quorum queues, durable, with dead-letter exchange.
    */
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

    /*
    |--------------------------------------------------------------------------
    | Topology Mode
    */
    'topology_mode' => env('RABBIT_RS_TOPOLOGY_MODE', 'declare'),

    /*
    |--------------------------------------------------------------------------
    | Publisher — confirms + mandatory routing.
    */
    'publisher' => [
        'confirms' => true,
        'mandatory' => true,
        'confirm_timeout' => (int) env('RABBIT_RS_CONFIRM_TIMEOUT', 30000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delay — auto-detect plugin, fall back to TTL buckets.
    */
    'delay' => [
        'mode' => env('RABBIT_RS_DELAY_MODE', 'auto'),
        'buckets' => array_map('intval', array_filter(array_map('trim', explode(',', env('RABBIT_RS_DELAY_BUCKETS', '1,5,30,120'))))),
        'max_buckets' => (int) env('RABBIT_RS_DELAY_MAX_BUCKETS', 8),
        'queue_expiry_margin' => (int) env('RABBIT_RS_DELAY_QUEUE_EXPIRY_MARGIN', 60),
        'detection_timeout' => (int) env('RABBIT_RS_DELAY_DETECTION_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | TLS
    */
    'tls' => [
        'enabled' => (bool) env('RABBIT_RS_TLS', false),
        'server_name' => env('RABBIT_RS_TLS_SERVER_NAME'),
        'ca_cert' => env('RABBIT_RS_TLS_CA_CERT'),
        'client_cert' => env('RABBIT_RS_TLS_CLIENT_CERT'),
        'client_key' => env('RABBIT_RS_TLS_CLIENT_KEY'),
        'verify' => env('RABBIT_RS_TLS_VERIFY', 'peer'),
    ],
];
```

- [ ] **Step 2: Verify the config is valid PHP**

Run:
```bash
./vendor/bin/sail php -l config/rabbit-rs.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add config/rabbit-rs.php
git commit -m "feat: configure multi-broker, multi-vhost rabbit-rs with 6 brokers, 16 routes, 6 workers"
```

---

### Task 11: Create the Vhost Setup Command

**Files:**
- Create: `app/Console/Commands/RabbitRsSetupVhostsCommand.php`

**Interfaces:**
- Produces: `php artisan rabbit-rs:setup-vhosts` — creates 3 vhosts on both RabbitMQ setups via the Management HTTP API.
- Consumes: RabbitMQ Management API at `http://rabbitmq-simple:15672` and `http://rabbitmq-1:15672` (inside the Docker network).

- [ ] **Step 1: Create the command file**

Create `app/Console/Commands/RabbitRsSetupVhostsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RabbitRsSetupVhostsCommand extends Command
{
    protected $signature = 'rabbit-rs:setup-vhosts';
    protected $description = 'Create RabbitMQ vhosts on both simple and cluster setups';

    private const VHOSTS = ['/default', '/orders', '/notifications'];

    private const BROKERS = [
        'simple' => [
            'host' => 'rabbitmq-simple',
            'port' => 15672,
            'user' => 'guest',
            'pass' => 'guest',
        ],
        'cluster' => [
            'host' => 'rabbitmq-1',
            'port' => 15672,
            'user' => 'guest',
            'pass' => 'guest',
        ],
    ];

    public function handle(): int
    {
        foreach (self::BROKERS as $name => $config) {
            $this->info("Setting up vhosts on {$name} broker ({$config['host']})...");

            $base = "http://{$config['host']}:{$config['port']}/api";
            $auth = [$config['user'], $config['pass']];

            foreach (self::VHOSTS as $vhost) {
                $encoded = urlencode($vhost);

                $response = Http::withBasicAuth(...$auth)
                    ->put("{$base}/vhosts/{$encoded}");

                if ($response->status() === 201 || $response->status() === 204) {
                    $this->line("  ✓ vhost {$vhost} created or already exists");
                } else {
                    $this->error("  ✗ Failed to create vhost {$vhost}: {$response->status()} {$response->body()}");
                    return 1;
                }

                $permissions = [
                    'configure' => '.*',
                    'write' => '.*',
                    'read' => '.*',
                ];

                $permResponse = Http::withBasicAuth(...$auth)
                    ->put("{$base}/permissions/{$encoded}/{$config['user']}", $permissions);

                if ($permResponse->status() === 201 || $permResponse->status() === 204) {
                    $this->line("  ✓ permissions set for {$config['user']} on {$vhost}");
                } else {
                    $this->warn("  ! Could not set permissions on {$vhost}: {$permResponse->status()}");
                }
            }
        }

        $this->info('All vhosts created successfully.');
        return 0;
    }
}
```

- [ ] **Step 2: Verify the command is registered**

Run:
```bash
./vendor/bin/sail artisan list rabbit-rs
```

Expected: `rabbit-rs:setup-vhosts` appears in the command list.

- [ ] **Step 3: Run the command to create vhosts**

Run:
```bash
./vendor/bin/sail artisan rabbit-rs:setup-vhosts
```

Expected: Output showing vhost creation on both brokers. Exit code 0.

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/RabbitRsSetupVhostsCommand.php
git commit -m "feat: add rabbit-rs:setup-vhosts command for vhost provisioning"
```

---

### Task 12: Create Demo Job Classes

**Files:**
- Create: `app/Jobs/ProcessDefaultJob.php`
- Create: `app/Jobs/ProcessHighPriorityJob.php`
- Create: `app/Jobs/ProcessOrderCreated.php`
- Create: `app/Jobs/ProcessOrderPaid.php`
- Create: `app/Jobs/ProcessOrderShipped.php`
- Create: `app/Jobs/SendEmailNotification.php`
- Create: `app/Jobs/SendSmsNotification.php`
- Create: `app/Jobs/SendPushNotification.php`

**Interfaces:**
- Produces: 8 job classes that implement `ShouldQueue`, accept a payload, and log execution.
- Consumes: Laravel's `Queueable`, `Dispatchable` traits, `Log` facade.

- [ ] **Step 1: Create ProcessDefaultJob**

Create `app/Jobs/ProcessDefaultJob.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDefaultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('ProcessDefaultJob', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'payload' => $this->payload,
        ]);

        sleep(2);

        Log::info('ProcessDefaultJob completed', [
            'id' => $this->payload['id'] ?? null,
        ]);
    }
}
```

- [ ] **Step 2: Create ProcessHighPriorityJob**

Create `app/Jobs/ProcessHighPriorityJob.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessHighPriorityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('ProcessHighPriorityJob', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'payload' => $this->payload,
        ]);

        sleep(1);

        Log::info('ProcessHighPriorityJob completed', [
            'id' => $this->payload['id'] ?? null,
        ]);
    }
}
```

- [ ] **Step 3: Create ProcessOrderCreated**

Create `app/Jobs/ProcessOrderCreated.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('ProcessOrderCreated', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'order_id' => $this->payload['order_id'] ?? null,
            'customer' => $this->payload['customer'] ?? null,
            'total' => $this->payload['total'] ?? null,
        ]);

        sleep(1);

        Log::info('Order created processed', [
            'order_id' => $this->payload['order_id'] ?? null,
        ]);
    }
}
```

- [ ] **Step 4: Create ProcessOrderPaid**

Create `app/Jobs/ProcessOrderPaid.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderPaid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('ProcessOrderPaid', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'order_id' => $this->payload['order_id'] ?? null,
            'payment_method' => $this->payload['payment_method'] ?? null,
            'amount' => $this->payload['amount'] ?? null,
        ]);

        sleep(1);

        Log::info('Order paid processed', [
            'order_id' => $this->payload['order_id'] ?? null,
        ]);
    }
}
```

- [ ] **Step 5: Create ProcessOrderShipped**

Create `app/Jobs/ProcessOrderShipped.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderShipped implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('ProcessOrderShipped', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'order_id' => $this->payload['order_id'] ?? null,
            'tracking_number' => $this->payload['tracking_number'] ?? null,
            'carrier' => $this->payload['carrier'] ?? null,
        ]);

        sleep(1);

        Log::info('Order shipped processed', [
            'order_id' => $this->payload['order_id'] ?? null,
        ]);
    }
}
```

- [ ] **Step 6: Create SendEmailNotification**

Create `app/Jobs/SendEmailNotification.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('SendEmailNotification', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'recipient' => $this->payload['recipient'] ?? null,
            'subject' => $this->payload['subject'] ?? null,
        ]);

        sleep(1);

        Log::info('Email sent', [
            'recipient' => $this->payload['recipient'] ?? null,
        ]);
    }
}
```

- [ ] **Step 7: Create SendSmsNotification**

Create `app/Jobs/SendSmsNotification.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('SendSmsNotification', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'phone' => $this->payload['phone'] ?? null,
            'message' => $this->payload['message'] ?? null,
        ]);

        sleep(1);

        Log::info('SMS sent', [
            'phone' => $this->payload['phone'] ?? null,
        ]);
    }
}
```

- [ ] **Step 8: Create SendPushNotification**

Create `app/Jobs/SendPushNotification.php`:

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        Log::info('SendPushNotification', [
            'queue' => $this->job->getQueue() ?? 'unknown',
            'device_token' => $this->payload['device_token'] ?? null,
            'title' => $this->payload['title'] ?? null,
            'body' => $this->payload['body'] ?? null,
        ]);

        sleep(1);

        Log::info('Push sent', [
            'device_token' => $this->payload['device_token'] ?? null,
        ]);
    }
}
```

- [ ] **Step 9: Verify all job classes exist**

Run:
```bash
ls app/Jobs/Process*.php app/Jobs/Send*.php
```

Expected: 8 files listed.

- [ ] **Step 10: Commit**

```bash
git add app/Jobs/
git commit -m "feat: add 8 demo job classes for all vhosts and queues"
```

---

### Task 13: Create the Demo Dispatch Command

**Files:**
- Create: `app/Console/Commands/RabbitRsDemoCommand.php`

**Interfaces:**
- Produces: `php artisan rabbit-rs:demo [--setup=simple|cluster|both] [--delay]` — dispatches jobs across both setups and all vhosts/queues.
- Consumes: All 8 job classes from Task 12.

- [ ] **Step 1: Create the command file**

Create `app/Console/Commands/RabbitRsDemoCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDefaultJob;
use App\Jobs\ProcessHighPriorityJob;
use App\Jobs\ProcessOrderCreated;
use App\Jobs\ProcessOrderPaid;
use App\Jobs\ProcessOrderShipped;
use App\Jobs\SendEmailNotification;
use App\Jobs\SendPushNotification;
use App\Jobs\SendSmsNotification;
use Illuminate\Console\Command;

class RabbitRsDemoCommand extends Command
{
    protected $signature = 'rabbit-rs:demo
                            {--setup=both : Setup to use (simple, cluster, or both)}
                            {--delay : Dispatch some jobs with delays}';

    protected $description = 'Dispatch demo jobs to RabbitMQ across all vhosts and queues';

    public function handle(): int
    {
        $setup = $this->option('setup');
        $useDelay = $this->option('delay');

        if (!in_array($setup, ['simple', 'cluster', 'both'])) {
            $this->error("Invalid setup: {$setup}. Use: simple, cluster, or both");
            return 1;
        }

        $setups = $setup === 'both' ? ['simple', 'cluster'] : [$setup];
        $dispatched = 0;

        foreach ($setups as $s) {
            $this->info("Dispatching to {$s} setup...");

            // /default vhost
            ProcessDefaultJob::dispatch(['id' => $dispatched + 1, 'message' => "Default job on {$s}"])
                ->onQueue("{$s}.default.default");
            $dispatched++;
            $this->line("  ✓ ProcessDefaultJob → {$s}.default.default");

            ProcessHighPriorityJob::dispatch(['id' => $dispatched + 1, 'message' => "High priority on {$s}"])
                ->onQueue("{$s}.default.high-priority");
            $dispatched++;
            $this->line("  ✓ ProcessHighPriorityJob → {$s}.default.high-priority");

            // /orders vhost
            ProcessOrderCreated::dispatch(['order_id' => $dispatched + 1, 'customer' => 'John Doe', 'total' => 99.99])
                ->onQueue("{$s}.orders.created");
            $dispatched++;
            $this->line("  ✓ ProcessOrderCreated → {$s}.orders.created");

            if ($useDelay) {
                ProcessOrderPaid::dispatch(['order_id' => $dispatched + 1, 'payment_method' => 'credit_card', 'amount' => 99.99])
                    ->onQueue("{$s}.orders.paid")
                    ->delay(now()->addSeconds(5));
                $this->line("  ✓ ProcessOrderPaid → {$s}.orders.paid (delayed 5s)");
            } else {
                ProcessOrderPaid::dispatch(['order_id' => $dispatched + 1, 'payment_method' => 'credit_card', 'amount' => 99.99])
                    ->onQueue("{$s}.orders.paid");
                $this->line("  ✓ ProcessOrderPaid → {$s}.orders.paid");
            }
            $dispatched++;

            ProcessOrderShipped::dispatch(['order_id' => $dispatched + 1, 'tracking_number' => 'TRK' . rand(100000, 999999), 'carrier' => 'UPS'])
                ->onQueue("{$s}.orders.shipped");
            $dispatched++;
            $this->line("  ✓ ProcessOrderShipped → {$s}.orders.shipped");

            // /notifications vhost
            SendEmailNotification::dispatch(['recipient' => 'user@example.com', 'subject' => "Welcome from {$s}"])
                ->onQueue("{$s}.notifications.email");
            $dispatched++;
            $this->line("  ✓ SendEmailNotification → {$s}.notifications.email");

            if ($useDelay) {
                SendSmsNotification::dispatch(['phone' => '+1234567890', 'message' => "Your code: " . rand(1000, 9999)])
                    ->onQueue("{$s}.notifications.sms")
                    ->delay(now()->addSeconds(10));
                $this->line("  ✓ SendSmsNotification → {$s}.notifications.sms (delayed 10s)");
            } else {
                SendSmsNotification::dispatch(['phone' => '+1234567890', 'message' => "Your code: " . rand(1000, 9999)])
                    ->onQueue("{$s}.notifications.sms");
                $this->line("  ✓ SendSmsNotification → {$s}.notifications.sms");
            }
            $dispatched++;

            SendPushNotification::dispatch(['device_token' => 'token_' . bin2hex(random_bytes(8)), 'title' => "Push from {$s}", 'body' => 'Hello!'])
                ->onQueue("{$s}.notifications.push");
            $dispatched++;
            $this->line("  ✓ SendPushNotification → {$s}.notifications.push");
        }

        $this->newLine();
        $this->info("Dispatched {$dispatched} jobs to " . implode(', ', $setups) . " setup(s).");
        $this->info('Run "sail artisan rabbit-rs:work --queue=<worker-profile>" to consume.');
        $this->info('Worker profiles: simple.default, simple.orders, simple.notifications, cluster.default, cluster.orders, cluster.notifications');

        return 0;
    }
}
```

- [ ] **Step 2: Verify the command is registered**

Run:
```bash
./vendor/bin/sail artisan list rabbit-rs
```

Expected: Both `rabbit-rs:demo` and `rabbit-rs:setup-vhosts` appear.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/RabbitRsDemoCommand.php
git commit -m "feat: add rabbit-rs:demo command to dispatch jobs across all vhosts"
```

---

### Task 14: Create the Makefile

**Files:**
- Create: `Makefile`

**Interfaces:**
- Produces: Make targets for build, up, down, setup-vhosts, demo, status, and workers.

- [ ] **Step 1: Create the Makefile**

Create `Makefile` at the project root:

```makefile
.PHONY: up down build demo setup-vhosts status workers-simple workers-cluster workers

build:
	./vendor/bin/sail build --no-cache

up:
	./vendor/bin/sail up -d

down:
	./vendor/bin/sail down

setup-vhosts:
	./vendor/bin/sail artisan rabbit-rs:setup-vhosts

demo:
	./vendor/bin/sail artisan rabbit-rs:demo

demo-delay:
	./vendor/bin/sail artisan rabbit-rs:demo --delay

demo-simple:
	./vendor/bin/sail artisan rabbit-rs:demo --setup=simple

demo-cluster:
	./vendor/bin/sail artisan rabbit-rs:demo --setup=cluster

status:
	./vendor/bin/sail artisan rabbit-rs:status

workers-simple:
	@echo "Starting simple workers..."
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.default &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.orders &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=simple.notifications &
	@echo "Simple workers started in background"

workers-cluster:
	@echo "Starting cluster workers..."
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.default &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.orders &
	@./vendor/bin/sail artisan rabbit-rs:work --queue=cluster.notifications &
	@echo "Cluster workers started in background"

workers: workers-simple workers-cluster
```

- [ ] **Step 2: Verify Makefile works**

Run:
```bash
make -n up
```

Expected: Prints `./vendor/bin/sail up -d` without executing.

- [ ] **Step 3: Commit**

```bash
git add Makefile
git commit -m "feat: add Makefile with Sail and rabbit-rs helper targets"
```

---

### Task 15: Create README

**Files:**
- Create: `README.md` (or modify if Laravel created one)

**Interfaces:**
- Produces: documentation for setup, usage, and troubleshooting.

- [ ] **Step 1: Create or replace README.md**

Replace the contents of `README.md` with:

```markdown
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
# 1. Build the Sail image (installs the rabbit_rs extension via PIE)
make build

# 2. Start all containers (Laravel + MySQL + 2 RabbitMQ setups)
make up

# 3. Create RabbitMQ vhosts
make setup-vhosts

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
- `docker/8.5/Dockerfile` — PIE + rabbit-rs-native extension
- `docker-compose.yml` — 4 RabbitMQ services (1 simple + 3 cluster nodes)
- `docker/rabbitmq/cluster/` — cluster peer discovery config

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Extension not loaded | Rebuild: `make build` then `make up` |
| Cluster not forming | Check `docker logs rabbitmq-1`; ensure all 3 nodes share the Erlang cookie |
| Composer rejects rabbit-rs-laravel | Must run inside Sail: `sail composer require ...` (extension is in the container, not on macOS) |
| Vhost creation fails | Ensure RabbitMQ is healthy: `docker compose ps`, then re-run `make setup-vhosts` |
| `rabbitmq:4.3-management` not found | Use `rabbitmq:4-management` in docker-compose.yml as fallback |
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add README with setup, usage, and troubleshooting guide"
```

---

### Task 16: End-to-End Verification

**Files:**
- No file changes — verification only.

**Interfaces:**
- Produces: confirmed working playground.

- [ ] **Step 1: Rebuild and restart (in case config changed)**

Run:
```bash
./vendor/bin/sail down
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```

Expected: All containers start successfully.

- [ ] **Step 2: Wait for RabbitMQ to be healthy**

Run:
```bash
docker compose ps
```

Expected: All RabbitMQ services show `(healthy)`.

- [ ] **Step 3: Verify extension loaded**

Run:
```bash
./vendor/bin/sail php -m | grep rabbit_rs
```

Expected: `rabbit_rs` in the output.

- [ ] **Step 4: Create vhosts**

Run:
```bash
./vendor/bin/sail artisan rabbit-rs:setup-vhosts
```

Expected: All 6 vhosts created (3 on simple, 3 on cluster), exit code 0.

- [ ] **Step 5: Check rabbit-rs status**

Run:
```bash
./vendor/bin/sail artisan rabbit-rs:status
```

Expected: Shows connection state for both brokers.

- [ ] **Step 6: Dispatch demo jobs**

Run:
```bash
./vendor/bin/sail artisan rabbit-rs:demo
```

Expected: 16 jobs dispatched (8 per setup), summary table printed.

- [ ] **Step 7: Start a worker and verify consumption**

Open a separate terminal and run:
```bash
./vendor/bin/sail artisan rabbit-rs:work --queue=simple.default
```

Expected: Worker starts, connects to the simple broker, consumes jobs from the `simple.default.default` and `simple.default.high-priority` queues.

- [ ] **Step 8: Check the management UI**

Open http://localhost:15672 in a browser. Log in with `guest` / `guest`. Verify:
- 3 vhosts exist: `/default`, `/orders`, `/notifications`
- Queues were created in each vhost
- Messages are visible in the queues (if no worker is running)

- [ ] **Step 9: Final commit (if any verification artifacts need saving)**

```bash
git status
```

If clean, no commit needed. If there are changes, commit them.
