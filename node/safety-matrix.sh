#!/bin/sh
# Safety-mode contract matrix: {safe, unsafe, blind} x {A..F} — each run in a
# FRESH tinker process (mode read from env at boot, no in-process switching).
# A lone-publish latency, B push+pop, C size() sanity, D clear-race,
# E child-exit deferred teardown, F unroutable publish (binding removed).

cd /var/www/html
API=http://guest:guest@rabbitmq-simple:15672/api

api() { php -r "echo @file_get_contents('$API$1', false, stream_context_create(['http' => ['method' => '$2', 'header' => 'Authorization: Basic ' . base64_encode('guest:guest'), 'content' => '{}']])) ?: '';"; }

depth() {
    php -r "\$d = json_decode(@file_get_contents('$API/queues/%2F/$1', false, stream_context_create(['http' => ['header' => 'Authorization: Basic ' . base64_encode('guest:guest')]])), true) ?? []; echo (int) (\$d['messages_ready'] ?? 0);"
}

buckets() {
    php -r "\$n = 0; foreach (json_decode(@file_get_contents('$API/queues/%2F', false, stream_context_create(['http' => ['header' => 'Authorization: Basic ' . base64_encode('guest:guest')]])), true) ?? [] as \$q) { if (str_starts_with(\$q['name'], 'rabbit-rs.delay.')) \$n++; } echo \$n;"
}

cleanup() {
    php -r "
\$api = '$API';
\$ctx = ['http' => ['method' => 'GET', 'header' => 'Authorization: Basic ' . base64_encode('guest:guest')]];
foreach (json_decode(@file_get_contents(\"\$api/queues/%2F\", false, stream_context_create(\$ctx)), true) ?? [] as \$q) {
    if (str_starts_with(\$q['name'], 'rabbit-rs.delay.')) {
        @file_get_contents(\"\$api/queues/%2F/\" . urlencode(\$q['name']), false, stream_context_create(['http' => ['method' => 'DELETE', 'header' => 'Authorization: Basic ' . base64_encode('guest:guest')]]));
    }
}
echo \"buckets cleaned\n\";
"
    php artisan tinker --execute 'Queue::connection("rabbit-rs-work")->clear("work"); Queue::connection("rabbit-rs-work")->size("work");' 2>/dev/null
    sleep 1
}

run_test() {
    MODE=$1
    TEST=$2
    echo "=== [$MODE] $TEST ==="
    RABBIT_RS_SAFETY=$MODE SAFETY_TEST=$TEST php artisan tinker --execute '
$m = env("RABBIT_RS_SAFETY");
$test = env("SAFETY_TEST");
$q = Queue::connection("rabbit-rs-work");
$depth = function (string $name): int {
    $d = json_decode((string) @file_get_contents("http://guest:guest@rabbitmq-simple:15672/api/queues/%2F/" . $name), true) ?? [];
    return (int) ($d["messages_ready"] ?? 0);
};
$waitDepth = function (string $name, int $min, int $secs) use ($depth): ?float {
    $t0 = microtime(true);
    do {
        if ($depth($name) >= $min) {
            return microtime(true) - $t0;
        }
        usleep(100000);
    } while (microtime(true) - $t0 < $secs);
    return null;
};
switch ($test) {
    case "a":
        sleep(2);
        $t0 = microtime(true);
        $q->push(new Modules\QueueLab\Jobs\StressJob(1), "", "work");
        $s = $waitDepth("work", 1, 10);
        printf("A lone publish: %s\n", $s === null ? "NOT DELIVERED in 10s" : sprintf("visible in %.2fs", $s));
        break;
    case "b":
        sleep(2);
        $q->push(new Modules\QueueLab\Jobs\StressJob(2), "", "work");
        $s = $waitDepth("work", 1, 10);
        if ($s === null) { echo "B push never visible — pop untestable\n"; break; }
        $job = $q->pop("work");
        printf("B pop after visible publish: %s\n", $job ? "GOT" : "null (MISSED)");
        try { $job?->delete(); $q->closeConsumers(); } catch (Throwable $e) {}
        break;
    case "c":
        sleep(2);
        $q->push(new Modules\QueueLab\Jobs\StressJob(3), "", "work");
        $waitDepth("work", 1, 10);
        printf("C size()=%d while broker holds 1\n", $q->size("work"));
        break;
    case "d":
        sleep(2);
        $q->push(new Modules\QueueLab\Jobs\StressJob(4), "", "work");
        echo "D pushed after clear(), process exits now\n";
        break;
    case "e":
        sleep(2);
        $q->later(10, new Modules\QueueLab\Jobs\StressJob(5), "", "work");
        echo "E deferred(10s) pushed, process exits now\n";
        break;
    case "f":
        try {
            $q->push(new Modules\QueueLab\Jobs\StressJob(6), "", "work");
            echo "F unroutable publish: NO exception\n";
        } catch (Throwable $e) {
            echo "F unroutable publish THREW: " . get_class($e) . ": " . substr($e->getMessage(), 0, 110) . "\n";
        }
        break;
}
' 2>&1 | grep -vE "Warning|Deprecated|^$" | head -3

    sleep 3
    case "$TEST" in
        d) D=$(depth work); echo "D after exit: broker ready=$D $( [ "$D" -ge 1 ] && echo '→ delivered (no clear-race loss)' || echo '→ LOST (clear-race)')" ;;
        e) D=$(depth work); B=$(buckets); echo "E after exit: work=$D buckets=$B $( [ "$B" -ge 1 ] && echo '→ routed to bucket (correct)' || ( [ "$D" -ge 1 ] && echo '→ PUBLISHED EARLY in work (teardown break)' ) || echo '→ lost')" ;;
    esac
    cleanup
}

if [ "$#" -eq 2 ]; then
    run_test "$1" "$2"
    exit 0
fi

for MODE in safe unsafe blind; do
    for TEST in a b c d e; do
        run_test "$MODE" "$TEST"
    done
done

    # F: remove the publish binding (unroutable), run per mode, restore
    api "/bindings/%2F/e/laravel.jobs/q/work/work" DELETE >/dev/null
    echo "=== publish binding removed (unroutable scenario) ==="
    for MODE in safe unsafe blind; do
        run_test "$MODE" f
    done
    api "/bindings/%2F/e/laravel.jobs/q/work" POST >/dev/null
    echo "binding restored"
    echo "work ready after restore: $(depth work)"
