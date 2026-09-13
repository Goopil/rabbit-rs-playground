#!/bin/sh
# Sentinel roast A: failover UNDER LOAD.
# Writer loop (10 writes/s + 10 reads/s through the sentinel driver) for 90s;
# the master is killed at t+12 and the container restarted at t+40.
# Output: /tmp/sentinel-roast.jsonl (one line per op: t, op, ms, err) +
# sentinel event feed /tmp/sentinel-events.txt.
php /var/www/html/artisan tinker --execute '
$fh = fopen("/tmp/sentinel-roast.jsonl", "w");
$t0 = microtime(true);
while (microtime(true) - $t0 < 90) {
    $t = microtime(true) - $t0;
    $s = microtime(true);
    try {
        Cache::store("redis")->increment("roast-a");
        $ms = (microtime(true) - $s) * 1000;
        fwrite($fh, json_encode(["t" => round($t, 2), "op" => "w", "ms" => round($ms, 1)]) . "\n");
    } catch (Throwable $e) {
        $ms = (microtime(true) - $s) * 1000;
        fwrite($fh, json_encode(["t" => round($t, 2), "op" => "e", "ms" => round($ms, 1), "err" => substr($e->getMessage(), 0, 90)]) . "\n");
    }
    $s = microtime(true);
    try {
        Cache::store("redis")->get("roast-a");
        $ms = (microtime(true) - $s) * 1000;
        fwrite($fh, json_encode(["t" => round(microtime(true) - $t0, 2), "op" => "r", "ms" => round($ms, 1)]) . "\n");
    } catch (Throwable $e) {
        $ms = (microtime(true) - $s) * 1000;
        fwrite($fh, json_encode(["t" => round(microtime(true) - $t0, 2), "op" => "e", "ms" => round($ms, 1), "err" => substr($e->getMessage(), 0, 90)]) . "\n");
    }
    usleep(100000);
}
fclose($fh);
echo "writer done\n";
' &
WRITER=$!
sleep 12
echo "t+12: killing valkey-master" >> /tmp/sentinel-events.txt
docker exec rabbit-rs-playground-valkey-master-1 valkey-cli shutdown nosave >> /tmp/sentinel-events.txt 2>&1
echo "t+12: master shut down" >> /tmp/sentinel-events.txt
sleep 28
echo "t+40: restarting old master container" >> /tmp/sentinel-events.txt
docker start rabbit-rs-playground-valkey-master-1 >> /tmp/sentinel-events.txt 2>&1
wait $WRITER
echo "=== events ==="; cat /tmp/sentinel-events.txt
