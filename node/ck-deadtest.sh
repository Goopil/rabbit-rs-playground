#!/bin/sh
# Discriminator: kill 2 of 3 workers (active stays 1) — does the primary
# survive and re-fork both slots?
cd /var/www/html
SSR_PORT=13915 SSR_METRICS_PORT=13916 WEB_CONCURRENCY=3 node node/clusterkit-server.mjs > /tmp/ssr-v5.log 2>&1 &
PRIMARY=$!
sleep 4
W1=$(grep -m1 "Worker online" /tmp/ssr-v5.log | sed 's/.*pid: \([0-9]*\).*/\1/')
W2=$(grep "Worker online" /tmp/ssr-v5.log | sed -n 2p | sed 's/.*pid: \([0-9]*\).*/\1/')
echo "killing 2 of 3: $W1 $W2"
kill -9 "$W1" "$W2" 2>/dev/null
sleep 8
echo "primary alive?"; kill -0 "$PRIMARY" && echo YES || echo NO
echo "Worker online lines (boot=3 + re-forks): $(grep -c 'Worker online' /tmp/ssr-v5.log)"
echo "--- restart activity:"; grep -E "restart|crash" /tmp/ssr-v5.log | tail -5
echo "--- renders:"; node node/ck-roast.mjs http://127.0.0.1:13915 3 5
kill -9 "$PRIMARY" 2>/dev/null
