<?php

namespace Firebird\Tests;

use Firebird\FirebirdServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /**
     * Load package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
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
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetup($app)
    {
        $config = $app['config'];

        $config->set('database.default', 'firebird');
        $config->set('database.connections.firebird', [
            'driver' => 'firebird',
            'host' => 'localhost',
            'database' => '/storage/firebird/APPLICATION.FDB',
            'username' => 'sysdba',
            'password' => 'masterkey',
            'charset' => 'UTF8',
            'role' => 'RDB$ADMIN',
            'engine_version' => '2.5.0',
        ]);
    }
}
