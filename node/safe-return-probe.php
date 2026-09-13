<?php

use Goopil\RabbitRs\Laravel\Horizon\RabbitMqQueue;
use Illuminate\Contracts\Console\Kernel;
use Modules\QueueLab\Jobs\StressJob;

// Safe-mode basic.return probe: publish an unroutable message, let the
// age-flush deliver it, then check where the outcome surfaces.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config()->set('rabbit-rs.safety', $argv[1] ?? 'safe');

/** @var RabbitMqQueue $q */
$q = Queue::connection('rabbit-rs-work');
$q->clear('work');
$q->size('work');

echo "publish #1 (unroutable, safe mode)\n";
$q->push(new StressJob(1), '', 'work');

echo "sleeping 6s for the age-flush + broker return...\n";
sleep(6);

echo 'next operation (size): ';
try {
    $s = $q->size('work');
    echo "returned $s — NO exception\n";
} catch (Throwable $e) {
    echo 'THREW '.get_class($e).': '.substr($e->getMessage(), 0, 110)."\n";
}

echo 'explicit drainSettlementErrors: ';
try {
    $q->drainSettlementErrors();
    echo "no error pending\n";
} catch (Throwable $e) {
    echo 'THREW '.get_class($e).': '.substr($e->getMessage(), 0, 110)."\n";
}

$job = $q->pop('work');
if ($job === null) {
    echo "work empty — message LOST\n";
} else {
    $payload = json_decode($job->getRawBody(), true);
    echo 'message RE-ROUTED into work: displayName='.($payload['displayName'] ?? '?')."\n";
    $job->delete();
}
try {
    $q->closeConsumers();
} catch (Throwable $e) {
}
$q->clear('work');
