<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'vtiger'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'vtiger' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'vtiger'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter(array_merge(
                [PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA')],
                env('DB_PERSISTENT', false) ? [PDO::ATTR_PERSISTENT => true] : []
            )) : [],
        ],

        // Optional direct PBX CDR connection (use when vtiger user cannot read asteriskcdrdb.cdr).
        'pbx_cdr' => [
            'driver' => 'mysql',
            'host' => env('PBX_CDR_DB_HOST', ''),
            'port' => env('PBX_CDR_DB_PORT', '3306'),
            'database' => env('PBX_CDR_DB_DATABASE', 'asteriskcdrdb'),
            'username' => env('PBX_CDR_DB_USERNAME', ''),
            'password' => env('PBX_CDR_DB_PASSWORD', ''),
            'unix_socket' => env('PBX_CDR_DB_SOCKET', ''),
            'charset' => env('PBX_CDR_DB_CHARSET', 'utf8'),
            'collation' => env('PBX_CDR_DB_COLLATION', 'utf8_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        /*
        | ERP (Oracle/PL-SQL) - requires yajra/laravel-oci8 and OCI8 extension
        | composer require yajra/laravel-oci8:^12
        |
        | Credentials: set ERP_CREDENTIALS_FILE=/path/to/erp-credentials.json in .env
        | to use JSON file (recommended for security). Otherwise uses ERP_* env vars.
        */
        'erp' => (function () {
            $base = [
                'driver' => 'oracle',
                'tns' => env('ERP_TNS', ''),
                'host' => env('ERP_HOST', ''),
                'port' => env('ERP_PORT', '1521'),
                'database' => env('ERP_DATABASE', ''),
                'service_name' => env('ERP_SERVICE_NAME', ''),
                'username' => env('ERP_USERNAME', ''),
                'password' => env('ERP_PASSWORD', ''),
                'charset' => env('ERP_CHARSET', 'AL32UTF8'),
                'prefix' => env('ERP_PREFIX', ''),
                'prefix_schema' => env('ERP_SCHEMA_PREFIX', ''),
                'skip_session_vars' => true,
            ];
            $path = env('ERP_CREDENTIALS_FILE', '');
            if (empty($path)) {
                return $base;
            }
            if ($path[0] !== '/' && $path[0] !== '\\' && !preg_match('#^[A-Za-z]:[/\\\\]#', $path)) {
                $path = base_path($path);
            }
            if (!is_readable($path)) {
                return $base;
            }
            $json = @json_decode(file_get_contents($path), true);
            if (!is_array($json)) {
                return $base;
            }
            $host = $json['host'] ?? null;
            $port = (int) ($json['port'] ?? 1521);
            $service = $json['database'] ?? $json['service_name'] ?? $json['serviceName'] ?? null;
            if (!empty($json['connectString']) && preg_match('#^([^:]+):(\d+)/(.+)$#', trim($json['connectString']), $m)) {
                $host = $m[1];
                $port = (int) $m[2];
                $service = $m[3];
            }
            return array_merge($base, array_filter([
                'username' => $json['user'] ?? $json['username'] ?? null,
                'password' => $json['password'] ?? null,
                'host' => $host,
                'port' => $port,
                'service_name' => $service,
                'database' => $service,
            ]));
        })(),

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
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
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
