#!/bin/sh
# Auto-mode delay probe, clean state: delete buckets + purge work, ONE later(30)
# in auto mode (plugin NOT installed). Where does the message go?
php -r '
$h = fn ($m, $p, $b = null) => ($c = curl_init("http://guest:guest@rabbitmq-simple:15672/api{$p}")) && curl_setopt_array($c, [CURLOPT_CUSTOMREQUEST => $m, CURLOPT_POSTFIELDS => json_encode($b ?? new stdClass()), CURLOPT_RETURNTRANSFER => true]) && curl_exec($c);
foreach (json_decode(file_get_contents("http://guest:guest@rabbitmq-simple:15672/api/queues/%2F"), true) as $q) {
    if (str_starts_with($q["name"], "rabbit-rs.delay.")) { $h("DELETE", "/queues/%2F/" . urlencode($q["name"])); echo "deleted bucket {$q["name"]}\n"; }
}
$c = curl_init("http://guest:guest@rabbitmq-simple:15672/api/queues/%2F/work/purge");
curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => "{}", CURLOPT_RETURNTRANSFER => true]);
curl_exec($c);
echo "work purged\n";
'
sleep 2
php /var/www/html/artisan tinker --execute '
config()->set("rabbit-rs.delay.mode", "auto");
try {
    Queue::connection("rabbit-rs-work")->later(30, new Modules\QueueLab\Jobs\StressJob(9), "", "work");
    echo "auto later(30): published without exception\n";
} catch (Throwable $e) {
    echo "auto later(30) THREW: " . get_class($e) . ": " . substr($e->getMessage(), 0, 120) . "\n";
}
sleep(2);
foreach (json_decode(file_get_contents("http://guest:guest@rabbitmq-simple:15672/api/queues/%2F"), true) as $q) {
    $m = $q["messages"] ?? 0;
    if ($m > 0 || str_contains($q["name"], "delay") || $q["name"] === "work") printf("%-58s msgs=%d ready=%d\n", $q["name"], $m, $q["messages_ready"] ?? 0);
}
'
