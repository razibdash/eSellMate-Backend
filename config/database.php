<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'database' => env('DB_DATABASE', database_path('database.sqlite')), 'prefix' => '', 'foreign_key_constraints' => true],
        'mysql' => ['driver' => 'mysql', 'host' => env('DB_HOST','127.0.0.1'), 'port' => env('DB_PORT','3306'), 'database' => env('DB_DATABASE','shopbotbd'), 'username' => env('DB_USERNAME','root'), 'password' => env('DB_PASSWORD',''), 'unix_socket' => env('DB_SOCKET',''), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => null, 'options' => extension_loaded('pdo_mysql') ? array_filter([PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA')]) : []],
        'pgsql' => ['driver'=>'pgsql','host'=>env('DB_HOST','127.0.0.1'),'port'=>env('DB_PORT','5432'),'database'=>env('DB_DATABASE','shopbotbd'),'username'=>env('DB_USERNAME','root'),'password'=>env('DB_PASSWORD',''),'charset'=>'utf8','prefix'=>'','prefix_indexes'=>true,'search_path'=>'public','sslmode'=>'prefer'],
    ],
    'migrations' => ['table' => 'migrations'],
    'redis' => ['client' => env('REDIS_CLIENT', 'phpredis'), 'default' => ['host'=>env('REDIS_HOST','127.0.0.1'), 'password'=>env('REDIS_PASSWORD'), 'port'=>env('REDIS_PORT','6379'), 'database'=>0]],
];
