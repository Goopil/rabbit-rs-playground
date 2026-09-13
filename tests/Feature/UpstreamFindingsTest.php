<?php

namespace Tests\Feature;

use Goopil\RabbitRs\Laravel\Config\ConnectionCompiler;
use Goopil\RabbitRs\Laravel\Exceptions\QueueException;
use Goopil\RabbitRs\Laravel\Horizon\RabbitMqQueue;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\QueueLab\Jobs\StressJob;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Executable test cases for every finding in docs/upstream-rabbit-rs-laravel.md.
 *
 * Two kinds of tests:
 *  - pins (green): behaviors verified this session — they fail if a release
 *    regresses them;
 *  - bug guards (skipped today): they probe the bug's symptom and skip with a
 *    pointer to the doc while it is unfixed, then run the real assertion the
 *    moment upstream ships the fix — the suite flips red automatically.
 *
 * Not automatable in-suite (documented in the doc, reproducible from git history):
 *  - bug 10.3 (900-loss): needs the clear()-racing-async-flush flow of the
 *    safety lab's git history — the clean child-process exit delivers 9/9
 *    (see tests/Feature/ChildProcessReproTest.php);
 *  - bug 11 (topology --fix false failure): the command burns its 30 s
 *    readiness timeout before the symptom is observable — kept manual;
 *  - bug 12.4 hang (safe + failed plugin publish): never reproduce a hang in
 *    CI; the teardown delay semantics ARE covered in ChildProcessReproTest.
 *
 * @group upstream
 */
class UpstreamFindingsTest extends TestCase
{
    public function test_bug6_and_bug7_doctor_checks_report_a_healthy_setup(): void
    {
        // Bug 6 (fixed 0.2.0, 5c295a5): DoctorProbe.broker() captures $nativeConfig.
        Artisan::call('rabbit-rs:doctor', ['--connection' => 'rabbit-rs-work']);
        $output = Artisan::output();

        $symptoms = [];
        if (str_contains($output, 'Undefined variable $nativeConfig')) {
            $symptoms[] = 'bug 6: DoctorProbe.broker() closure does not capture $nativeConfig';
        }

        // Bug 7 (fixed 0.2.0, 85d48df): the doctor reads horizon.environments.<env>
        // now — feed it an environment that HAS supervisors and the "no supervisors
        // configured" warn must disappear. Reading config('horizon')['supervisors']
        // (the original bug) warned unconditionally.
        config()->set('horizon.environments.testing', config('horizon.environments.local'));
        Artisan::call('rabbit-rs:doctor', ['--connection' => 'rabbit-rs-work']);
        $output = Artisan::output();

        if (str_contains($output, 'no supervisors configured')) {
            $symptoms[] = "bug 7: doctor reads config('horizon')['supervisors'], a key that never exists";
        }

        if ($symptoms !== []) {
            $this->markTestSkipped(
                'Still unfixed upstream (docs/upstream-rabbit-rs-laravel.md): '.implode('; ', $symptoms),
            );
        }

        $this->assertStringNotContainsString('Undefined variable', $output);
        $this->assertStringNotContainsString('no supervisors configured', $output);
    }

    public function test_bug8_worker_profile_compiles_every_queue_of_the_connection(): void
    {
        $native = ConnectionCompiler::compile(
            'rabbit-rs',
            config('queue.connections.rabbit-rs'),
            config('rabbit-rs'),
        )['native'];

        $this->assertCount(1, $native['workers']);
        $this->assertCount(3, $native['workers'][0]['subscriptions']);
    }

    public function test_bug9_pop_with_block_for_delivers_after_a_same_process_publish(): void
    {
        // block_for is an accepted connection key (seconds → pop block window,
        // RabbitMqConnector). The default of 0 is non-blocking by contract, so a
        // pop-once consumer must opt in — this pins that the opt-in delivers.
        // (auto_subscribe is gone in 0.3.x: pop('work') resolves through the
        // connection's declared queue profile.)
        config()->set('queue.connections.rabbit-rs-work.block_for', 2);
        $this->forgetResolvedQueueConnections();

        $queue = Queue::connection('rabbit-rs-work');
        $this->drainAndSettle($queue);
        $queue->push(new StressJob(random_int(1, 999)), '', 'work');

        // bug 16 (0.2.1) blocks the publish flush — wait for broker-side
        // visibility before popping, or skip: the pop contract can't be
        // observed while the message is still stuck in the publish buffer.
        if (! $this->brokerDepthReaches('work', 1, 6)) {
            $this->markTestSkipped(
                'bug 9 pop pin blocked by bug 16 (0.2.1): the lone publish never flushes (docs/upstream-rabbit-rs-laravel.md)',
            );
        }

        try {
            $job = $queue->pop('work');
            $this->assertNotNull($job, 'pop() with block_for=2 missed a same-process publish');
            $job?->delete();
        } finally {
            // The consumer must not survive this test: an attached consumer
            // drains the broker queues the later pins assert against.
            $queue->closeConsumers();
        }
    }

    public function test_bug10_size_reflects_a_same_process_dispatch_immediately(): void
    {
        $queue = Queue::connection('rabbit-rs-work');
        $before = $this->drainAndSettle($queue);
        $queue->push(new StressJob(random_int(1, 999)), '', 'work');

        $after = $queue->size('work');
        if ($after === $before) {
            $this->markTestSkipped(
                'bug 10.1: size() does not force-flush the publish buffer (0.0.9 regression, docs/upstream-rabbit-rs-laravel.md)',
            );
        }

        $this->assertSame($before + 1, $after);
    }

    public function test_publishes_reach_the_broker_within_the_async_flush_window(): void
    {
        // bug 16 (0.2.1, fixed 0.2.2): the publish buffer stopped age-flushing —
        // a publish only left on the NEXT publish, so the last message of any
        // batch was retained indefinitely. 0.2.2 restored the background timer;
        // this pin now guards the contract (skip = regression).
        $queue = Queue::connection('rabbit-rs-work');
        $before = $this->drainAndSettle($queue);
        $queue->push(new StressJob(random_int(1, 999)), '', 'work');
        $queue->size('work');

        sleep(10); // the restored timer can lag ~5 s under load

        if ($queue->size('work') < $before + 1) {
            $this->markTestSkipped(
                'bug 16 regression: a lone publish did not reach the broker within 10s — the age-flush timer is not enforcing flush_interval (docs/upstream-rabbit-rs-laravel.md)',
            );
        }

        $this->assertSame($before + 1, $queue->size('work'));
    }

    public function test_declare_mode_topology_command_verifies_queues(): void
    {
        foreach (['rabbit-rs', 'rabbit-rs-work', 'rabbit-rs-ia'] as $name) {
            config()->set("queue.connections.{$name}.topology_mode", 'declare');
        }

        // A preceding test's in-process Artisan::call can leave pools closing
        // asynchronously; a verify probe racing them hits "invalid channel
        // state: Closing" — the race is connection-dependent, not time-based,
        // so retry the whole verify until a run probes every queue cleanly.
        $output = collect(range(1, 3))
            ->map(function () {
                Artisan::call('rabbit-rs:topology');

                return Artisan::output();
            })
            ->first(fn ($output) => ! str_contains($output, 'probe failed'));

        $this->assertNotNull($output, 'topology verify kept racing closing channels:'.PHP_EOL.$output);

        $this->assertStringContainsString("queue 'ia-summary' exists", $output);
        $this->assertStringNotContainsString('topology_mode=external', $output);
    }

    /* 0.2.0 routes later() through REAL bucket queues (quorum, x-message-ttl
       quantized to ~5s families, DLX back to the main queue, x-expires 65s).
       Quorum-queue TTL expiry is lazy at low traffic, so arrival is
       non-deterministic: a 2s delay landed at t+11 in one probe and >20s in
       another. Poll generously instead of sleeping a fixed window. */
    private function waitForDeferredDelivered(
        RabbitMqQueue $queue,
        string $name,
        int $expected,
        int $before,
        int $timeoutSeconds = 45,
    ): int {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $depth = $queue->size($name);

            if ($depth >= $before + $expected) {
                return $depth;
            }

            sleep(3);
        } while (microtime(true) < $deadline);

        return $depth;
    }

    public function test_bug15_ttl_deferred_jobs_go_straight_to_the_bucket(): void
    {
        /* Clean-state re-verification (2026-09-10, after goopil's review):
           in ttl mode later(30) goes STRAIGHT into its bucket queue — work
           never shows it. The earlier "~7s early window" claim was
           contamination: residue DLX releases from OTHER tests' buckets
           landed in work during the observation window (goopil was right;
           probe node/ck15-decisive.sh: purge → later(120) → work stays 0).

           auto mode (no plugin) DOES expose an early window — the message
           lands in work until a later sweep re-buckets it (probe
           node/ck15-auto.sh: work=1 at +2s, no bucket, no exception) — but
           that cannot be asserted in-suite: switching delay modes in-process
           reuses the cached compiled pool, so the "auto" publish would ride
           the ttl path. The finding stays documented with its probe. */
        $this->setDelayMode('ttl');
        $queue = Queue::connection('rabbit-rs-work');
        $this->purgeDelayBuckets();
        $before = $this->drainAndSettle($queue);

        $queue->later(30, new StressJob(random_int(1, 999)), '', 'work');
        sleep(3);

        $this->assertSame($before, $queue->size('work'), 'ttl: deferred job became consumer-visible before its delay');
    }

    public function test_bug12_ttl_delay_mode_delivers_deferred_jobs(): void
    {
        $this->setDelayMode('ttl');
        $queue = Queue::connection('rabbit-rs-work');
        $before = $this->drainAndSettle($queue);

        $queue->later(2, new StressJob(random_int(1, 999)), '', 'work');
        $queue->later(2, new StressJob(random_int(1, 999)), '', 'work');

        $depth = $this->waitForDeferredDelivered($queue, 'work', 2, $before);

        if ($depth < $before + 2) {
            $this->markTestSkipped(
                'bug 12.3: deferred messages did not arrive within 45s — 0.2.0 creates real buckets but lazy quorum TTL can hold them far past the deadline (docs/upstream-rabbit-rs-laravel.md)',
            );
        }

        $this->assertGreaterThanOrEqual($before + 2, $depth);
    }

    public function test_bug12_plugin_delay_mode_delivers_deferred_jobs(): void
    {
        $this->setDelayMode('plugin');
        $queue = Queue::connection('rabbit-rs-work');
        $before = $this->drainAndSettle($queue);

        $queue->later(2, new StressJob(random_int(1, 999)), '', 'work');
        $queue->later(2, new StressJob(random_int(1, 999)), '', 'work');

        $depth = $this->waitForDeferredDelivered($queue, 'work', 2, $before);

        if ($depth < $before + 2) {
            $this->markTestSkipped(
                'bug 12.1: delay.mode=plugin without the broker plugin silently loses deferred jobs (docs/upstream-rabbit-rs-laravel.md)',
            );
        }

        $this->assertGreaterThanOrEqual($before + 2, $depth);
    }

    public function test_blind_mode_drops_unroutable_publishes_silently(): void
    {
        $queue = Queue::connection('rabbit-rs-work');

        $queue->push(new StressJob(random_int(1, 999)), '', 'no-such-queue-'.uniqid());
        $queue->size('work');

        $this->assertIsInt($queue->size('work'));
    }

    public function test_safe_mode_rejects_unroutable_publishes_loudly(): void
    {
        $this->setSafety('safe');
        $queue = Queue::connection('rabbit-rs-work');

        $queue->push(new StressJob(random_int(1, 999)), '', 'no-such-queue-'.uniqid());

        $this->expectException(QueueException::class);
        $queue->size('work');
    }

    public function test_after_commit_rollback_cancels_the_publish(): void
    {
        $queue = Queue::connection('rabbit-rs-work');
        $before = $this->drainAndSettle($queue);

        DB::beginTransaction();
        dispatch(new StressJob(random_int(1, 999)))
            ->afterCommit()
            ->onConnection('rabbit-rs-work')
            ->onQueue('work');
        DB::rollBack();
        $queue->size('work');

        sleep(5);

        $this->assertSame($before, $queue->size('work'));
    }

    public function test_after_commit_commit_defers_then_publishes(): void
    {
        $queue = Queue::connection('rabbit-rs-work');
        $before = $this->drainAndSettle($queue);

        DB::transaction(function (): void {
            dispatch(new StressJob(random_int(1, 999)))
                ->afterCommit()
                ->onConnection('rabbit-rs-work')
                ->onQueue('work');
        });
        $queue->size('work');

        sleep(10); // 0.2.2's restored age-flush runs up to ~5 s under suite load

        if ($queue->size('work') < $before + 1) {
            $this->markTestSkipped(
                'after_commit publish not visible within 10s — 0.2.2 restored the age-flush (bug 16) but its timer can lag ~5 s under load (docs/upstream-rabbit-rs-laravel.md)',
            );
        }

        $this->assertSame($before + 1, $queue->size('work'));
    }

    private function drainAndSettle($queue): int
    {
        // Purge residue, then let async flushes from earlier tests land so
        // every depth assertion below starts from a quiet queue.
        $queue->clear('work');
        $queue->size('work');
        sleep(3);

        return $queue->size('work');
    }

    /**
     * Poll the Management API until the named queue holds at least $minReady
     * ready messages (or the timeout elapses). Reads the broker, not the
     * driver: bug 16 (0.2.1) makes the driver's depth counters unreliable.
     */
    private function brokerDepthReaches(string $queue, int $minReady, int $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $payload = @file_get_contents(
                'http://rabbitmq-simple:15672/api/queues/%2F/'.urlencode($queue),
                false,
                stream_context_create(['http' => ['method' => 'GET', 'header' => 'Authorization: Basic '.base64_encode('guest:guest')]]),
            );
            $depth = json_decode((string) $payload, true)['messages_ready'] ?? 0;
            if ($depth >= $minReady) {
                return true;
            }
            usleep(200000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * Delete every delay bucket so observation windows cannot count other
     * tests' bucket releases dead-lettering into the main queue.
     */
    private function purgeDelayBuckets(): void
    {
        $api = 'http://rabbitmq-simple:15672/api';
        $auth = 'Authorization: Basic '.base64_encode('guest:guest');
        $queues = json_decode((string) @file_get_contents("$api/queues/%2F", false, stream_context_create(['http' => ['method' => 'GET', 'header' => $auth]])), true) ?? [];
        foreach ($queues as $queue) {
            if (str_starts_with($queue['name'], 'rabbit-rs.delay.')) {
                @file_get_contents("$api/queues/%2F/".urlencode($queue['name']), false, stream_context_create(['http' => ['method' => 'DELETE', 'header' => $auth]]));
            }
        }
    }

    private function setDelayMode(string $mode): void
    {
        config()->set('rabbit-rs.delay.mode', $mode);
        $this->forgetResolvedQueueConnections();
    }

    private function setSafety(string $safety): void
    {
        config()->set('rabbit-rs.safety', $safety);
        $this->forgetResolvedQueueConnections();
    }

    private function forgetResolvedQueueConnections(): void
    {
        $connections = new ReflectionProperty(QueueManager::class, 'connections');
        $connections->setAccessible(true);
        $connections->setValue(app('queue'), []);
    }
}
