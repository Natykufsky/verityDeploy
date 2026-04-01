<?php

return [
    /*
    |--------------------------------------------------------------------------
    | View Paths
    |--------------------------------------------------------------------------
    |
    | Here you may specify which view paths should be checked for your
    | application views. Typically this is only the resources/views directory.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled Views Path
    |--------------------------------------------------------------------------
    |
    | On Windows, Laravel's compiled view swap can hit file-locking issues
    | when the cache lives alongside other active view files. Using a dedicated
    | compiled directory keeps those writes isolated and reduces rename races.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        sys_get_temp_dir().DIRECTORY_SEPARATOR.'veritydeploy-blade',
    ),
];
