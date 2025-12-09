<?php

use App\Config\Config;

return [
    /*
    |--------------------------------------------------------------------------
    | PDO Fetch Style
    |--------------------------------------------------------------------------
    |
    | By default, database results will be returned as instances of the PHP
    | stdClass object; however, you may wish to retrieve records in an
    | array format for simplicity. Here you can set the fetch style.
    |
    */

    'fetch' => PDO::FETCH_OBJ,

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => Config::get('database.default', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by the framework is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => Config::get('database.host', '127.0.0.1'),
            'port' => Config::get('database.port', 5432),
            'database' => Config::get('database.database', 'forge'),
            'username' => Config::get('database.username', 'forge'),
            'password' => Config::get('database.password', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [
        'client' => 'phpredis',

        'options' => [
            'cluster' => Config::get('redis.cluster', 'redis'),
            'prefix' => Config::get('redis.prefix', 'document_ocr_database_'),
        ],

        'default' => [
            'url' => Config::get('redis.url'),
            'host' => Config::get('redis.host', '127.0.0.1'),
            'password' => Config::get('redis.password', null),
            'port' => Config::get('redis.port', 6379),
            'database' => Config::get('redis.db', 0),
        ],
    ],
];