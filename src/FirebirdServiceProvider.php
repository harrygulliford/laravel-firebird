<?php

namespace HarryGulliford\Firebird;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use PDO;

class FirebirdServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Connection::resolverFor('firebird', function (PDO|Closure $pdo, string $database = '', string $tablePrefix = '', array $config = []) {
            return new FirebirdConnection($pdo, $database, $tablePrefix, $config);
        });

        $this->app->bind('db.connector.firebird', FirebirdConnector::class);
    }
}
