#!/bin/sh
# REAL unroutable test: delete laravel.jobs→work (rk=work) via the API (PHP,
# no shell quoting), verify, then run the per-mode probe (safe-return-probe),
# restore. In safe mode a basic.return should surface via drainSettlementErrors
# / the next operation (RabbitMqQueue.php:373).
cd /var/www/html
AUTH="Authorization: Basic $(echo -n guest:guest | base64)"

api() {
    php -r "
\$ctx = stream_context_create(['http' => ['method' => '$2', 'header' => 'Authorization: Basic ' . base64_encode('guest:guest'), 'content-type: application/json', 'content' => '$3', 'ignore_errors' => true]]);
\$r = @file_get_contents('http://rabbitmq-simple:15672$1', false, \$ctx);
echo (\$http_response_header[0] ?? '?') . ' ' . substr((string) \$r, 0, 80);
"
}

bind_count() {
    php -r "
\$ctx = stream_context_create(['http' => ['header' => 'Authorization: Basic ' . base64_encode('guest:guest')]]);
\$d = json_decode((string) @file_get_contents('http://rabbitmq-simple:15672/api/queues/%2F/work/bindings', false, \$ctx), true) ?? [];
\$n = 0;
foreach (\$d as \$b) { if ((\$b['source'] ?? '') === 'laravel.jobs' && (\$b['routing_key'] ?? '') === 'work') \$n++; }
echo \$n;
"
}

# clear first so depth readings are meaningful
php artisan tinker --execute 'Queue::connection("rabbit-rs-work")->clear("work"); Queue::connection("rabbit-rs-work")->size("work");' 2>/dev/null
sleep 1

echo "before: rk=work bindings on work = $(bind_count)"
api "/api/bindings/%2F/e/laravel.jobs/q/work/work" DELETE "" >/dev/null
echo "after delete: rk=work bindings on work = $(bind_count)"

for MODE in safe unsafe blind; do
    echo "--- $MODE (binding absent) ---"
    php /var/www/html/node/safe-return-probe.php "$MODE" 2>&1 | grep -vE "Warning|Deprecated|^$"
done

api "/api/bindings/%2F/e/laravel.jobs/q/work" POST '{"routing_key":"work","arguments":{}}' >/dev/null
echo "restored: rk=work bindings on work = $(bind_count)"
php artisan tinker --execute 'Queue::connection("rabbit-rs-work")->clear("work"); Queue::connection("rabbit-rs-work")->size("work");' 2>/dev/null
