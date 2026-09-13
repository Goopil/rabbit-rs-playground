<?php

/*
|--------------------------------------------------------------------------
| Rabbit RS — Cross-Cutting Defaults (rabbit-rs-laravel 0.1.0 schema)
|--------------------------------------------------------------------------
| Since 0.1.0 the driver is connection-first: brokers, credentials, routes
| and worker subscriptions live on queue.connections.* in config/queue.php
| (one connection = one broker = one native pool). This file only holds
| cross-cutting defaults merged under every rabbit-rs connection: a key the
| connection omits is inherited from here (per sub-key for tls, delay and
| dead_letter), and env() strings are cast/validated lazily at connection
| resolution.
*/

return [

    'heartbeat' => env('RABBIT_RS_HEARTBEAT', 30),

    'tls' => [
        'enabled' => env('RABBIT_RS_TLS', false),
        'ca_cert' => env('RABBIT_RS_TLS_CA_CERT'),
        'client_cert' => env('RABBIT_RS_TLS_CLIENT_CERT'),
        'client_key' => env('RABBIT_RS_TLS_CLIENT_KEY'),
    ],

    // safe (confirms + mandatory) | unsafe (no confirms) | blind (fire-and-forget)
    'safety' => env('RABBIT_RS_SAFETY', 'safe'),

    'confirm_timeout' => env('RABBIT_RS_CONFIRM_TIMEOUT', 30000),

    'prefetch' => env('RABBIT_RS_PREFETCH', 16),

    'wait_timeout' => env('RABBIT_RS_CONSUMER_WAIT_TIMEOUT', 30000),

    // declare | verify | external
    'topology_mode' => env('RABBIT_RS_TOPOLOGY_MODE', 'declare'),

    /*
    | Playground topology: quorum queues, durable, delivery limit 20 with a
    | dead-letter exchange (mirrors rabbit-rs:setup-topology's x-delivery-limit
    | and the dead-letters/failed-jobs pair).
    */
    'queue_type' => 'quorum',
    'queue_durable' => true,
    'delivery_limit' => 20,
    'dead_letter' => [
        'exchange' => 'dead-letters',
        'queue' => 'failed-jobs',
        'routing_key' => null,
    ],

    // ttl (buckets; the playground brokers don't run the delayed_message plugin
    // and 0.1.0's auto probe fails hard instead of falling back) | auto | plugin
    'delay' => [
        'mode' => env('RABBIT_RS_DELAY_MODE', 'ttl'),
        'buckets' => array_map('intval', array_filter(array_map('trim', explode(',', env('RABBIT_RS_DELAY_BUCKETS', '1,5,30,120'))))),
        'max_buckets' => env('RABBIT_RS_DELAY_MAX_BUCKETS', 8),
        'queue_expiry_margin' => env('RABBIT_RS_DELAY_QUEUE_EXPIRY_MARGIN', 60),
    ],

    // default | horizon
    'worker' => env('RABBIT_RS_WORKER', 'default'),

    'production_warning' => env('RABBIT_RS_PRODUCTION_WARNING', true),

    'best_effort' => env('RABBIT_RS_BEST_EFFORT', false),

];
