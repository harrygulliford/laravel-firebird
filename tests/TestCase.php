<?php

namespace HarryGulliford\Firebird\Tests;

use HarryGulliford\Firebird\FirebirdServiceProvider;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /**
     * Load package service provider.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            FirebirdServiceProvider::class,
        ];
    }

    /**
     * Define the environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetup($app)
    {
        config()->set('database.default', 'firebird');
        config()->set('database.connections.firebird', [
            'driver' => 'firebird',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '3050'),
            'database' => env('DB_DATABASE', '/var/lib/firebird/data/database.fdb'),
            'username' => env('DB_USERNAME', 'sysdba'),
            'password' => env('DB_PASSWORD', 'masterkey'),
            'charset' => env('DB_CHARSET', 'UTF8'),
        ]);
    }

    /**
     * Determine the Firebird engine version of the current database connection.
     *
     * @return float
     */
    public function getDatabaseEngineVersion(): float
    {
        return (float) DB::selectOne('SELECT rdb$get_context(\'SYSTEM\', \'ENGINE_VERSION\') as "version" from rdb$database')->version;
    }
}
