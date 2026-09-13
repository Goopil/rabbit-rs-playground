<?php

$ctx = stream_context_create(['http' => ['header' => 'Authorization: Basic '.base64_encode('guest:guest'), 'ignore_errors' => true]]);
$bindings = json_decode((string) @file_get_contents('http://rabbitmq-simple:15672/api/queues/%2F/work/bindings', false, $ctx), true) ?? [];

echo "bindings on 'work':\n";
foreach ($bindings as $b) {
    printf("  source=%-15s routing_key=%-20s properties_key=%s\n", $b['source'] ?: '(default)', $b['routing_key'], $b['properties_key']);
}
