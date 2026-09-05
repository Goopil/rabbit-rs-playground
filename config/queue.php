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

        'redis-sentinel' => [
            'driver' => 'phpredis-sentinel',
            'connection' => 'default',
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
        | one connection = one broker = one exchange. Queue names are flat
        | and identical on both transports (redis-sentinel and rabbit-rs):
        | default, high-priority, bulk. Cross-cutting keys (tls, delay,
        | dead_letter, queue_type, safety, ...) come from config/rabbit-rs.php.
        */
        'rabbit-rs' => [
            'driver' => 'rabbit-rs',
            'queue' => env('RABBIT_RS_QUEUE', 'default'),
            // NB: worker is read from the raw connection config by the
            // connector (defaults from config/rabbit-rs.php are NOT merged
            // before this lookup) — it must be declared here.
            'worker' => env('RABBIT_RS_WORKER', 'default'),
            'hosts' => env('RABBIT_RS_HOSTS', 'rabbitmq-simple:5672'),
            'vhost' => env('RABBIT_RS_VHOST', '/'),
            'username' => env('RABBIT_RS_USER', 'guest'),
            'password' => env('RABBIT_RS_PASS', 'guest'),
            'exchange' => 'laravel.jobs',
            'routing_key' => '{queue}',
            'subscriptions' => [
                'default' => [
                    'queue' => 'default',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
                    'starvation_after' => 30,
                ],
                'high-priority' => [
                    'queue' => 'high-priority',
                    'weight' => 4,
                    'priority_class' => -1,
                    'prefetch' => 16,
                    'starvation_after' => 15,
                ],
                'bulk' => [
                    'queue' => 'bulk',
                    'weight' => 1,
                    'priority_class' => 0,
                    'prefetch' => 16,
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
