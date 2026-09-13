#!/bin/sh
# Decisive 15.1 probe: purge work, publish later(120) IMMEDIATELY (no residue
# window), trace work + buckets. If goopil is right (straight-to-bucket), work
# must stay 0 for the whole window and the bucket must hold the message.
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
node node/ck15probe.mjs &
OBS=$!
php /var/www/html/artisan tinker --execute 'Queue::connection("rabbit-rs-work")->later(120, new Modules\QueueLab\Jobs\StressJob(120), "", "work"); echo "published later(120)\n";'
wait $OBS
