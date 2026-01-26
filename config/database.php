<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'reports' => [
            'driver' => 'mysql',
            'host' => env('REPORTS_DB_HOST', '127.0.0.1'),
            'port' => env('REPORTS_DB_PORT', '3306'),
            'database' => env('REPORTS_DB_DATABASE', 'forge'),
            'username' => env('REPORTS_DB_USERNAME', 'forge'),
            'password' => env('REPORTS_DB_PASSWORD', ''),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'n8n_mysql' => [
            'driver' => 'mysql',
            'host' => env('N8N_DB_HOST', '127.0.0.1'),
            'port' => env('N8N_DB_PORT', '3306'),
            'database' => env('N8N_DB_DATABASE', 'price_quotation_system'),
            'username' => env('N8N_DB_USERNAME', 'forge'),
            'password' => env('N8N_DB_PASSWORD', ''),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

        'mysql_production' => [
            'driver' => 'mysql',
            'host' => env('DB_PRODUCTION_HOST', '127.0.0.1'),
            'port' => env('DB_PRODUCTION_PORT', '3306'),
            'database' => env('DB_PRODUCTION_DATABASE', 'forge'),
            'username' => env('DB_PRODUCTION_USERNAME', 'forge'),
            'password' => env('DB_PRODUCTION_PASSWORD', ''),
            'unix_socket' => env('DB_PRODUCTION_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */

    'migrations' => 'migrations',

];
