#!/bin/sh
# T3 v2: circuit breaker trip — 5 crashes of ONE slot's replacements.
cd /var/www/html
CRASH_THRESHOLD=5 node node/ck-edge.mjs 13951 > /tmp/ck-t3.log 2>&1 &
P=$!
sleep 2
OTHER=$(grep -a "health" /tmp/ck-t3.log | awk '{print $4}' | tail -1)
echo "surviving worker: $OTHER (never killed)"
i=0
while [ $i -lt 5 ]; do
    for pid in $(pgrep -f "ck-edge.mjs"); do
        [ "$pid" = "$P" ] && continue
        [ "$pid" = "$OTHER" ] && continue
        kill -9 "$pid" 2>/dev/null
    done
    sleep 2.5
    i=$((i+1))
done
sleep 3
echo "=== events:"
grep -aE ">>> (worker:crash|circuit-breaker|fleet)" /tmp/ck-t3.log | head -10
kill -0 "$P" && echo "primary alive" || echo "primary dead"
echo -n "serving: "; curl -s -m 2 "http://127.0.0.1:13951/" || echo "DEAD"
echo
kill -9 "$P" 2>/dev/null
for pid in $(pgrep -f "ck-edge.mjs"); do kill -9 "$pid" 2>/dev/null; done
