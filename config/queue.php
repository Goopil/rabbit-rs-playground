<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

        /*
        | Rabbit RS — connection-first schema (rabbit-rs-laravel 0.1.0):
        | one connection = one broker = one exchange, named after the old
        | config's brokers. The old per-queue routes (simple.*.*,
        | simple.all.*, cluster.*) are preserved as the connection's
        | exchange + routing_key ({queue} = Laravel queue name) and its
        | subscriptions; publishing queue "simple.all.orders.created" through
        | the "simple.orders" connection lands on the same AMQP queue as
        | before. Cross-cutting keys (tls, delay, dead_letter, queue_type,
        | safety, ...) are inherited from config/rabbit-rs.php.
        */
        'rabbit-rs' => [
            'driver' => 'rabbit-rs',
            'queue' => env('RABBIT_RS_QUEUE', 'simple.default.default'),
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/default',
            'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
            'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
            'subscriptions' => [
                'default' => [
                    'queue' => 'simple.default.default',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'high-priority' => [
                    'queue' => 'simple.default.high-priority',
                    'weight' => 4,
                    'priority_class' => -1,
                    'prefetch' => 16,
                    'starvation_after' => 15,
                ],
            ],
        ],

        'simple-default' => [
            'driver' => 'rabbit-rs',
            'queue' => 'simple.default.default',
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/default',
            'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
            'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
            'subscriptions' => [
                'default' => [
                    'queue' => 'simple.default.default',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'high-priority' => [
                    'queue' => 'simple.default.high-priority',
                    'weight' => 4,
                    'priority_class' => -1,
                    'prefetch' => 16,
                    'starvation_after' => 15,
                ],
                'all-default' => [
                    'queue' => 'simple.all.default',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'all-high-priority' => [
                    'queue' => 'simple.all.high-priority',
                    'weight' => 4,
                    'priority_class' => -1,
                    'prefetch' => 16,
                    'starvation_after' => 15,
                ],
            ],
        ],

        'simple-orders' => [
            'driver' => 'rabbit-rs',
            'queue' => 'simple.orders.created',
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/orders',
            'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
            'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            'exchange' => 'laravel.orders',
            'routing_key' => '{queue}',
            'subscriptions' => [
                'created' => [
                    'queue' => 'simple.orders.created',
                    'weight' => 2,
                    'priority_class' => -1,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'paid' => [
                    'queue' => 'simple.orders.paid',
                    'weight' => 2,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'shipped' => [
                    'queue' => 'simple.orders.shipped',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'all-created' => [
                    'queue' => 'simple.all.orders.created',
                    'weight' => 2,
                    'priority_class' => -1,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'all-paid' => [
                    'queue' => 'simple.all.orders.paid',
                    'weight' => 2,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'all-shipped' => [
                    'queue' => 'simple.all.orders.shipped',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
            ],
        ],

        'simple-notifications' => [
            'driver' => 'rabbit-rs',
            'queue' => 'simple.notifications.email',
            'hosts' => env('RABBIT_RS_SIMPLE_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => '/notifications',
            'username' => env('RABBIT_RS_SIMPLE_USER', 'guest'),
            'password' => env('RABBIT_RS_SIMPLE_PASS', 'guest'),
            'exchange' => 'laravel.notifications',
            'routing_key' => '{queue}',
            'subscriptions' => [
                'email' => [
                    'queue' => 'simple.notifications.email',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'sms' => [
                    'queue' => 'simple.notifications.sms',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'push' => [
                    'queue' => 'simple.notifications.push',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'all-email' => [
                    'queue' => 'simple.all.notifications.email',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'all-sms' => [
                    'queue' => 'simple.all.notifications.sms',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'all-push' => [
                    'queue' => 'simple.all.notifications.push',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
            ],
        ],

        'cluster-default' => [
            'driver' => 'rabbit-rs',
            'queue' => 'cluster.default.default',
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/default',
            'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
            'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
            'subscriptions' => [
                'default' => [
                    'queue' => 'cluster.default.default',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'high-priority' => [
                    'queue' => 'cluster.default.high-priority',
                    'weight' => 4,
                    'priority_class' => -1,
                    'prefetch' => 32,
                    'starvation_after' => 15,
                ],
                'all-default' => [
                    'queue' => 'cluster.all.default',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'all-high-priority' => [
                    'queue' => 'cluster.all.high-priority',
                    'weight' => 4,
                    'priority_class' => -1,
                    'prefetch' => 32,
                    'starvation_after' => 15,
                ],
            ],
        ],

        'cluster-orders' => [
            'driver' => 'rabbit-rs',
            'queue' => 'cluster.orders.created',
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/orders',
            'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
            'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            'exchange' => 'laravel.orders',
            'routing_key' => '{queue}',
            'subscriptions' => [
                'created' => [
                    'queue' => 'cluster.orders.created',
                    'weight' => 2,
                    'priority_class' => -1,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'paid' => [
                    'queue' => 'cluster.orders.paid',
                    'weight' => 2,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'shipped' => [
                    'queue' => 'cluster.orders.shipped',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'all-created' => [
                    'queue' => 'cluster.all.orders.created',
                    'weight' => 2,
                    'priority_class' => -1,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'all-paid' => [
                    'queue' => 'cluster.all.orders.paid',
                    'weight' => 2,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'all-shipped' => [
                    'queue' => 'cluster.all.orders.shipped',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
            ],
        ],

        'cluster-notifications' => [
            'driver' => 'rabbit-rs',
            'queue' => 'cluster.notifications.email',
            'hosts' => env('RABBIT_RS_CLUSTER_HOSTS', 'rabbitmq-1:5672,rabbitmq-2:5672,rabbitmq-3:5672'),
            'vhost' => '/notifications',
            'username' => env('RABBIT_RS_CLUSTER_USER', 'guest'),
            'password' => env('RABBIT_RS_CLUSTER_PASS', 'guest'),
            'exchange' => 'laravel.notifications',
            'routing_key' => '{queue}',
            'subscriptions' => [
                'email' => [
                    'queue' => 'cluster.notifications.email',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'sms' => [
                    'queue' => 'cluster.notifications.sms',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'push' => [
                    'queue' => 'cluster.notifications.push',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'all-email' => [
                    'queue' => 'cluster.all.notifications.email',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'all-sms' => [
                    'queue' => 'cluster.all.notifications.sms',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
                'all-push' => [
                    'queue' => 'cluster.all.notifications.push',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 32,
                    'starvation_after' => 30,
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
