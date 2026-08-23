<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brokers
    |--------------------------------------------------------------------------
    | Each broker is a named connection pool. A vhost owns a distinct AMQP
    | connection. 6 brokers: 2 setups × 3 vhosts.
    */
    'brokers' => [
        // === Simple Setup ===
        'simple.default' => [
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/default',
            'credentials' => [
                'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
                'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        'simple.orders' => [
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/orders',
            'credentials' => [
                'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
                'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        'simple.notifications' => [
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/notifications',
            'credentials' => [
                'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
                'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        // === Cluster Setup ===
        'cluster.default' => [
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/default',
            'credentials' => [
                'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
                'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        'cluster.orders' => [
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/orders',
            'credentials' => [
                'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
                'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
        'cluster.notifications' => [
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/notifications',
            'credentials' => [
                'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
                'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            ],
            'heartbeat' => (int) env('RABBIT_RS_HEARTBEAT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes — map Laravel queue names to broker + exchange + routing key.
    | Route name = Laravel queue name = AMQP queue name (via {queue} placeholder).
    */
    'routes' => [
        // Simple — /default vhost
        'simple.default.default' => [
            'broker' => 'simple.default',
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
        ],
        'simple.default.high-priority' => [
            'broker' => 'simple.default',
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
        ],
        // Simple — /orders vhost
        'simple.orders.created' => [
            'broker' => 'simple.orders',
            'exchange' => 'laravel.orders',
            'routing_key' => '{queue}',
        ],
        'simple.orders.paid' => [
            'broker' => 'simple.orders',
            'exchange' => 'laravel.orders',
            'routing_key' => '{queue}',
        ],
        'simple.orders.shipped' => [
            'broker' => 'simple.orders',
            'exchange' => 'laravel.orders',
            'routing_key' => '{queue}',
        ],
        // Simple — /notifications vhost
        'simple.notifications.email' => [
            'broker' => 'simple.notifications',
            'exchange' => 'laravel.notifications',
            'routing_key' => '{queue}',
        ],
        'simple.notifications.sms' => [
            'broker' => 'simple.notifications',
            'exchange' => 'laravel.notifications',
            'routing_key' => '{queue}',
        ],
        'simple.notifications.push' => [
            'broker' => 'simple.notifications',
            'exchange' => 'laravel.notifications',
            'routing_key' => '{queue}',
        ],
        // Cluster — /default vhost
        'cluster.default.default' => [
            'broker' => 'cluster.default',
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
        ],
        'cluster.default.high-priority' => [
            'broker' => 'cluster.default',
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
        ],
        // Cluster — /orders vhost
        'cluster.orders.created' => [
            'broker' => 'cluster.orders',
            'exchange' => 'laravel.orders',
            'routing_key' => '{queue}',
        ],
        'cluster.orders.paid' => [
            'broker' => 'cluster.orders',
            'exchange' => 'laravel.orders',
            'routing_key' => '{queue}',
        ],
        'cluster.orders.shipped' => [
            'broker' => 'cluster.orders',
            'exchange' => 'laravel.orders',
            'routing_key' => '{queue}',
        ],
        // Cluster — /notifications vhost
        'cluster.notifications.email' => [
            'broker' => 'cluster.notifications',
            'exchange' => 'laravel.notifications',
            'routing_key' => '{queue}',
        ],
        'cluster.notifications.sms' => [
            'broker' => 'cluster.notifications',
            'exchange' => 'laravel.notifications',
            'routing_key' => '{queue}',
        ],
        'cluster.notifications.push' => [
            'broker' => 'cluster.notifications',
            'exchange' => 'laravel.notifications',
            'routing_key' => '{queue}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workers — 6 profiles, one per vhost per setup.
    | Subscription queue values match route names exactly.
    */
    'workers' => [
        'simple.default' => [
            'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 64],
            'subscriptions' => [
                'default' => [
                    'enabled' => true,
                    'broker' => 'simple.default',
                    'queue' => 'simple.default.default',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 16],
                    'starvation_after' => 30,
                ],
                'high-priority' => [
                    'enabled' => true,
                    'broker' => 'simple.default',
                    'queue' => 'simple.default.high-priority',
                    'weight' => 4,
                    'priority_class' => -1,
                    'prefetch' => ['mode' => 'fixed', 'value' => 16],
                    'starvation_after' => 15,
                ],
            ],
        ],
        'simple.orders' => [
            'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 64],
            'subscriptions' => [
                'created' => [
                    'enabled' => true,
                    'broker' => 'simple.orders',
                    'queue' => 'simple.orders.created',
                    'weight' => 2,
                    'priority_class' => -1,
                    'prefetch' => ['mode' => 'fixed', 'value' => 16],
                    'starvation_after' => 30,
                ],
                'paid' => [
                    'enabled' => true,
                    'broker' => 'simple.orders',
                    'queue' => 'simple.orders.paid',
                    'weight' => 2,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 16],
                    'starvation_after' => 30,
                ],
                'shipped' => [
                    'enabled' => true,
                    'broker' => 'simple.orders',
                    'queue' => 'simple.orders.shipped',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 16],
                    'starvation_after' => 30,
                ],
            ],
        ],
        'simple.notifications' => [
            'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 64],
            'subscriptions' => [
                'email' => [
                    'enabled' => true,
                    'broker' => 'simple.notifications',
                    'queue' => 'simple.notifications.email',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 16],
                    'starvation_after' => 30,
                ],
                'sms' => [
                    'enabled' => true,
                    'broker' => 'simple.notifications',
                    'queue' => 'simple.notifications.sms',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 16],
                    'starvation_after' => 30,
                ],
                'push' => [
                    'enabled' => true,
                    'broker' => 'simple.notifications',
                    'queue' => 'simple.notifications.push',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 16],
                    'starvation_after' => 30,
                ],
            ],
        ],
        'cluster.default' => [
            'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 128],
            'subscriptions' => [
                'default' => [
                    'enabled' => true,
                    'broker' => 'cluster.default',
                    'queue' => 'cluster.default.default',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 32],
                    'starvation_after' => 30,
                ],
                'high-priority' => [
                    'enabled' => true,
                    'broker' => 'cluster.default',
                    'queue' => 'cluster.default.high-priority',
                    'weight' => 4,
                    'priority_class' => -1,
                    'prefetch' => ['mode' => 'fixed', 'value' => 32],
                    'starvation_after' => 15,
                ],
            ],
        ],
        'cluster.orders' => [
            'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 128],
            'subscriptions' => [
                'created' => [
                    'enabled' => true,
                    'broker' => 'cluster.orders',
                    'queue' => 'cluster.orders.created',
                    'weight' => 2,
                    'priority_class' => -1,
                    'prefetch' => ['mode' => 'fixed', 'value' => 32],
                    'starvation_after' => 30,
                ],
                'paid' => [
                    'enabled' => true,
                    'broker' => 'cluster.orders',
                    'queue' => 'cluster.orders.paid',
                    'weight' => 2,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 32],
                    'starvation_after' => 30,
                ],
                'shipped' => [
                    'enabled' => true,
                    'broker' => 'cluster.orders',
                    'queue' => 'cluster.orders.shipped',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 32],
                    'starvation_after' => 30,
                ],
            ],
        ],
        'cluster.notifications' => [
            'scheduler' => ['strategy' => 'weighted_fair', 'max_in_flight' => 128],
            'subscriptions' => [
                'email' => [
                    'enabled' => true,
                    'broker' => 'cluster.notifications',
                    'queue' => 'cluster.notifications.email',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 32],
                    'starvation_after' => 30,
                ],
                'sms' => [
                    'enabled' => true,
                    'broker' => 'cluster.notifications',
                    'queue' => 'cluster.notifications.sms',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 32],
                    'starvation_after' => 30,
                ],
                'push' => [
                    'enabled' => true,
                    'broker' => 'cluster.notifications',
                    'queue' => 'cluster.notifications.push',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => ['mode' => 'fixed', 'value' => 32],
                    'starvation_after' => 30,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Topology — quorum queues, durable, with dead-letter exchange.
    */
    'topology' => [
        'queue' => [
            'type' => 'quorum',
            'durable' => true,
            'delivery_limit' => 20,
        ],
        'dead_letter' => [
            'exchange' => 'dead-letters',
            'queue' => 'failed-jobs',
            'routing_key' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Topology Mode
    */
    'topology_mode' => env('RABBIT_RS_TOPOLOGY_MODE', 'declare'),

    /*
    |--------------------------------------------------------------------------
    | Publisher — confirms + mandatory routing.
    */
    'publisher' => [
        'confirms' => true,
        'mandatory' => true,
        'confirm_timeout' => (int) env('RABBIT_RS_CONFIRM_TIMEOUT', 30000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delay — auto-detect plugin, fall back to TTL buckets.
    */
    'delay' => [
        'mode' => env('RABBIT_RS_DELAY_MODE', 'auto'),
        'buckets' => array_map('intval', array_filter(array_map('trim', explode(',', env('RABBIT_RS_DELAY_BUCKETS', '1,5,30,120'))))),
        'max_buckets' => (int) env('RABBIT_RS_DELAY_MAX_BUCKETS', 8),
        'queue_expiry_margin' => (int) env('RABBIT_RS_DELAY_QUEUE_EXPIRY_MARGIN', 60),
        'detection_timeout' => (int) env('RABBIT_RS_DELAY_DETECTION_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | TLS
    */
    'tls' => [
        'enabled' => (bool) env('RABBIT_RS_TLS', false),
        'server_name' => env('RABBIT_RS_TLS_SERVER_NAME'),
        'ca_cert' => env('RABBIT_RS_TLS_CA_CERT'),
        'client_cert' => env('RABBIT_RS_TLS_CLIENT_CERT'),
        'client_key' => env('RABBIT_RS_TLS_CLIENT_KEY'),
        'verify' => env('RABBIT_RS_TLS_VERIFY', 'peer'),
    ],
];
