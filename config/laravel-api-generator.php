<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Stub Path
    |--------------------------------------------------------------------------
    |
    | This value determines the default path where the package will look for
    | stub files. You can override this by publishing the stubs and
    | customizing them according to your needs.
    |
    */
    'stub_path' => __DIR__ . '/../stubs',

    /*
    |--------------------------------------------------------------------------
    | Generated Files Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure the paths and namespaces for generated files.
    | These paths are relative to the Laravel application root.
    |
    */
    'paths' => [
        'models' => 'app/Models',
        'controllers' => 'app/Http/Controllers',
        'requests' => 'app/Http/Requests',
        'resources' => 'app/Http/Resources',
        'services' => 'app/Services',
        'dto' => 'app/DTO',
        'policies' => 'app/Policies',
        'factories' => 'database/factories',
        'seeders' => 'database/seeders',
        'migrations' => 'database/migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespaces Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the namespaces for generated classes.
    |
    */
    'namespaces' => [
        'models' => 'App\\Models',
        'controllers' => 'App\\Http\\Controllers',
        'requests' => 'App\\Http\\Requests',
        'resources' => 'App\\Http\\Resources',
        'services' => 'App\\Services',
        'dto' => 'App\\DTO',
        'policies' => 'App\\Policies',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Field Types
    |--------------------------------------------------------------------------
    |
    | Define the default field types and their corresponding database types,
    | validation rules, and factory values.
    |
    */
    'field_types' => [
        'string' => [
            'database' => 'string',
            'php' => 'string',
            'validation' => 'string|max:255',
            'factory' => 'fake()->word()',
        ],
        'text' => [
            'database' => 'text',
            'php' => 'string',
            'validation' => 'string',
            'factory' => 'fake()->sentence()',
        ],
        'integer' => [
            'database' => 'integer',
            'php' => 'int',
            'validation' => 'integer',
            'factory' => 'fake()->randomNumber()',
        ],
        'boolean' => [
            'database' => 'boolean',
            'php' => 'bool',
            'validation' => 'boolean',
            'factory' => 'fake()->boolean()',
        ],
        'float' => [
            'database' => 'decimal',
            'php' => 'float',
            'validation' => 'numeric',
            'factory' => 'fake()->randomFloat(2, 1, 1000)',
        ],
        'json' => [
            'database' => 'json',
            'php' => 'array',
            'validation' => 'json',
            'factory' => 'json_encode([\'key\' => \'value\'])',
        ],
        'date' => [
            'database' => 'date',
            'php' => '\DateTimeInterface',
            'validation' => 'date',
            'factory' => 'fake()->date()',
        ],
        'datetime' => [
            'database' => 'datetime',
            'php' => '\DateTimeInterface',
            'validation' => 'date',
            'factory' => 'fake()->dateTime()',
        ],
        'timestamp' => [
            'database' => 'timestamp',
            'php' => '\DateTimeInterface',
            'validation' => 'date',
            'factory' => 'fake()->dateTime()',
        ],
        'uuid' => [
            'database' => 'uuid',
            'php' => 'string',
            'validation' => 'uuid',
            'factory' => 'fake()->uuid()',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generator Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which generators should be enabled and their order of execution.
    |
    */
    'generators' => [
        'model' => true,
        'migration' => true,
        'controller' => true,
        'request' => true,
        'resource' => true,
        'service' => true,
        'dto' => true,
        'policy' => true,
        'factory' => true,
        'seeder' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how API routes should be generated.
    |
    */
    'routes' => [
        'file' => 'routes/api.php',
        'prefix' => 'api',
        'middleware' => ['api'],
    ],
];
