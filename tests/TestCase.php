<?php

namespace EduKSX\Core\Tests;

use EduKSX\Core\CoreServiceProvider;
use EduKSX\Core\Http\Problem;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Testumgebung: Modul «demo»; Datenbank SQLite im Speicher oder – mit DB_CONNECTION=pgsql – PostgreSQL wie in der CI.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('eduksx.module', 'demo');
        $app['config']->set('app.debug', false);
        $driver = env('DB_CONNECTION', 'sqlite');
        if ($driver === 'pgsql') {
            $app['config']->set('database.default', 'pgsql');
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', 'core_test'),
                'username' => env('DB_USERNAME', 'core'),
                'password' => env('DB_PASSWORD', 'core'),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
            ]);
        } else {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Problem::register(new Exceptions($this->app->make(ExceptionHandler::class)));
    }
}
