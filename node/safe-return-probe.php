<?php

// Safe-mode basic.return probe: publish an unroutable message, let the
// age-flush deliver it, then check where the outcome surfaces.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config()->set('rabbit-rs.safety', $argv[1] ?? 'safe');

/** @var Goopil\RabbitRs\Laravel\Horizon\RabbitMqQueue $q */
$q = Queue::connection('rabbit-rs-work');
$q->clear('work');
$q->size('work');

$depth = function (string $name): int {
    $ctx = stream_context_create(['http' => ['header' => 'Authorization: Basic '.base64_encode('guest:guest'), 'ignore_errors' => true]]);
    $d = json_decode((string) @file_get_contents('http://rabbitmq-simple:15672/api/queues/%2F/'.$name, false, $ctx), true) ?? [];

    return (int) ($d['messages_ready'] ?? 0);
};

echo 'publish #1 ('.($argv[1] ?? 'safe').", binding present)\n";
$q->push(new Modules\QueueLab\Jobs\StressJob(1), '', 'work');

echo "sleeping 6s for the age-flush...\n";
sleep(6);

printf("broker depth after flush: %d\n", $depth('work'));

echo "next operation (size): ";
try {
    $s = $q->size('work');
    echo "returned $s — NO exception\n";
} catch (Throwable $e) {
    echo 'THREW '.get_class($e).': '.substr($e->getMessage(), 0, 110)."\n";
}

echo "explicit drainSettlementErrors: ";
try {
    $q->drainSettlementErrors();
    echo "no error pending\n";
} catch (Throwable $e) {
    echo 'THREW '.get_class($e).': '.substr($e->getMessage(), 0, 110)."\n";
}

$job = $q->pop('work');
if ($job === null) {
    echo "pop: null — driver can't see the message\n";
} else {
    $payload = json_decode($job->getRawBody(), true);
    echo "popped: displayName=".($payload['displayName'] ?? '?')."\n";
    $job->delete();
}
try { $q->closeConsumers(); } catch (Throwable $e) {}
$q->clear('work');
