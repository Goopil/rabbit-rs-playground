# Example Modules Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Three didactic modules (one per lib: rabbit-rs-laravel, laravel-redis-sentinel, clusterkit) with `examples:*` commands, module READMEs, and a `/lab/examples` panel in FrontLab.

**Architecture:** Follow the existing nwidart module conventions — LifecycleLab is the minimal skeleton for command-only modules; ClusterkitExamples adds a RouteServiceProvider + one public Inertia page (pages are auto-discovered by the glob in `resources/js/app.jsx`, no vite config changes). One shared support class (`SentinelWhoami`) feeds both a command and the FrontLab endpoint. Spec: `docs/specs/2026-09-14-example-modules-design.md`.

**Tech Stack:** Laravel 13 / PHP 8.5 (Sail), nwidart/laravel-modules v13, Inertia + React, PHPUnit via `vendor/bin/sail artisan test`.

## Global Constraints

- Commands use the `examples:` namespace: `examples:rabbit-rs:dispatch`, `examples:rabbit-rs:delay`, `examples:rabbit-rs:fail`, `examples:sentinel:whoami`, `examples:sentinel:cache`, `examples:clusterkit:render` (exact names).
- Module `composer.json` author is `{"name": "Goopil"}` — no email (matches the 5 existing modules).
- No new composer/npm dependencies. No new broker topology. Tests live in root `tests/Feature/` (suite convention), NOT in module `tests/` dirs.
- Do NOT duplicate the sentinel package's own `sentinel:status` command — `examples:sentinel:whoami` demonstrates the `RedisSentinelManager` API from app-side code.
- Inertia SSR payloads must NOT require `props.ziggy` — demo page must not call `route()` (it renders publicly through the SSR server).
- All commands must fail gracefully on unreachable infra (clear error line, exit 1) — never a stack trace.
- PHP style: run `vendor/bin/pint --dirty --format agent` before every commit. Commit messages follow repo history (lowercase conventional prefix, English).

---

### Task 1: RabbitRsExamples module + dispatch example

**Files:**
- Create: `Modules/RabbitRsExamples/module.json`
- Create: `Modules/RabbitRsExamples/composer.json`
- Create: `Modules/RabbitRsExamples/app/Providers/RabbitRsExamplesServiceProvider.php`
- Create: `Modules/RabbitRsExamples/app/Jobs/ProcessOrderJob.php`
- Create: `Modules/RabbitRsExamples/app/Console/ExamplesRabbitRsDispatchCommand.php`
- Modify: `modules_statuses.json` (add `"RabbitRsExamples": true`)
- Test: `tests/Feature/ExamplesRabbitRsCommandsTest.php`

**Interfaces:**
- Produces: job class `Modules\RabbitRsExamples\Jobs\ProcessOrderJob` (constructor `array $payload`, `tags(): array` with `connection:…`, `queue:…`, `job:examples-order`); command `examples:rabbit-rs:dispatch {--count=1} {--connection=}` dispatching to `default` / `high-priority` / `bulk` on `redis-sentinel` + `rabbit-rs`. Later tasks add two more commands to the same test file and provider.

- [ ] **Step 1: Scaffold the module**

```bash
vendor/bin/sail artisan module:make RabbitRsExamples --no-interaction
vendor/bin/sail composer dump-autoload
```

Then trim to the LifecycleLab shape: delete `Modules/RabbitRsExamples/{vite.config.js,package.json}` and any generated `app/Http`, `app/Providers/{EventServiceProvider,RouteServiceProvider}`, `routes/`, `database/`, `resources/`, `tests/` subtrees EXCEPT keep what the plan creates below (the command-only modules carry no routes). Replace generated `app/Providers/RabbitRsExamplesServiceProvider.php` content with the code in Step 3. Delete `Modules/RabbitRsExamples/config/` if generated.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/ExamplesRabbitRsCommandsTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Modules\RabbitRsExamples\Jobs\ProcessOrderJob;
use Tests\TestCase;

/**
 * The examples:* commands show intended lib usage — this suite guards the
 * dispatch surface (job, connection, queue, tags) with Queue::fake.
 */
class ExamplesRabbitRsCommandsTest extends TestCase
{
    public function test_dispatch_command_routes_one_order_job_per_queue_and_connection(): void
    {
        Queue::fake();

        Artisan::call('examples:rabbit-rs:dispatch', ['--count' => 1]);

        Queue::assertPushed(ProcessOrderJob::class, 6); // 3 queues × 2 connections
        Queue::assertPushed(ProcessOrderJob::class, fn ($job, $queue) => in_array($queue, ['default', 'high-priority', 'bulk']));
        Queue::assertPushed(ProcessOrderJob::class, fn ($job) => in_array($job->connection ?? 'redis-sentinel', ['redis-sentinel', 'rabbit-rs']));
    }

    public function test_dispatch_command_rejects_an_unknown_connection(): void
    {
        $exitCode = Artisan::call('examples:rabbit-rs:dispatch', ['--connection' => 'nope']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid --connection', Artisan::output());
    }
}
```

- [ ] **Step 3: Write the module files**

`Modules/RabbitRsExamples/module.json`:

```json
{
    "name": "RabbitRsExamples",
    "alias": "rabbitrsexamples",
    "description": "Didactic usage examples for goopil/rabbit-rs-laravel",
    "keywords": [],
    "priority": 0,
    "providers": [
        "Modules\\RabbitRsExamples\\Providers\\RabbitRsExamplesServiceProvider"
    ],
    "files": []
}
```

`Modules/RabbitRsExamples/composer.json`:

```json
{
    "name": "nwidart/rabbitrsexamples",
    "description": "Didactic usage examples for goopil/rabbit-rs-laravel: routing, delays, failure path",
    "authors": [
        {
            "name": "Goopil"
        }
    ],
    "extra": {
        "laravel": {
            "providers": [],
            "aliases": {}
        }
    },
    "autoload": {
        "psr-4": {
            "Modules\\RabbitRsExamples\\": "app/"
        }
    }
}
```

`Modules/RabbitRsExamples/app/Providers/RabbitRsExamplesServiceProvider.php`:

```php
<?php

namespace Modules\RabbitRsExamples\Providers;

use Modules\RabbitRsExamples\Console\ExamplesRabbitRsDispatchCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class RabbitRsExamplesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'RabbitRsExamples';

    protected string $nameLower = 'rabbitrsexamples';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExamplesRabbitRsDispatchCommand::class,
    ];
}
```

(Task 2 appends `ExamplesRabbitRsDelayCommand::class` and `ExamplesRabbitRsFailCommand::class` to `$commands`.)

`Modules/RabbitRsExamples/app/Jobs/ProcessOrderJob.php`:

```php
<?php

namespace Modules\RabbitRsExamples\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Didactic job: one class, three queues — the connection's `routing_key =
 * {queue}` mapping does the routing, no per-queue job classes needed.
 */
class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function tags(): array
    {
        return [
            'connection:'.($this->connection ?? config('queue.default')),
            'queue:'.($this->queue ?? 'default'),
            'job:examples-order',
        ];
    }

    public function handle(): void
    {
        // A real app processes the order here; the example only proves the
        // transport delivers on the queue the publisher chose.
    }
}
```

`Modules/RabbitRsExamples/app/Console/ExamplesRabbitRsDispatchCommand.php`:

```php
<?php

namespace Modules\RabbitRsExamples\Console;

use Illuminate\Console\Command;
use Modules\RabbitRsExamples\Jobs\ProcessOrderJob;

class ExamplesRabbitRsDispatchCommand extends Command
{
    protected $signature = 'examples:rabbit-rs:dispatch
                            {--count=1 : Orders per queue}
                            {--connection= : redis-sentinel, rabbit-rs, or both (default: both)}';

    protected $description = 'Example: route one job class to default/high-priority/bulk via routing_key = {queue}';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        if ($count < 1) {
            $this->error("Invalid count: {$count}. Must be >= 1");

            return 1;
        }

        $connections = match ($this->option('connection') ?: 'both') {
            'redis-sentinel' => ['redis-sentinel'],
            'rabbit-rs' => ['rabbit-rs'],
            'both' => ['redis-sentinel', 'rabbit-rs'],
            default => null,
        };
        if ($connections === null) {
            $this->error('Invalid --connection. Use: redis-sentinel, rabbit-rs, or both');

            return 1;
        }

        $queues = ['default', 'high-priority', 'bulk'];
        foreach ($connections as $connection) {
            foreach ($queues as $queue) {
                for ($i = 0; $i < $count; $i++) {
                    ProcessOrderJob::dispatch(['order' => uniqid('order-')])
                        ->onConnection($connection)
                        ->onQueue($queue);
                }
            }
        }

        $this->info("Dispatched {$count} order(s) × ".count($queues).' queues × '.count($connections).' connection(s).');
        $this->line('The routing contract (copy this):');
        $this->line('  ProcessOrderJob::dispatch([...])->onConnection($c)->onQueue($q);');
        $this->line('  config/queue.php: routing_key = {queue} → the queue name IS the routing key.');
        $this->line('Watch them land: /horizon (Recent jobs) or /lab.');

        return 0;
    }
}
```

Add `"RabbitRsExamples": true` to `modules_statuses.json` (keep existing entries).

- [ ] **Step 4: Run tests — verify they pass**

Run: `vendor/bin/sail artisan test --filter=ExamplesRabbitRsCommandsTest --compact`
Expected: 2 passed. If `Command not found`, re-run `vendor/bin/sail composer dump-autoload`.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add Modules/RabbitRsExamples modules_statuses.json tests/Feature/ExamplesRabbitRsCommandsTest.php
git commit -m "feat(examples): rabbit-rs dispatch example module — one job class, three queues"
```

---

### Task 2: delay + fail examples

**Files:**
- Create: `Modules/RabbitRsExamples/app/Jobs/DelayedReportJob.php`
- Create: `Modules/RabbitRsExamples/app/Jobs/FlakyJob.php`
- Create: `Modules/RabbitRsExamples/app/Console/ExamplesRabbitRsDelayCommand.php`
- Create: `Modules/RabbitRsExamples/app/Console/ExamplesRabbitRsFailCommand.php`
- Modify: `Modules/RabbitRsExamples/app/Providers/RabbitRsExamplesServiceProvider.php` (append both commands to `$commands`)
- Test: `tests/Feature/ExamplesRabbitRsCommandsTest.php` (add methods)

**Interfaces:**
- Consumes: `RabbitRsExamplesServiceProvider` (Task 1).
- Produces: `examples:rabbit-rs:delay {--seconds=30} {--connection=rabbit-rs}` and `examples:rabbit-rs:fail {--connection=rabbit-rs}`; jobs `DelayedReportJob` (payload array) and `FlakyJob` (`public int $tries = 3`, always throws). Both command classes are appended to the provider's `$commands`.

- [ ] **Step 1: Write the failing tests (append to ExamplesRabbitRsCommandsTest)**

```php
    public function test_delay_command_pushes_a_delayed_report_job(): void
    {
        Queue::fake();

        Artisan::call('examples:rabbit-rs:delay', ['--seconds' => 30]);

        Queue::assertPushed(DelayedReportJob::class, 1);
    }

    public function test_fail_command_pushes_a_flaky_job_on_the_bulk_queue(): void
    {
        Queue::fake();

        Artisan::call('examples:rabbit-rs:fail');

        Queue::assertPushedOn('bulk', FlakyJob::class);
    }
```

Imports to add at the top of the file: `use Modules\RabbitRsExamples\Jobs\DelayedReportJob;` and `use Modules\RabbitRsExamples\Jobs\FlakyJob;`.

- [ ] **Step 2: Run tests — verify the two new ones fail**

Run: `vendor/bin/sail artisan test --filter=ExamplesRabbitRsCommandsTest --compact`
Expected: 2 new tests FAIL (command not found), the 2 existing pass.

- [ ] **Step 3: Write the jobs and commands**

First, update `Modules/RabbitRsExamples/app/Providers/RabbitRsExamplesServiceProvider.php` — the `$commands` array becomes:

```php
    protected array $commands = [
        ExamplesRabbitRsDispatchCommand::class,
        ExamplesRabbitRsDelayCommand::class,
        ExamplesRabbitRsFailCommand::class,
    ];
```

and add the two imports:

```php
use Modules\RabbitRsExamples\Console\ExamplesRabbitRsDelayCommand;
use Modules\RabbitRsExamples\Console\ExamplesRabbitRsFailCommand;
```

`Modules/RabbitRsExamples/app/Jobs/DelayedReportJob.php`:

```php
<?php

namespace Modules\RabbitRsExamples\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Didactic job for the delay demo: with RABBIT_RS_DELAY_MODE=ttl the
 * later() publish lands in a quantized bucket queue
 * (rabbit-rs.delay.<fingerprint>.<id>.<ms>) and the broker releases it
 * when the bucket TTL expires.
 */
class DelayedReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function tags(): array
    {
        return [
            'connection:'.($this->connection ?? config('queue.default')),
            'queue:'.($this->queue ?? 'default'),
            'job:examples-delayed-report',
        ];
    }

    public function handle(): void
    {
        Log::info('DelayedReportJob delivered', $this->payload);
    }
}
```

`Modules/RabbitRsExamples/app/Jobs/FlakyJob.php`:

```php
<?php

namespace Modules\RabbitRsExamples\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Didactic job for the failure demo: always throws, so the worker runs
 * the full tries → retries → failed recording path.
 */
class FlakyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $orderId
    ) {}

    public function tags(): array
    {
        return [
            'connection:'.($this->connection ?? config('queue.default')),
            'queue:'.($this->queue ?? 'default'),
            'job:examples-flaky',
        ];
    }

    public function handle(): void
    {
        throw new RuntimeException("FlakyJob: order {$this->orderId} always fails (demo)");
    }
}
```

`Modules/RabbitRsExamples/app/Console/ExamplesRabbitRsDelayCommand.php`:

```php
<?php

namespace Modules\RabbitRsExamples\Console;

use Illuminate\Console\Command;
use Modules\RabbitRsExamples\Jobs\DelayedReportJob;

class ExamplesRabbitRsDelayCommand extends Command
{
    protected $signature = 'examples:rabbit-rs:delay
                            {--seconds=30 : Delay before delivery}
                            {--connection=rabbit-rs : redis-sentinel or rabbit-rs}';

    protected $description = 'Example: later(N) with the ttl delay mode — buckets quantize UP to 1/5/30/120s';

    public function handle(): int
    {
        $seconds = (int) $this->option('seconds');
        if ($seconds < 1) {
            $this->error("Invalid --seconds: {$seconds}. Must be >= 1");

            return 1;
        }

        $connection = $this->option('connection');
        if (! in_array($connection, ['redis-sentinel', 'rabbit-rs'], true)) {
            $this->error('Invalid --connection. Use: redis-sentinel or rabbit-rs');

            return 1;
        }

        DelayedReportJob::dispatch(['report' => uniqid('report-'), 'at' => now()->toIso8601String()])
            ->delay($seconds)
            ->onConnection($connection)
            ->onQueue('default');

        $this->info("Delayed a report by {$seconds}s on {$connection}/default.");
        $this->line('The delay contract (copy this):');
        $this->line('  DelayedReportJob::dispatch([...])->delay($seconds)->onConnection("rabbit-rs");');
        $this->line('  ttl mode quantizes UP to the configured buckets (1/5/30/120s): later(10) → the 30s bucket.');
        $this->line('Watch the bucket queues in RabbitMQ Management (localhost:15672): rabbit-rs.delay.*');

        return 0;
    }
}
```

`Modules/RabbitRsExamples/app/Console/ExamplesRabbitRsFailCommand.php`:

```php
<?php

namespace Modules\RabbitRsExamples\Console;

use Illuminate\Console\Command;
use Modules\RabbitRsExamples\Jobs\FlakyJob;

class ExamplesRabbitRsFailCommand extends Command
{
    protected $signature = 'examples:rabbit-rs:fail
                            {--connection=rabbit-rs : redis-sentinel or rabbit-rs}';

    protected $description = 'Example: the failure path — tries, retries, failure recording';

    public function handle(): int
    {
        $connection = $this->option('connection');
        if (! in_array($connection, ['redis-sentinel', 'rabbit-rs'], true)) {
            $this->error('Invalid --connection. Use: redis-sentinel or rabbit-rs');

            return 1;
        }

        FlakyJob::dispatch(uniqid('order-'))
            ->onConnection($connection)
            ->onQueue('bulk');

        $this->info("Dispatched a FlakyJob (tries=3) on {$connection}/bulk.");
        $this->line('The failure contract (copy this):');
        $this->line('  public int $tries = 3;  // worker retries, then records the failure');
        $this->line('Watch it die: /horizon/failed (and the failed_jobs table).');

        return 0;
    }
}
```

- [ ] **Step 4: Run tests — verify all four pass**

Run: `vendor/bin/sail artisan test --filter=ExamplesRabbitRsCommandsTest --compact`
Expected: 4 passed.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add Modules/RabbitRsExamples tests/Feature/ExamplesRabbitRsCommandsTest.php
git commit -m "feat(examples): delay and failure-path examples for rabbit-rs"
```

---

### Task 3: SentinelExamples module

**Files:**
- Create: `Modules/SentinelExamples/module.json`, `composer.json`
- Create: `Modules/SentinelExamples/app/Providers/SentinelExamplesServiceProvider.php`
- Create: `Modules/SentinelExamples/app/Support/SentinelWhoami.php`
- Create: `Modules/SentinelExamples/app/Console/ExamplesSentinelWhoamiCommand.php`
- Create: `Modules/SentinelExamples/app/Console/ExamplesSentinelCacheCommand.php`
- Modify: `modules_statuses.json` (add `"SentinelExamples": true`)
- Test: `tests/Feature/ExamplesSentinelCommandsTest.php`

**Interfaces:**
- Consumes: package API — `Goopil\LaravelRedisSentinel\RedisSentinelManager` (container-injectable), `Goopil\LaravelRedisSentinel\Connectors\RedisSentinelConnector::serviceFromConfig()`, `$connector->createSentinel($name)`, `$sentinel->master($service)` / `replicas()` (idioms from the package's own `SentinelStatus` command).
- Produces: `Modules\SentinelExamples\Support\SentinelWhoami::capture(): array` — keys `service`, `sentinels`, `master`, `role`, `connection` — consumed by Task 6's `GET /lab/examples/sentinel`. Commands `examples:sentinel:whoami` and `examples:sentinel:cache`.

- [ ] **Step 1: Scaffold the module** (same trim as Task 1 Step 1, for `SentinelExamples`) + `vendor/bin/sail composer dump-autoload`.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/ExamplesSentinelCommandsTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Live tests: the sentinels run in the Sail stack (sentinel-1/2/3), same
 * spirit as the broker-up tests in the upstream suite.
 */
class ExamplesSentinelCommandsTest extends TestCase
{
    public function test_whoami_command_resolves_the_current_topology(): void
    {
        $exitCode = Artisan::call('examples:sentinel:whoami');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('service "mymaster"', $output);
        $this->assertStringContainsString('role:', $output);
    }

    public function test_cache_command_roundtrips_the_default_store(): void
    {
        $exitCode = Artisan::call('examples:sentinel:cache');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('round-trip OK', $output);
    }
}
```

- [ ] **Step 3: Write the module files**

`Modules/SentinelExamples/module.json`:

```json
{
    "name": "SentinelExamples",
    "alias": "sentinelexamples",
    "description": "Didactic usage examples for goopil/laravel-redis-sentinel",
    "keywords": [],
    "priority": 0,
    "providers": [
        "Modules\\SentinelExamples\\Providers\\SentinelExamplesServiceProvider"
    ],
    "files": []
}
```

`Modules/SentinelExamples/composer.json`:

```json
{
    "name": "nwidart/sentinelexamples",
    "description": "Didactic usage examples for goopil/laravel-redis-sentinel: connection resolution, cache round-trip",
    "authors": [
        {
            "name": "Goopil"
        }
    ],
    "extra": {
        "laravel": {
            "providers": [],
            "aliases": {}
        }
    },
    "autoload": {
        "psr-4": {
            "Modules\\SentinelExamples\\": "app/"
        }
    }
}
```

`Modules/SentinelExamples/app/Providers/SentinelExamplesServiceProvider.php`:

```php
<?php

namespace Modules\SentinelExamples\Providers;

use Modules\SentinelExamples\Console\ExamplesSentinelCacheCommand;
use Modules\SentinelExamples\Console\ExamplesSentinelWhoamiCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SentinelExamplesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'SentinelExamples';

    protected string $nameLower = 'sentinelexamples';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExamplesSentinelWhoamiCommand::class,
        ExamplesSentinelCacheCommand::class,
    ];
}
```

`Modules/SentinelExamples/app/Support/SentinelWhoami.php`:

```php
<?php

namespace Modules\SentinelExamples\Support;

use Goopil\LaravelRedisSentinel\Connectors\RedisSentinelConnector;
use Goopil\LaravelRedisSentinel\RedisSentinelManager;
use Throwable;

/**
 * App-side snapshot of what the sentinel driver resolved for THIS process —
 * the copyable core is three lines: resolveConnector() → createSentinel() →
 * master($service). Consumed by the whoami command and the /lab/examples
 * sentinel endpoint. Distinct from the package's own `sentinel:status`
 * (full diagnostics): this shows what an application asks for.
 */
class SentinelWhoami
{
    /**
     * @return array{service: string, sentinels: list<string>, master: string, role: ?string, connection: string}
     */
    public static function capture(): array
    {
        $manager = app(RedisSentinelManager::class);
        $config = (array) config('database.redis.default');
        $service = RedisSentinelConnector::serviceFromConfig($config);

        $connector = $manager->resolveConnector('default');
        $sentinel = $connector->createSentinel('default');

        $master = (array) $sentinel->master($service);
        $connection = $manager->resolve('default');
        $info = (array) ($connection->info('REPLICATION') ?? []);

        return [
            'service' => $service,
            'sentinels' => collect((array) ($config['sentinels'] ?? []))
                ->map(fn ($s) => ($s['host'] ?? '?').':'.($s['port'] ?? '?'))
                ->all(),
            'master' => ($master['ip'] ?? '?').':'.($master['port'] ?? '?'),
            'role' => $info['role'] ?? null,
            'connection' => $info['master_host'] ?? '?',
        ];
    }

    public static function captureOrError(): array
    {
        try {
            return self::capture();
        } catch (Throwable $e) {
            return [
                'service' => 'mymaster',
                'sentinels' => [],
                'master' => '?',
                'role' => null,
                'connection' => 'unreachable: '.$e->getMessage(),
            ];
        }
    }
}
```

`Modules/SentinelExamples/app/Console/ExamplesSentinelWhoamiCommand.php`:

```php
<?php

namespace Modules\SentinelExamples\Console;

use Illuminate\Console\Command;
use Modules\SentinelExamples\Support\SentinelWhoami;

class ExamplesSentinelWhoamiCommand extends Command
{
    protected $signature = 'examples:sentinel:whoami';

    protected $description = 'Example: resolve the sentinel topology from app-side code (manager API)';

    public function handle(): int
    {
        $snapshot = SentinelWhoami::captureOrError();

        $this->info('Sentinel-backed connection "default":');
        $this->line('  service: '.$snapshot['service']);
        $this->line('  sentinels: '.implode(', ', $snapshot['sentinels']));
        $this->line('  master: '.$snapshot['master']);
        $this->line('  role of resolved connection: '.($snapshot['role'] ?? '?'));
        $this->newLine();
        $this->line('The resolution contract (copy this):');
        $this->line('  $manager = app(RedisSentinelManager::class);');
        $this->line('  $connector = $manager->resolveConnector("default");');
        $this->line('  $sentinel = $connector->createSentinel("default");');
        $this->line('  $master = $sentinel->master("mymaster"); // or resolve() for a client');
        $this->line('Failover drills: make chaos-kill-master / make chaos-heal (README).');
        $this->line('Diagnostics: the package ships its own sentinel:status command.');

        return 0;
    }
}
```

`Modules/SentinelExamples/app/Console/ExamplesSentinelCacheCommand.php`:

```php
<?php

namespace Modules\SentinelExamples\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExamplesSentinelCacheCommand extends Command
{
    protected $signature = 'examples:sentinel:cache';

    protected $description = 'Example: cache round-trip through the sentinel-backed store';

    public function handle(): int
    {
        $store = config('cache.default');
        $key = 'examples:sentinel:'.uniqid();
        $value = 'sentinel-'.bin2hex(random_bytes(4));

        $startedAt = microtime(true);
        Cache::store()->put($key, $value, 30);
        $read = Cache::store()->get($key);
        Cache::store()->forget($key);
        $ms = (int) ((microtime(true) - $startedAt) * 1000);

        if ($read !== $value) {
            $this->error("Cache round-trip FAILED on store [{$store}]: wrote {$value}, read ".var_export($read, true));

            return 1;
        }

        $this->info("Cache round-trip OK on store [{$store}] in {$ms}ms.");
        $this->line('The usage contract (copy this):');
        $this->line('  Cache::store()->put($key, $value, $ttl);  // nothing sentinel-specific:');
        $this->line('  the driver resolves master/replicas under the hood (read/write split).');

        return 0;
    }
}
```

Add `"SentinelExamples": true` to `modules_statuses.json`.

- [ ] **Step 4: Run tests — verify both pass**

Run: `vendor/bin/sail artisan test --filter=ExamplesSentinelCommandsTest --compact`
Expected: 2 passed (sentinels are up in the Sail stack).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add Modules/SentinelExamples modules_statuses.json tests/Feature/ExamplesSentinelCommandsTest.php
git commit -m "feat(examples): sentinel whoami + cache round-trip examples"
```

---

### Task 4: ClusterkitExamples module — render command + SSR demo page

**Files:**
- Create: `Modules/ClusterkitExamples/module.json`, `composer.json`
- Create: `Modules/ClusterkitExamples/app/Providers/ClusterkitExamplesServiceProvider.php`
- Create: `Modules/ClusterkitExamples/app/Providers/RouteServiceProvider.php`
- Create: `Modules/ClusterkitExamples/app/Console/ExamplesClusterkitRenderCommand.php`
- Create: `Modules/ClusterkitExamples/routes/web.php`
- Create: `Modules/ClusterkitExamples/resources/js/Pages/Demo.jsx`
- Modify: `modules_statuses.json` (add `"ClusterkitExamples": true`)
- Test: `tests/Feature/ExamplesClusterkitRenderTest.php`

**Interfaces:**
- Consumes: the SSR server (`POST http://127.0.0.1:{SSR_PORT}/render`, env `SSR_PORT` default 13715, payload shape from `node/ck-roast.mjs`); the page glob in `resources/js/app.jsx` resolves `ClusterkitExamples/Demo` automatically — no vite config changes.
- Produces: `examples:clusterkit:render {--count=10} {--url=}`; public page `ClusterkitExamples/Demo` at `GET /clusterkit-demo` (route name `clusterkit.demo`).

- [ ] **Step 1: Scaffold the module** (same trim as Task 1 Step 1, for `ClusterkitExamples`) + `vendor/bin/sail composer dump-autoload`. KEEP `routes/` this time (add RouteServiceProvider below); delete generated `config/` and `resources/assets/`.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/ExamplesClusterkitRenderTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The render example fires real renders at the ClusterKit SSR server
 * (supervisord program `ssr`). When the pool is down the command must
 * fail gracefully (exit 1), and the in-suite test skips.
 */
class ExamplesClusterkitRenderTest extends TestCase
{
    public function test_render_command_renders_through_the_ssr_pool(): void
    {
        try {
            Http::withHeaders(['X-Probe' => '1'])
                ->post($this->ssrUrl().'/render', ['component' => 'ping'])
                ->throw();
        } catch (\Throwable $e) {
            $this->markTestSkipped('SSR pool unreachable (ssr program down): '.$e->getMessage());
        }

        $exitCode = Artisan::call('examples:clusterkit:render', ['--count' => 5]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('rendered', $output);
    }

    public function test_demo_page_is_reachable_without_auth(): void
    {
        $this->get('/clusterkit-demo')->assertOk();
    }

    private function ssrUrl(): string
    {
        return 'http://127.0.0.1:'.env('SSR_PORT', 13715);
    }
}
```

- [ ] **Step 3: Write the module files**

`Modules/ClusterkitExamples/module.json`:

```json
{
    "name": "ClusterkitExamples",
    "alias": "clusterkitexamples",
    "description": "Didactic usage examples for @goopil/clusterkit (Inertia SSR orchestration)",
    "keywords": [],
    "priority": 0,
    "providers": [
        "Modules\\ClusterkitExamples\\Providers\\ClusterkitExamplesServiceProvider"
    ],
    "files": []
}
```

`Modules/ClusterkitExamples/composer.json`:

```json
{
    "name": "nwidart/clusterkitexamples",
    "description": "Didactic usage examples for @goopil/clusterkit: SSR pool renders and the demo page",
    "authors": [
        {
            "name": "Goopil"
        }
    ],
    "extra": {
        "laravel": {
            "providers": [],
            "aliases": {}
        }
    },
    "autoload": {
        "psr-4": {
            "Modules\\ClusterkitExamples\\": "app/"
        }
    }
}
```

`Modules/ClusterkitExamples/app/Providers/ClusterkitExamplesServiceProvider.php`:

```php
<?php

namespace Modules\ClusterkitExamples\Providers;

use Modules\ClusterkitExamples\Console\ExamplesClusterkitRenderCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ClusterkitExamplesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'ClusterkitExamples';

    protected string $nameLower = 'clusterkitexamples';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExamplesClusterkitRenderCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
```

`Modules/ClusterkitExamples/app/Providers/RouteServiceProvider.php` — copy `Modules/FrontLab/app/Providers/RouteServiceProvider.php` verbatim, then change the `protected string $name = 'FrontLab';` line to `protected string $name = 'ClusterkitExamples';`.

`Modules/ClusterkitExamples/routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public on purpose: the SSR render example posts its own payload, and the
// demo page proves the ClusterKit round-trip without a login detour.
Route::get('/clusterkit-demo', fn () => Inertia::render('ClusterkitExamples/Demo'))->name('clusterkit.demo');
```

`Modules/ClusterkitExamples/app/Console/ExamplesClusterkitRenderCommand.php`:

```php
<?php

namespace Modules\ClusterkitExamples\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ExamplesClusterkitRenderCommand extends Command
{
    protected $signature = 'examples:clusterkit:render
                            {--count=10 : Number of renders}
                            {--url= : SSR base URL (default: http://127.0.0.1:{SSR_PORT})}';

    protected $description = 'Example: fire N renders at the ClusterKit SSR pool and report the accounting';

    public function handle(): int
    {
        $base = rtrim($this->option('url') ?: 'http://127.0.0.1:'.env('SSR_PORT', 13715), '/');
        $count = (int) $this->option('count');
        if ($count < 1) {
            $this->error("Invalid --count: {$count}. Must be >= 1");

            return 1;
        }

        // The Demo page calls no route() and needs no auth props — a minimal
        // payload is enough (the app shell's ziggy requirement applies to
        // pages that call route(), see docs/PLAYGROUND.md "SSR roast").
        $payload = [
            'component' => 'ClusterkitExamples/Demo',
            'props' => ['auth' => ['user' => null]],
            'url' => '/clusterkit-demo',
            'version' => 'legacy-dirty',
        ];

        $rendered = 0;
        for ($i = 0; $i < $count; $i++) {
            try {
                $response = Http::timeout(5)->post("{$base}/render", $payload);
            } catch (\Throwable $e) {
                $this->error("SSR pool unreachable at {$base}: {$e->getMessage()}");
                $this->line('Start it: supervisord program `ssr`, or node node/clusterkit-server.mjs');

                return 1;
            }

            $response->successful() ? $rendered++ : $this->line("  render ".($i + 1).": HTTP {$response->status()}");
        }

        $this->info("Rendered {$rendered}/{$count} through the ClusterKit pool at {$base}.");
        $this->line('The usage contract (copy this):');
        $this->line('  Http::post($ssrBase."/render", ["component" => "Module/Page", "props" => [...], ...]);');
        $this->line('Full roast battery (300 renders, kill -9 drills): node/ck-roast.mjs (node/README.md).');

        return $rendered === $count ? 0 : 1;
    }
}
```

`Modules/ClusterkitExamples/resources/js/Pages/Demo.jsx`:

```jsx
import { Head } from '@inertiajs/react';

export default function Demo() {
    const renderedBy = typeof window === 'undefined' ? 'Server (ClusterKit SSR)' : 'Browser (client hydration)';

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-100">
            <Head title="ClusterKit demo" />
            <div className="w-full max-w-lg space-y-3 rounded-lg bg-white p-8 shadow-sm">
                <h1 className="text-xl font-semibold text-gray-900">ClusterKit SSR demo</h1>
                <p className="text-sm text-gray-600">
                    This page is served by the Inertia SSR pool orchestrated by{' '}
                    <code className="rounded bg-gray-100 px-1.5 py-0.5">@goopil/clusterkit</code>. Rendered by:
                </p>
                <p className="text-lg font-semibold text-indigo-600">{renderedBy}</p>
                <p className="text-xs text-gray-400">
                    Fire renders at it: <code>sail artisan examples:clusterkit:render --count=20</code>
                </p>
            </div>
        </div>
    );
}
```

Add `"ClusterkitExamples": true` to `modules_statuses.json`.

- [ ] **Step 4: Build + run tests**

```bash
vendor/bin/sail npm run build && vendor/bin/sail npm run build:ssr
vendor/bin/sail artisan test --filter=ExamplesClusterkitRenderTest --compact
```

Expected: 2 passed (SSR up) or 1 passed + 1 skipped (SSR down). `examples:clusterkit:render` must exit 0 with "Rendered 5/5".

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add Modules/ClusterkitExamples modules_statuses.json tests/Feature/ExamplesClusterkitRenderTest.php
git commit -m "feat(examples): clusterkit render example + public SSR demo page"
```

---

### Task 5: RabbitRsExamples + SentinelExamples READMEs

**Files:**
- Create: `Modules/RabbitRsExamples/README.md`
- Create: `Modules/SentinelExamples/README.md`

**Interfaces:**
- Consumes: command names from Tasks 1-3. Produces: the wiring notes the repo README links later (Task 6).

- [ ] **Step 1: Write `Modules/RabbitRsExamples/README.md`**

```markdown
# RabbitRsExamples — rabbit-rs-laravel usage examples

Didactic examples for [goopil/rabbit-rs-laravel](https://github.com/Goopil/php-rabbit-rs):
each command prints the copyable contract it demonstrates. The probing twin
of this module is [`RabbitRs`](../RabbitRs) (topology setup) and the `*Lab`
modules (findings).

## Commands

| Command | Demonstrates |
|---------|--------------|
| `examples:rabbit-rs:dispatch` | One job class → `default`/`high-priority`/`bulk` via `routing_key = {queue}` |
| `examples:rabbit-rs:delay --seconds=30` | `later(N)` in ttl mode — buckets quantize UP to 1/5/30/120 s |
| `examples:rabbit-rs:fail` | `tries` → retries → failure recording (`/horizon/failed`) |

## Wiring notes (cheatsheet)

- `config/queue.php` — `rabbit-rs` connection: `exchange` = `laravel.jobs`,
  `routing_key` = `{queue}`; subscriptions define the worker side
  (default/high-priority/bulk, weights + prefetch).
- `config/rabbit-rs.php` — cross-cutting: `safety` (blind/unsafe/safe),
  `topology_mode` (external/declare), `delay.mode` (ttl/auto/plugin) +
  `delay.buckets`, `delivery_limit` + `dead_letter`.
- Consumers: Horizon on both `redis-sentinel` and `rabbit-rs`
  (`supervisor-horizon`/`supervisor-bulk`/`supervisor-rabbit` in
  `config/horizon.php`).
- Broker: `make setup` declares the exchange, the 7 quorum queues and the
  `failed-jobs` DLQ pair.

## Where the edge cases live

`docs/PLAYGROUND.md` (known traps) and `docs/upstream-rabbit-rs-laravel.md`
(bug dossier). The test suite pins the contract:
`tests/Feature/UpstreamFindingsTest.php`.
```

- [ ] **Step 2: Write `Modules/SentinelExamples/README.md`**

```markdown
# SentinelExamples — laravel-redis-sentinel usage examples

Didactic examples for
[goopil/laravel-redis-sentinel](https://github.com/Goopil/laravel-redis-sentinel).
The package's own diagnostics ship as `sentinel:status` — these examples show
what an application asks the driver for.

## Commands

| Command | Demonstrates |
|---------|--------------|
| `examples:sentinel:whoami` | `RedisSentinelManager` resolution: connector → sentinel client → master, + role of the resolved connection |
| `examples:sentinel:cache` | Cache round-trip through the sentinel-backed store (read/write split is invisible app-side) |

## Wiring notes (cheatsheet)

- `config/database.php` — `database.redis.default` is sentinel-backed:
  `sentinels` (host/port list), `service` = `mymaster`,
  `read_only_replicas` (read/write splitting).
- `config/phpredis-sentinel.php` — driver knobs: `node_cache.ttl`,
  `retry.*`, `log.*` (failover events land in `storage/logs/sentinel.log`).
- Cache, sessions, Horizon and the `redis-sentinel` queue connection all
  ride the same sentinel set (1 master + 2 replicas + 3 sentinels in
  `compose.yaml`).

## Failover drills

```bash
make sentinel-watch      # terminal 1: +switch-master / +sdown / +odown
make chaos-kill-master   # terminal 2: stop valkey-master
make horizon-probes      # Horizon stays ready/alive through failover
make chaos-heal          # old master rejoins as replica
```

Findings: `docs/upstream-laravel-redis-sentinel.md`.
```

- [ ] **Step 3: Commit**

```bash
git add Modules/RabbitRsExamples/README.md Modules/SentinelExamples/README.md
git commit -m "docs(examples): wiring cheatsheets for the rabbit-rs and sentinel example modules"
```

---

### Task 6: FrontLab `/lab/examples` panel

**Files:**
- Modify: `Modules/FrontLab/routes/web.php` (3 routes)
- Create: `Modules/FrontLab/app/Http/Controllers/ExampleController.php`
- Create: `Modules/FrontLab/resources/js/Pages/Examples.jsx`
- Test: `tests/Feature/LabExamplesTest.php`
- Modify: `README.md` (one row in the Modules table for the three new modules + a `/lab/examples` line)

**Interfaces:**
- Consumes: `ProcessOrderJob`, `DelayedReportJob`, `FlakyJob` (Task 1-2), `SentinelWhoami::captureOrError()` (Task 3), route names `lab.examples`, `lab.examples.rabbit-rs`, `lab.examples.sentinel`.
- Produces: routes as listed; Inertia page `FrontLab/Examples`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LabExamplesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\RabbitRsExamples\Jobs\DelayedReportJob;
use Modules\RabbitRsExamples\Jobs\FlakyJob;
use Modules\RabbitRsExamples\Jobs\ProcessOrderJob;
use Tests\TestCase;

class LabExamplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/lab/examples')->assertRedirect(route('login'));
    }

    public function test_examples_page_renders_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/lab/examples')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('FrontLab/Examples'));
    }

    public function test_rabbit_rs_endpoint_dispatches_the_selected_example(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/lab/examples/rabbit-rs', ['example' => 'dispatch', 'count' => 3])
            ->assertRedirect();

        Queue::assertPushed(ProcessOrderJob::class, 3);

        $this->actingAs($user)->post('/lab/examples/rabbit-rs', ['example' => 'delay', 'count' => 1]);
        Queue::assertPushed(DelayedReportJob::class, 1);

        $this->actingAs($user)->post('/lab/examples/rabbit-rs', ['example' => 'fail', 'count' => 1]);
        Queue::assertPushed(FlakyJob::class, 1);
    }

    public function test_rabbit_rs_endpoint_validates_the_example_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/lab/examples/rabbit-rs', ['example' => 'nope'])
            ->assertSessionHasErrors('example');
    }

    public function test_sentinel_endpoint_returns_the_snapshot_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/lab/examples/sentinel');

        $response->assertOk()->assertJsonStructure(['service', 'master', 'role', 'connection', 'ok']);
    }
}
```

- [ ] **Step 2: Run tests — verify they fail**

Run: `vendor/bin/sail artisan test --filter=LabExamplesTest --compact`
Expected: FAIL (404s — routes don't exist yet).

- [ ] **Step 3: Write the routes and controller**

Append to `Modules/FrontLab/routes/web.php` (inside the existing `Route::middleware(['auth', 'verified'])->group`):

```php
    Route::get('/lab/examples', [ExampleController::class, 'index'])->name('lab.examples');
    Route::post('/lab/examples/rabbit-rs', [ExampleController::class, 'rabbitRs'])->name('lab.examples.rabbit-rs');
    Route::get('/lab/examples/sentinel', [ExampleController::class, 'sentinel'])->name('lab.examples.sentinel');
```

Add to the imports at the top of the file: `use Modules\FrontLab\Http\Controllers\ExampleController;`.

`Modules/FrontLab/app/Http/Controllers/ExampleController.php`:

```php
<?php

namespace Modules\FrontLab\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\RabbitRsExamples\Jobs\DelayedReportJob;
use Modules\RabbitRsExamples\Jobs\FlakyJob;
use Modules\RabbitRsExamples\Jobs\ProcessOrderJob;
use Modules\SentinelExamples\Support\SentinelWhoami;

class ExampleController extends Controller
{
    public function index()
    {
        return inertia('FrontLab/Examples', [
            'sentinel' => SentinelWhoami::captureOrError(),
        ]);
    }

    public function rabbitRs(Request $request)
    {
        $validated = $request->validate([
            'example' => 'required|in:dispatch,delay,fail',
            'count' => 'required|integer|min:1|max:50',
        ]);

        $watch = match ($validated['example']) {
            'dispatch' => $this->dispatchMany($validated['count']),
            'delay' => $this->dispatchDelayed($validated['count']),
            'fail' => $this->dispatchFailing($validated['count']),
        };

        return back()->with('success', $watch);
    }

    public function sentinel()
    {
        // Same contract as DashboardController::rabbitDepth: unreachable
        // infra degrades to an ok=false payload, never a 500.
        return response()->json(SentinelWhoami::captureOrError() + ['ok' => true]);
    }

    private function dispatchMany(int $count): string
    {
        foreach (['default', 'high-priority', 'bulk'] as $queue) {
            for ($i = 0; $i < $count; $i++) {
                ProcessOrderJob::dispatch(['order' => uniqid('order-')])->onQueue($queue);
            }
        }

        return "Dispatched {$count} order(s) per queue — watch /horizon (Recent) or /lab.";
    }

    private function dispatchDelayed(int $count): string
    {
        for ($i = 0; $i < $count; $i++) {
            DelayedReportJob::dispatch(['report' => uniqid('report-')])
                ->delay(30)
                ->onConnection('rabbit-rs')
                ->onQueue('default');
        }

        return "Delayed {$count} report(s) by 30s (ttl bucket) — watch rabbit-rs.delay.* in the RabbitMQ UI.";
    }

    private function dispatchFailing(int $count): string
    {
        for ($i = 0; $i < $count; $i++) {
            FlakyJob::dispatch(uniqid('order-'))->onConnection('rabbit-rs')->onQueue('bulk');
        }

        return "Dispatched {$count} FlakyJob(s) (tries=3) — watch /horizon/failed.";
    }
}
```

- [ ] **Step 4: Write the Examples.jsx page**

`Modules/FrontLab/resources/js/Pages/Examples.jsx` (same styling vocabulary as Dashboard.jsx — StatCard/white-card Tailwind classes):

```jsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

function Card({ title, subtitle, children }) {
    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div className="border-b border-gray-200 p-6">
                <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
                <p className="mt-1 text-xs text-gray-500">{subtitle}</p>
            </div>
            <div className="space-y-4 p-6">{children}</div>
        </div>
    );
}

export default function Examples() {
    const { success, sentinel } = usePage().props;
    const [example, setExample] = useState('dispatch');
    const [count, setCount] = useState(3);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');

    const run = (e) => {
        e.preventDefault();
        setError('');
        setProcessing(true);
        router.post(route('lab.examples.rabbit-rs'), { example, count }, {
            onFinish: () => setProcessing(false),
            onError: () => setError('Dispatch failed.'),
        });
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Usage examples</h2>}
        >
            <Head title="Examples" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {success && (
                        <div className="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{success}</div>
                    )}

                    <Card
                        title="rabbit-rs — routing, delays, failures"
                        subtitle="Dispatches the RabbitRsExamples job classes; each prints its contract via the CLI examples too."
                    >
                        <form onSubmit={run} className="flex flex-wrap items-end gap-4">
                            <div>
                                <InputLabel htmlFor="example" value="Example" />
                                <select
                                    id="example"
                                    className="mt-1 block rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    value={example}
                                    onChange={(e) => setExample(e.target.value)}
                                >
                                    <option value="dispatch">dispatch — routing_key = {`{queue}`}</option>
                                    <option value="delay">delay — later(30), ttl bucket</option>
                                    <option value="fail">fail — tries → failed-jobs</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="count" value="Count" />
                                <input
                                    id="count"
                                    type="number"
                                    min="1"
                                    max="50"
                                    className="mt-1 block w-24 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    value={count}
                                    onChange={(e) => setCount(e.target.value)}
                                />
                            </div>
                            <PrimaryButton disabled={processing}>Run</PrimaryButton>
                            <InputError message={error} className="ms-2" />
                        </form>
                    </Card>

                    <Card
                        title="redis-sentinel — what this process resolved"
                        subtitle="Snapshot from SentinelWhoami (RabbitRs examples live in /horizon; the full status ships as sail artisan sentinel:status)."
                    >
                        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div><dt className="text-gray-500">service</dt><dd className="font-semibold">{sentinel?.service ?? '–'}</dd></div>
                            <div><dt className="text-gray-500">master</dt><dd className="font-semibold">{sentinel?.master ?? '–'}</dd></div>
                            <div><dt className="text-gray-500">role</dt><dd className="font-semibold">{sentinel?.role ?? '–'}</dd></div>
                            <div><dt className="text-gray-500">connection</dt><dd className="font-semibold">{sentinel?.connection ?? '–'}</dd></div>
                        </dl>
                        <div className="flex gap-3 text-xs text-gray-500">
                            <span>Failover drill:</span>
                            <code>make sentinel-watch</code>
                            <code>make chaos-kill-master</code>
                            <code>make chaos-heal</code>
                        </div>
                    </Card>

                    <Card
                        title="clusterkit — Inertia SSR pool"
                        subtitle="The demo page renders through the SSR server; fire renders at it from the CLI."
                    >
                        <div className="flex flex-wrap items-center gap-4 text-sm">
                            <a href="/clusterkit-demo" target="_blank" rel="noreferrer"
                                className="font-semibold text-indigo-600 hover:text-indigo-500">
                                Open /clusterkit-demo →
                            </a>
                            <code className="rounded bg-gray-100 px-2 py-1 text-xs">
                                sail artisan examples:clusterkit:render --count=20
                            </code>
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 5: Update README.md**

In the Modules table (README.md), add one row after the `LifecycleLab` row:

```markdown
| `RabbitRsExamples` / `SentinelExamples` / `ClusterkitExamples` | Didactic usage examples per lib — `examples:*` commands + module READMEs (wiring cheatsheets) |
```

In the section "Dual Dispatch" after the dispatch-panel line, add:

```markdown
Usage examples: `/lab/examples` dispatches the example jobs (routing, delay, failure path) and shows the live sentinel snapshot.
```

- [ ] **Step 6: Build + run the full verification**

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact
```

Expected: full suite green (existing 70 + new 9; skips allowed only for the documented SSR/broker guards).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add Modules/FrontLab tests/Feature/LabExamplesTest.php README.md
git commit -m "feat(frontlab): /lab/examples panel — run the example jobs, live sentinel snapshot"
```

---

## Self-review notes

- Spec coverage: 3 example modules (Tasks 1-4), READMEs per module (Tasks 3, 5), `/lab/examples` panel (Task 6), error-handling contract (graceful exit 1 + `ok:false` JSON) covered in Tasks 3-4, 6. Testing section of the spec maps to the per-task test files.
- Deliberate deviation from the spec, recorded: `examples:sentinel:topology` became `examples:sentinel:whoami` — the package already ships a `sentinel:status` diagnostic command; the example demonstrates the manager API a user app would copy instead of duplicating the diagnostic.
- Type consistency: `SentinelWhoami::captureOrError(): array` defined in Task 3, consumed in Task 6; job class names consistent across Tasks 1, 2, 6.
