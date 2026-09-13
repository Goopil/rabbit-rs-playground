#!/bin/sh
# Bug 15.1 re-verification, clean state: delete all delay buckets + purge work,
# publish ONE later(30) in ttl mode, timestamped trace of work + buckets.
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
node node/ck15probe.mjs &
OBS=$!
sleep 1
php /var/www/html/artisan tinker --execute 'Queue::connection("rabbit-rs-work")->later(30, new Modules\QueueLab\Jobs\StressJob(30), "", "work"); echo "published later(30)\n";'
wait $OBS
