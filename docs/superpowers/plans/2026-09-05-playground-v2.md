# Playground v2 — Octane + Horizon/Sentinel + SSR ClusterKit + Modules — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the rabbit-rs-playground into a full-stack playground: Octane/Swoole HTTP, Horizon on Valkey Sentinel (read/write splitting), Inertia SSR served by a ClusterKit-orchestrated Node server, code organized in nwidart modules, everything updated.

**Architecture:** All services run in the single `laravel.test` Sail container under supervisord (php=octane, horizon, ssr). Compose adds a Valkey HA set (1 master + 2 replicas + 3 sentinels). RabbitMQ infra/topology stays untouched; its `queue:work` supervisor programs are removed (return later via `rabbit-rs:work`).

**Tech Stack:** Laravel 13, PHP 8.5, Node 26, laravel/octane (swoole), laravel/horizon, goopil/laravel-redis-sentinel, nwidart/laravel-modules v13, @goopil/clusterkit, Inertia React 2 + Ziggy, Sail.

**Spec:** `docs/superpowers/specs/2026-09-05-playground-v2-horizon-octane-ssr-design.md`

## Global Constraints

- Composer install/require/update MUST run inside Sail (`sail composer ...`) — `goopil/rabbit-rs-laravel` requires `ext-rabbit_rs` which only exists in the container.
- Node version in Docker image: `NODE_VERSION=26`. PHP: 8.5 (existing).
- `QUEUE_CONNECTION=redis-sentinel` becomes the default; `rabbit-rs` connection and full rabbit config/topology stay intact.
- RabbitMQ compose services, MySQL, and `config/rabbit-rs.php` are NOT modified.
- Docs/comments/README in English. Commit messages: conventional commits (feat/fix/chore).
- Every task ends with `sail composer test` green (where tests exist) and a commit.
- `phpunit.xml` hermetic overrides (SESSION_DRIVER=array, CACHE_STORE=array, QUEUE_CONNECTION=sync, DB sqlite/mysql) must remain untouched.

---

### Task 1: Runtime updates (Node 26, composer update, npm update)

**Files:**
- Modify: `docker/8.5/Dockerfile` (line 6: `ARG NODE_VERSION=24` → `26`)
- Modify: `package.json` (indirectly via npm update)

**Interfaces:**
- Produces: up-to-date container base (Node 26, latest composer deps) that all later tasks build on.

- [ ] **Step 1: Bump Node in Dockerfile**

In `docker/8.5/Dockerfile`, change `ARG NODE_VERSION=24` to `ARG NODE_VERSION=26`.

- [ ] **Step 2: Rebuild and start**

```bash
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```
Wait for healthchecks (RabbitMQ cluster needs ~30s).

- [ ] **Step 3: Update composer + npm dependencies**

```bash
./vendor/bin/sail composer update
./vendor/bin/sail npm update
```
If `npm update` produces peer-dep conflicts, resolve by removing the offending constraint, not with `--legacy-peer-deps`.

- [ ] **Step 4: Verify versions**

```bash
./vendor/bin/sail node --version   # expect v26.x
./vendor/bin/sail php --version    # 8.5.x
./vendor/bin/sail composer test    # existing suite green
```

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "chore: update deps, Node 26 base image"
```

---

### Task 2: Valkey HA (1 master + 2 replicas + 3 sentinels)

**Files:**
- Create: `docker/valkey/master.conf`
- Create: `docker/valkey/replica.conf`
- Create: `docker/valkey/sentinel.conf`
- Modify: `compose.yaml` (append 6 services + 3 volumes)

**Interfaces:**
- Produces: reachable sentinel set at `sentinel-1/2/3:26379` monitoring master `mymaster` (hostname `valkey-master:6379`), used by Task 3 config.

- [ ] **Step 1: Write config files**

`docker/valkey/master.conf`:
```conf
appendonly yes
save ""
```

`docker/valkey/replica.conf`:
```conf
replicaof valkey-master 6379
appendonly yes
save ""
```

`docker/valkey/sentinel.conf`:
```conf
port 26379
sentinel resolve-hostnames yes
sentinel announce-hostnames yes
sentinel monitor mymaster valkey-master 6379 2
sentinel down-after-milliseconds mymaster 5000
sentinel failover-timeout mymaster 20000
sentinel parallel-syncs mymaster 1
```

- [ ] **Step 2: Add compose services**

Append to `compose.yaml` services (before `networks:`):

```yaml
    valkey-master:
        image: 'valkey/valkey:8'
        command: ['valkey-server', '/etc/valkey/valkey.conf']
        volumes:
            - './docker/valkey/master.conf:/etc/valkey/valkey.conf:ro'
            - 'sail-valkey-master:/data'
        ports:
            - '${FORWARD_VALKEY_MASTER_PORT:-6380}:6379'
        networks:
            - sail
        healthcheck:
            test: ['CMD', 'valkey-cli', 'ping']
            interval: 5s
            timeout: 3s
            retries: 10
    valkey-1:
        image: 'valkey/valkey:8'
        command: ['valkey-server', '/etc/valkey/valkey.conf']
        volumes:
            - './docker/valkey/replica.conf:/etc/valkey/valkey.conf:ro'
            - 'sail-valkey-1:/data'
        ports:
            - '${FORWARD_VALKEY_1_PORT:-6381}:6379'
        networks:
            - sail
        depends_on:
            valkey-master:
                condition: service_healthy
        healthcheck:
            test: ['CMD', 'valkey-cli', 'ping']
            interval: 5s
            timeout: 3s
            retries: 10
    valkey-2:
        image: 'valkey/valkey:8'
        command: ['valkey-server', '/etc/valkey/valkey.conf']
        volumes:
            - './docker/valkey/replica.conf:/etc/valkey/valkey.conf:ro'
            - 'sail-valkey-2:/data'
        ports:
            - '${FORWARD_VALKEY_2_PORT:-6382}:6379'
        networks:
            - sail
        depends_on:
            valkey-master:
                condition: service_healthy
        healthcheck:
            test: ['CMD', 'valkey-cli', 'ping']
            interval: 5s
            timeout: 3s
            retries: 10
    sentinel-1:
        image: 'valkey/valkey:8'
        command: ['sh', '-c', 'cp /etc/valkey/sentinel-template.conf /data/sentinel.conf && exec valkey-sentinel /data/sentinel.conf']
        volumes:
            - './docker/valkey/sentinel.conf:/etc/valkey/sentinel-template.conf:ro'
            - 'sail-sentinel-1:/data'
        ports:
            - '${FORWARD_SENTINEL_1_PORT:-26379}:26379'
        networks:
            - sail
        depends_on:
            valkey-master:
                condition: service_healthy
    sentinel-2:
        image: 'valkey/valkey:8'
        command: ['sh', '-c', 'cp /etc/valkey/sentinel-template.conf /data/sentinel.conf && exec valkey-sentinel /data/sentinel.conf']
        volumes:
            - './docker/valkey/sentinel.conf:/etc/valkey/sentinel-template.conf:ro'
            - 'sail-sentinel-2:/data'
        ports:
            - '${FORWARD_SENTINEL_2_PORT:-26380}:26379'
        networks:
            - sail
        depends_on:
            valkey-master:
                condition: service_healthy
    sentinel-3:
        image: 'valkey/valkey:8'
        command: ['sh', '-c', 'cp /etc/valkey/sentinel-template.conf /data/sentinel.conf && exec valkey-sentinel /data/sentinel.conf']
        volumes:
            - './docker/valkey/sentinel.conf:/etc/valkey/sentinel-template.conf:ro'
            - 'sail-sentinel-3:/data'
        ports:
            - '${FORWARD_SENTINEL_3_PORT:-26381}:26379'
        networks:
            - sail
        depends_on:
            valkey-master:
                condition: service_healthy
```

Append to `volumes:`:

```yaml
    sail-valkey-master:
        driver: local
    sail-valkey-1:
        driver: local
    sail-valkey-2:
        driver: local
    sail-sentinel-1:
        driver: local
    sail-sentinel-2:
        driver: local
    sail-sentinel-3:
        driver: local
```

Note: sentinels copy their read-only template to a writable `/data/sentinel.conf` because Sentinel rewrites its config on failover.

- [ ] **Step 3: Start and verify topology**

```bash
./vendor/bin/sail up -d
./vendor/bin/sail exec sentinel-1 valkey-cli -p 26379 sentinel get-master-addr-by-name mymaster
# expect: valkey-master + 6379
./vendor/bin/sail exec sentinel-1 valkey-cli -p 26379 sentinel replicas mymaster
# expect 2 replicas
```

- [ ] **Step 4: Commit**

```bash
git add compose.yaml docker/valkey && git commit -m "feat: valkey HA set (master + 2 replicas + 3 sentinels)"
```

---

### Task 3: goopil/laravel-redis-sentinel integration

**Files:**
- Modify: `config/database.php` (redis block)
- Create: `config/phpredis-sentinel.php` (published)
- Modify: `.env` and `.env.example` (Redis vars, CACHE_STORE, SESSION_DRIVER)

**Interfaces:**
- Produces: redis connection `default` named in config, resolved through the sentinel-aware manager; global override enabled so cache/session/broadcast use Sentinel. Horizon (Task 4) and queue connection (Task 4) build on it.

- [ ] **Step 1: Install**

```bash
./vendor/bin/sail composer require goopil/laravel-redis-sentinel
./vendor/bin/sail artisan vendor:publish --provider="Goopil\\LaravelRedisSentinel\\RedisSentinelServiceProvider" --tag=config
```

- [ ] **Step 2: Rewrite the redis block in `config/database.php`**

Replace the existing `'redis' => [...]` array with:

```php
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis-sentinel'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', ''),
        ],

        'default' => [
            'sentinels' => [
                ['host' => env('REDIS_SENTINEL_HOST_1', 'sentinel-1'), 'port' => 26379],
                ['host' => env('REDIS_SENTINEL_HOST_2', 'sentinel-2'), 'port' => 26379],
                ['host' => env('REDIS_SENTINEL_HOST_3', 'sentinel-3'), 'port' => 26379],
            ],
            'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_DB', 0),
            'read_only_replicas' => env('REDIS_READ_REPLICAS', true),
            'options' => [
                'prefix' => env('REDIS_PREFIX', ''),
            ],
        ],

        'cache' => [
            'sentinels' => [
                ['host' => env('REDIS_SENTINEL_HOST_1', 'sentinel-1'), 'port' => 26379],
                ['host' => env('REDIS_SENTINEL_HOST_2', 'sentinel-2'), 'port' => 26379],
                ['host' => env('REDIS_SENTINEL_HOST_3', 'sentinel-3'), 'port' => 26379],
            ],
            'service' => env('REDIS_SENTINEL_SERVICE', 'mymaster'),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_CACHE_DB', 1),
            'read_only_replicas' => env('REDIS_READ_REPLICAS', true),
            'options' => [
                'prefix' => env('REDIS_PREFIX', ''),
            ],
        ],

    ],
```

Note: exact option keys accepted by the lib for sentinels/service/read_only_replicas are authoritative in the README + `config/phpredis-sentinel.php` + source of the installed version (`vendor/goopil/laravel-redis-sentinel`). Cross-check during implementation and adjust key names if the installed version differs (e.g. single `'sentinel' => [...]` form).

- [ ] **Step 3: Environment**

`.env` and `.env.example` — replace the current `REDIS_*` block and cache/session vars with:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis-sentinel
REDIS_SENTINEL_HOST_1=sentinel-1
REDIS_SENTINEL_HOST_2=sentinel-2
REDIS_SENTINEL_HOST_3=sentinel-3
REDIS_SENTINEL_SERVICE=mymaster
REDIS_PASSWORD=null
REDIS_READ_REPLICAS=true
REDIS_SENTINEL_OVERRIDE_LARAVEL_REDIS=true
```

- [ ] **Step 4: Verify through Sentinel**

```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail exec laravel.test php artisan tinker --execute="Cache::store('redis')->put('sentinel-check', 'ok', 60); echo Cache::store('redis')->get('sentinel-check');"
# expect: ok
./vendor/bin/sail exec sentinel-1 valkey-cli -p 26379 sentinel get-master-addr-by-name mymaster
# master still valkey-master:6379 (writes land on master)
./vendor/bin/sail exec valkey-master valkey-cli keys '*sentinel-check*'
```

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: redis sentinel via goopil/laravel-redis-sentinel (cache+session)"
```

---

### Task 4: Horizon + redis-sentinel queue connection + supervisord

**Files:**
- Modify: `config/horizon.php` (installed by horizon:install)
- Modify: `config/queue.php` (add connection)
- Modify: `docker/8.5/supervisord.conf` (remove 4 rabbit programs, add horizon)
- Modify: `.env` / `.env.example` (`QUEUE_CONNECTION`, `SUPERVISOR_HORIZON`, remove `SUPERVISOR_RABBIT_RS_WORKERS`)
- Test: `tests/Feature/QueueTopologyTest.php`

**Interfaces:**
- Consumes: redis connection `default` (Task 3).
- Produces: queue connection name `redis-sentinel` with queues `default`, `high-priority`, `bulk`; Horizon running under supervisord; later tasks dispatch with `->onConnection('redis-sentinel')`.

- [ ] **Step 1: Install Horizon**

```bash
./vendor/bin/sail composer require laravel/horizon
./vendor/bin/sail artisan horizon:install
```

- [ ] **Step 2: Configure horizon.php**

In `config/horizon.php`: set `'use' => env('HORIZON_REDIS_CONNECTION', 'default')`. Then replace the whole `environments` array with:

```php
    'environments' => [
        'production' => [
            'supervisor-horizon' => [
                'connection' => 'redis-sentinel',
                'queue' => ['default', 'high-priority'],
                'balance' => 'auto',
                'processes' => 2,
                'tries' => 3,
            ],
            'supervisor-bulk' => [
                'connection' => 'redis-sentinel',
                'queue' => ['bulk'],
                'balance' => 'simple',
                'processes' => 4,
                'tries' => 3,
            ],
        ],

        'local' => [
            'supervisor-horizon' => [
                'connection' => 'redis-sentinel',
                'queue' => ['default', 'high-priority'],
                'balance' => 'auto',
                'processes' => 2,
                'tries' => 3,
            ],
            'supervisor-bulk' => [
                'connection' => 'redis-sentinel',
                'queue' => ['bulk'],
                'balance' => 'simple',
                'processes' => 4,
                'tries' => 3,
            ],
        ],
    ],
```

If Horizon fails at boot to resolve the sentinel connection, read `vendor/goopil/laravel-redis-sentinel/README.md` "Horizon Integration" section and align (`'use'` value / queue driver naming) with the installed version's documented wiring.

- [ ] **Step 3: Add queue connection in `config/queue.php`**

Inside `connections`, keep the existing `'redis'` entry and add:

```php
        'redis-sentinel' => [
            'driver' => 'phpredis-sentinel',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],
```

(Check `vendor/goopil/laravel-redis-sentinel/README.md` "Queue" section — the lib registers the `phpredis-sentinel` queue driver; use exactly the documented driver name.)

- [ ] **Step 4: Default connection**

`.env` and `.env.example`: `QUEUE_CONNECTION=redis-sentinel` (replaces `rabbit-rs`).

- [ ] **Step 5: Write the config test**

`tests/Feature/QueueTopologyTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueueTopologyTest extends TestCase
{
    public function test_redis_sentinel_connection_is_configured(): void
    {
        $connection = config('queue.connections.redis-sentinel');

        $this->assertNotNull($connection);
        $this->assertSame('default', $connection['connection']);
        $this->assertSame(['default', 'high-priority'], config('horizon.environments.local.supervisor-horizon.queue'));
        $this->assertSame('redis-sentinel', config('horizon.environments.local.supervisor-horizon.connection'));
    }
}
```

- [ ] **Step 6: Run test**

Run: `./vendor/bin/sail composer test`
Expected: PASS (all suite green).

- [ ] **Step 7: Update supervisord**

NOTE (updated after the rabbit-rs 0.1.0 connection-first migration): `docker/8.5/supervisord.conf`
now contains 8 rabbit-rs worker programs (`queue:work <connection> --queue=…`, one per
connection×profile) plus fixed supervisorctl sections. Instead of deleting them:

1. Add `autostart=%(ENV_SUPERVISOR_RABBIT_RS_WORKERS)s` to each of the 8 rabbit worker programs
   (replacing any existing `autostart=true`).
2. Append the horizon program (keep the existing worker programs and supervisorctl sections):

```ini
[program:horizon]
command=/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan horizon
user=%(ENV_SUPERVISOR_PHP_USER)s
autostart=%(ENV_SUPERVISOR_HORIZON)s
autorestart=true
stopsignal=TERM
stopasgroup=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

- [ ] **Step 8: Env gates**

`.env` + `.env.example`: set `SUPERVISOR_RABBIT_RS_WORKERS=false` (rabbit workers defined but off — Horizon-only per user decision, re-enable later with rabbit-rs:work / queue:work), add `SUPERVISOR_HORIZON=true`, set `QUEUE_CONNECTION=redis-sentinel`.

- [ ] **Step 9: Rebuild, boot, verify end to end**

```bash
./vendor/bin/sail build --no-cache && ./vendor/bin/sail up -d
./vendor/bin/sail exec supervisorctl status        # php RUNNING + horizon RUNNING
./vendor/bin/sail artisan tinker --execute="dispatch((new \App\Jobs\ProcessDefaultJob(['id'=>1])))->onConnection('redis-sentinel')->onQueue('default'); echo 'dispatched';"
sleep 3
./vendor/bin/sail artisan horizon:status           # inactive-jobs should drain
```

- [ ] **Step 10: Commit**

```bash
git add -A && git commit -m "feat: horizon on redis-sentinel, single queue runtime"
```

---

### Task 5: Octane (Swoole) as HTTP server

**Files:**
- Modify: `docker/8.5/Dockerfile` (`SUPERVISOR_PHP_COMMAND` env line)
- Create: `config/octane.php` (via octane:install)
- Modify: `.env` / `.env.example` (`OCTANE_SERVER=swoole`)

**Interfaces:**
- Produces: app served by Octane on container port 80; supervisord program name unchanged (`php`).

- [ ] **Step 1: Install**

```bash
./vendor/bin/sail composer require laravel/octane
./vendor/bin/sail artisan octane:install --server=swoole
```

- [ ] **Step 2: Dockerfile supervisord command**

In `docker/8.5/Dockerfile`, change:

```dockerfile
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
```
to:
```dockerfile
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan octane:start --host=0.0.0.0 --port=80 --max-requests=500"
```

- [ ] **Step 3: Rebuild + verify**

```bash
./vendor/bin/sail build --no-cache && ./vendor/bin/sail up -d
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:80
# expect: 200 (redirect to login counts too: 302)
./vendor/bin/sail artisan octane:status
```

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat: serve http via octane swoole"
```

---

### Task 6: Install nwidart/laravel-modules + create modules

**Files:**
- Modify: `composer.json` (allow-plugins + autoload `Modules\\`)
- Create: `config/modules.php` (published), `Modules/RabbitRs`, `Modules/QueueLab`, `Modules/FrontLab` (generated)

**Interfaces:**
- Produces: three modules with generated service providers auto-discovered; used by Tasks 7, 8, 10.

- [ ] **Step 1: Install**

```bash
./vendor/bin/sail composer require nwidart/laravel-modules
```
Composer will prompt to allow `wikimedia/composer-merge-plugin` — allow it (also add `"wikimedia/composer-merge-plugin": true` to `config.allow-plugins` in `composer.json`).

- [ ] **Step 2: Autoload**

In `composer.json` `autoload.psr-4` add `"Modules\\": "Modules/"`, then:

```bash
./vendor/bin/sail composer dump-autoload
```

- [ ] **Step 3: Generate modules**

```bash
./vendor/bin/sail artisan module:make RabbitRs QueueLab FrontLab
./vendor/bin/sail artisan module:list
```
If the command name differs in v13 (check `sail artisan list | grep module`), use the equivalent documented command. Inspect the generated structure (provider namespace + paths) — it is authoritative for Tasks 7/8/10 import paths.

- [ ] **Step 4: Verify + commit**

`module:list` shows the 3 enabled modules.

```bash
git add -A && git commit -m "feat: laravel-modules with RabbitRs, QueueLab, FrontLab skeletons"
```

---

### Task 7: Migrate rabbit-rs code into RabbitRs module + dual dispatch

**Files:**
- Move: `app/Jobs/*.php` (8 files) → `Modules/RabbitRs/app/Jobs/` (paths per generated layout)
- Move: `app/Console/Commands/RabbitRs*.php` (3 files) → `Modules/RabbitRs/app/Console/` (paths per generated layout)
- Delete: `app/Jobs/`, `app/Console/Commands/` (emptied)
- Modify: `Modules/RabbitRs/app/Console/RabbitRsDemoCommand.php` (add `--connection`)

**Interfaces:**
- Consumes: queue connection `redis-sentinel` (Task 4).
- Produces: job classes namespaced under the module (exact namespace = generated layout, e.g. `Modules\RabbitRs\App\Jobs\ProcessDefaultJob` — mirror whatever the generator produced for the provider); `rabbit-rs:demo --connection=redis-sentinel|rabbit-rs|both`.

- [ ] **Step 1: Move files and fix namespaces**

Move the 8 job classes and 3 commands into the module's generated app folders. Update namespaces to the module layout found in Task 6 Step 3, update imports (`use App\Jobs\...` → module namespace) inside the commands. Register nothing manually — module providers auto-load module `Console\Commands` (verify the generated provider; if it does not auto-register commands, add `$this->commands([...])` in the module provider's `boot()`).

- [ ] **Step 2: Add `--connection` to the demo command**

In `RabbitRsDemoCommand`:

Signature: add `{--connection= : redis-sentinel, rabbit-rs, or both (default: QUEUE_CONNECTION)}`.

`handle()` — after existing validation, resolve connections:

```php
$connections = match ($this->option('connection') ?: config('queue.default')) {
    'redis-sentinel' => ['redis-sentinel'],
    'rabbit-rs' => ['rabbit-rs'],
    'both' => ['redis-sentinel', 'rabbit-rs'],
    default => $this->error('Invalid --connection') ?? ['rabbit-rs'],
};
```

Wrap the existing dispatch loop in `foreach ($connections as $connection)`; compute the queue name per connection and replace the final `$job->onQueue($queueName)` with `->onConnection($connection)->onQueue($queueName)`:

```php
$queueName = $connection === 'redis-sentinel'
    ? $this->redisQueueFor($vhost, $queue)
    : ($m === 'single' ? "{$s}.{$vhost}.{$queue}" : "{$s}.all.{$this->combinedQueueName($vhost, $queue)}");
```

with:

```php
private function redisQueueFor(string $vhost, string $queue): string
{
    return $vhost === 'default' ? $queue : 'bulk';
}
```

Update the final `info()` to include the connections list and "check Horizon: make horizon" hint instead of supervisord workers.

- [ ] **Step 3: Verify both connections**

```bash
./vendor/bin/sail artisan rabbit-rs:demo --connection=redis-sentinel --count=1
sleep 3   # Horizon processes them (dashboard /horizon shows recent jobs)
./vendor/bin/sail artisan rabbit-rs:demo --connection=rabbit-rs --count=1
# expect: success, messages accumulate in RabbitMQ (no workers by design)
./vendor/bin/sail composer test
```

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat: rabbit-rs code in RabbitRs module, dual dispatch"
```

---

### Task 8: QueueLab module — stress command + sentinel listeners

**Files:**
- Create: `Modules/QueueLab/app/Console/QueueLabStressCommand.php`
- Create: `Modules/QueueLab/app/Jobs/StressJob.php`
- Modify: `Modules/QueueLab/app/Providers/QueueLabServiceProvider.php` (register commands + event listeners)
- Modify: `config/logging.php` (add `sentinel` channel)

**Interfaces:**
- Consumes: connection `redis-sentinel` queues (Task 4); job class namespaced per module layout.
- Produces: `queue-lab:stress {--count=100} {--queue=bulk} {--connection=redis-sentinel} {--sleep-ms=0} {--fail-every=0}`; sentinel events logged to `storage/logs/sentinel.log` (used by Task 12 chaos verification).

- [ ] **Step 1: StressJob**

```php
<?php

namespace Modules\QueueLab\App\Jobs; // adjust to generated namespace

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $id,
        public int $sleepMs = 0,
        public int $failEvery = 0,
    ) {}

    public function handle(): void
    {
        if ($this->sleepMs > 0) {
            usleep($this->sleepMs * 1000);
        }

        if ($this->failEvery > 0 && $this->id % $this->failEvery === 0) {
            throw new \RuntimeException("StressJob #{$this->id} simulated failure");
        }
    }
}
```

- [ ] **Step 2: Stress command**

```php
<?php

namespace Modules\QueueLab\App\Console; // adjust to generated namespace

use Modules\QueueLab\App\Jobs\StressJob; // adjust to generated namespace
use Illuminate\Console\Command;

class QueueLabStressCommand extends Command
{
    protected $signature = 'queue-lab:stress
                            {--count=100 : Jobs to dispatch}
                            {--queue=bulk : Target queue}
                            {--connection=redis-sentinel : Queue connection}
                            {--sleep-ms=0 : Simulated work per job}
                            {--fail-every=0 : Fail every Nth job (0 = never)}';

    protected $description = 'Dispatch a burst of stress jobs (Horizon / redis-sentinel)';

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 1; $i <= $count; $i++) {
            StressJob::dispatch($i, (int) $this->option('sleep-ms'), (int) $this->option('fail-every'))
                ->onConnection($this->option('connection'))
                ->onQueue($this->option('queue'));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$count} StressJob to {$this->option('connection')}@{$this->option('queue')}");

        return 0;
    }
}
```

- [ ] **Step 3: Sentinel event listeners**

In `QueueLabServiceProvider::boot()` (namespace per generated layout):

```php
use Goopil\LaravelRedisSentinel\Events\RedisSentinelConnectionFailed;
use Goopil\LaravelRedisSentinel\Events\RedisSentinelConnectionReconnected;
use Goopil\LaravelRedisSentinel\Events\RedisSentinelMasterFailed;
use Goopil\LaravelRedisSentinel\Events\RedisSentinelMasterReconnected;
use Goopil\LaravelRedisSentinel\Events\RedisSentinelReplicaFallback;
use Illuminate\Support\Facades\Event;

foreach ([
    RedisSentinelMasterFailed::class,
    RedisSentinelMasterReconnected::class,
    RedisSentinelConnectionFailed::class,
    RedisSentinelConnectionReconnected::class,
    RedisSentinelReplicaFallback::class,
] as $event) {
    Event::listen($event, fn ($e) => logger('sentinel')->info(class_basename($e), (array) $e));
}
```

Plus register the command in the provider (`$this->commands([QueueLabStressCommand::class]);` if the generated provider doesn't auto-discover).

In `config/logging.php` `channels`, add:

```php
        'sentinel' => [
            'driver' => 'single',
            'path' => storage_path('logs/sentinel.log'),
            'level' => 'info',
        ],
```

- [ ] **Step 4: Verify**

```bash
./vendor/bin/sail artisan queue-lab:stress --count=50 --sleep-ms=5
# /horizon → supervisor-bulk shows throughput; recent jobs complete
```

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: queue-lab stress command + sentinel event logging"
```

---

### Task 9: Inertia SSR via ClusterKit

**Files:**
- Create: `resources/js/ssr.jsx`
- Modify: `vite.config.js` (ssr input)
- Modify: `package.json` (dependency + script)
- Create: `node/clusterkit-server.mjs`
- Create: `config/inertia.php` (published/modified)
- Modify: `docker/8.5/supervisord.conf` (add `ssr` program)
- Modify: `.env` / `.env.example` (`SSR_PORT`, `SUPERVISOR_SSR`)

**Interfaces:**
- Consumes: pages under `resources/js/Pages/**` (shared with app.jsx).
- Produces: `POST /render` on `http://127.0.0.1:13715` (same protocol as `@inertiajs/server`); supervisord program `ssr`; Inertia middleware falls back to CSR when SSR is down.

- [ ] **Step 1: Add dependency + entrypoint**

```bash
./vendor/bin/sail npm install @goopil/clusterkit
```

`resources/js/ssr.jsx`:

```jsx
import ReactDOMServer from 'react-dom/server';
import { createInertiaApp } from '@inertiajs/react';

export default function render(page) {
    return createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => `${title} - ${import.meta.env.VITE_APP_NAME}`,
        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
            return pages[`./Pages/${name}.jsx`];
        },
        setup: ({ App, props }) => <App {...props} />,
    });
}
```

`vite.config.js` — replace the laravel plugin options:

```js
        laravel({
            input: 'resources/js/app.jsx',
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
```

`package.json` scripts:

```json
        "build:ssr": "vite build --ssr",
```

- [ ] **Step 2: ClusterKit server**

`node/clusterkit-server.mjs`:

```js
import http from 'node:http';
import { Orchestrator } from '@goopil/clusterkit';

const SSR_PORT = Number(process.env.SSR_PORT || 13715);
const SSR_HOST = process.env.SSR_HOST || '127.0.0.1';

const orchestrator = new Orchestrator({ logger: console });

orchestrator.run(async () => {
    const { default: render } = await import('../bootstrap/ssr/ssr.js');

    const server = http.createServer(async (req, res) => {
        if (req.method !== 'POST' || req.url !== '/render') {
            res.statusCode = 404;
            return res.end();
        }

        let raw = '';
        for await (const chunk of req) raw += chunk;

        try {
            const result = await render(JSON.parse(raw));
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(result));
        } catch (error) {
            res.statusCode = 500;
            res.end(String(error));
        }
    });

    server.listen({ port: SSR_PORT, host: SSR_HOST, reusePort: true, exclusive: true });
    orchestrator.registerOnShutdown(() => server.close());
});
```

- [ ] **Step 3: Inertia config + supervisord**

```bash
./vendor/bin/sail artisan vendor:publish --tag=inertia-config
```
(or create `config/inertia.php` with the published defaults), then set:

```php
    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13715'),
    ],
```

In `docker/8.5/supervisord.conf`, append:

```ini
[program:ssr]
command=/usr/bin/node /var/www/html/node/clusterkit-server.mjs
directory=/var/www/html
user=%(ENV_SUPERVISOR_PHP_USER)s
autostart=%(ENV_SUPERVISOR_SSR)s
autorestart=true
stopsignal=TERM
stopasgroup=true
environment=WEB_CONCURRENCY="2",SSR_PORT="%(ENV_SSR_PORT)s"
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

`.env` + `.env.example`: `SUPERVISOR_SSR=true` and `SSR_PORT=13715`.

- [ ] **Step 4: Build + rebuild + verify**

```bash
./vendor/bin/sail npm run build
./vendor/bin/sail npm run build:ssr
./vendor/bin/sail build --no-cache && ./vendor/bin/sail up -d
./vendor/bin/sail exec supervisorctl status ssr
curl -s -X POST http://localhost:13715/render -H "Content-Type: application/json" \
  -d '{"component":"Welcome","props":{},"url":"/"}' | head -c 200
# expect JSON with "body" containing <div data-page="Welcome"
curl -s http://localhost/ | grep -o 'data-page="Welcome"' | head -1
# expect: data-page="Welcome" (server-rendered through Octane -> ClusterKit SSR)
```

If port 13715 isn't reachable from the host (SSR binds 127.0.0.1 inside the container), do the render check via `sail exec laravel.test curl -s -X POST http://127.0.0.1:13715/render ...` instead, and verify SSR-from-Laravel via the `data-page` check on port 80.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: inertia ssr orchestrated by clusterkit"
```

---

### Task 10: FrontLab dashboard (dispatch + stats)

**Files:**
- Create: `Modules/FrontLab/app/Http/Controllers/DashboardController.php`
- Create: `Modules/FrontLab/app/Http/Controllers/DispatchController.php`
- Modify: `Modules/FrontLab/routes/web.php` (module routes)
- Modify: `routes/web.php` (`/` redirects to lab)
- Modify: `resources/js/Pages/Dashboard.jsx` (full rewrite)
- Create: `resources/js/Pages/Lab/Stress.jsx` (thin page reusing dispatch panel pattern)

**Interfaces:**
- Consumes: job classes from `Modules\RabbitRs` (namespace per Task 7 layout); connection `redis-sentinel`.
- Produces: `GET /lab` (dashboard, auth-gated), `POST /lab/dispatch` (JSON: `{job, connection, queue, count}`).

- [ ] **Step 1: Controllers**

`DashboardController`:

```php
<?php

namespace Modules\FrontLab\App\Http\Controllers; // adjust to generated namespace

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'horizon' => [
                'masters' => collect(app(MasterSupervisorRepository::class)->all())->pluck('name'),
                'recent' => app(JobRepository::class)->countRecent(),
                'pending' => app(JobRepository::class)->countPending(),
                'failed' => app(JobRepository::class)->countFailed(),
            ],
            'queues' => collect(['default', 'high-priority', 'bulk'])
                ->mapWithKeys(fn ($q) => [$q => Redis::connection('default')->llen("queues:{$q}")]),
        ];

        if ($request->boolean('only-stats')) {
            return response()->json($stats);
        }

        return inertia('Dashboard', [
            'stats' => $stats,
        ]);
    }
}
```

`DispatchController`:

```php
<?php

namespace Modules\FrontLab\App\Http\Controllers; // adjust to generated namespace

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\RabbitRs\App\Jobs\ProcessDefaultJob;      // adjust to Task 7 namespaces
use Modules\RabbitRs\App\Jobs\ProcessHighPriorityJob; // adjust
use Modules\RabbitRs\App\Jobs\ProcessOrderCreated;    // adjust
use Modules\RabbitRs\App\Jobs\SendEmailNotification;  // adjust

class DispatchController extends Controller
{
    private const JOBS = [
        'default' => ProcessDefaultJob::class,
        'high-priority' => ProcessHighPriorityJob::class,
        'order' => ProcessOrderCreated::class,
        'email' => SendEmailNotification::class,
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job' => 'required|string|in:'.implode(',', array_keys(self::JOBS)),
            'connection' => 'required|in:redis-sentinel,rabbit-rs,both',
            'queue' => 'nullable|string|max:100',
            'count' => 'required|integer|min:1|max:10000',
        ]);

        $connections = $validated['connection'] === 'both'
            ? ['redis-sentinel', 'rabbit-rs']
            : [$validated['connection']];

        $dispatched = 0;
        foreach ($connections as $connection) {
            $queue = $validated['queue'] ?? ($connection === 'redis-sentinel' ? 'default' : 'simple.default.default');
            for ($i = 0; $i < $validated['count']; $i++) {
                self::JOBS[$validated['job']]::dispatch(['id' => $i, 'source' => 'lab'])
                    ->onConnection($connection)
                    ->onQueue($queue);
                $dispatched++;
            }
        }

        return back()->with('success', "Dispatched {$dispatched} jobs.");
    }
}
```

- [ ] **Step 2: Routes**

`Modules/FrontLab/routes/web.php` (adapt middleware wrapper to generated module route file conventions):

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\FrontLab\App\Http\Controllers\DashboardController;   // adjust
use Modules\FrontLab\App\Http\Controllers\DispatchController;    // adjust

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/lab', [DashboardController::class, 'index'])->name('lab.dashboard');
    Route::post('/lab/dispatch', [DispatchController::class, 'store'])->name('lab.dispatch');
});
```

`routes/web.php` root route becomes `return redirect()->route('lab.dashboard');`.

- [ ] **Step 3: Dashboard.jsx rewrite**

Replace `resources/js/Pages/Dashboard.jsx` with a page that renders: stats cards (from `stats` prop), a dispatch panel (selects: job, connection, queue, count input + POST via `router.post(route('lab.dispatch'), ...)`), and a live feed section that polls:

```jsx
useEffect(() => {
    const id = setInterval(() => {
        router.reload({ only: ['stats'] });
    }, 3000);
    return () => clearInterval(id);
}, []);
```

Style with the existing Breeze components (`ApplicationLayout`, `NavLink` additions for `/lab`). Add `Lab/Stress.jsx` page + GET route later only if trivially needed — the dispatch panel already covers the stress use case via `count`.

- [ ] **Step 4: Build + verify**

```bash
./vendor/bin/sail npm run build
```
Login at `http://localhost`, open `/lab`, dispatch 10 `default` jobs on `redis-sentinel`, verify pending count drops in the polled stats and in `/horizon`.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: frontlab dashboard with dispatch panel and live stats"
```

---

### Task 11: Makefile, README, env sync

**Files:**
- Modify: `Makefile` (remove worker targets, add new ones)
- Modify: `README.md` (rewrite for new architecture)
- Modify: `.env.example` (final sync)

**Interfaces:**
- Consumes: everything above. Produces: operator entry points.

- [ ] **Step 1: Makefile**

Remove `check-queues` python blocks and `workers-*` targets. Add:

```makefile
horizon:
	./vendor/bin/sail artisan horizon:status

horizon-probes:
	./vendor/bin/sail artisan horizon:ready && ./vendor/bin/sail artisan horizon:alive

ssr-build:
	./vendor/bin/sail npm run build && ./vendor/bin/sail npm run build:ssr

sentinel-watch:
	./vendor/bin/sail exec sentinel-1 valkey-cli -p 26379 --json subscribe "+switch-master" "+failover-end" "+sdown" "+odown"

chaos-kill-master:
	docker compose stop valkey-master

chaos-heal:
	docker compose start valkey-master
```

Change `demo` targets to pass `--connection=redis-sentinel` by default (e.g. `demo: ./vendor/bin/sail artisan rabbit-rs:demo --connection=both`), keep `demo-rabbit`/`demo-redis` variants.

- [ ] **Step 2: README**

Rewrite the feature list and quick start: Octane, Horizon on Valkey Sentinel (diagram of topology), SSR ClusterKit, modules table, dual dispatch, chaos testing section, updated troubleshooting (Horizon not connecting → check sentinels; SSR down → CSR fallback; etc.). Remove supervisord worker documentation.

- [ ] **Step 3: env + commit**

Sync `.env.example` with every new var introduced (SENTINEL hosts, SUPERVISOR_HORIZON, SUPERVISOR_SSR, SSR_PORT, QUEUE_CONNECTION=redis-sentinel) and ensure `.env` matches.

```bash
git add -A && git commit -m "docs: playground v2 makefile and readme"
```

---

### Task 12: End-to-end verification + chaos drill

**Files:** none (verification only)

**Interfaces:**
- Consumes: full stack.

- [ ] **Step 1: Full boot from scratch**

```bash
./vendor/bin/sail down -v
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --force
make setup   # rabbit topology still provisioned
```
`./vendor/bin/sail exec supervisorctl status` → php, horizon, ssr all RUNNING.

- [ ] **Step 2: Functional pass**

```bash
make demo                          # both connections
make horizon-probes                # ready + alive
./vendor/bin/sail artisan queue-lab:stress --count=200 --sleep-ms=5 --fail-every=50
# /horizon: throughput visible, failed jobs visible with retries
curl -s http://localhost/ | grep -o 'data-page=' | head -1   # SSR active
./vendor/bin/sail composer test    # green
```

- [ ] **Step 3: Chaos drill (sentinel failover)**

```bash
make sentinel-watch   # terminal 1
make chaos-kill-master
# watch: +sdown master → +odown → +switch-master mymaster → valkey-1/2
./vendor/bin/sail exec supervisorctl status          # horizon still RUNNING
make horizon-probes
./vendor/bin/sail artisan rabbit-rs:demo --connection=redis-sentinel --count=1   # still processed
make chaos-heal                                      # old master rejoins as replica
```
Confirm `storage/logs/sentinel.log` captured the events, and no stuck pending jobs.

- [ ] **Step 4: Final commit**

```bash
git add -A && git commit -m "chore: playground v2 verified (octane, horizon/sentinel, ssr, modules)"
```
