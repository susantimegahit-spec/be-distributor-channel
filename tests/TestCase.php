<?php

namespace Tests;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static bool $databasesInitialized = false;

    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        if (config('database.default') === 'sqlite') {
            $defaultDb = database_path('testing_default.sqlite');

            if (! static::$databasesInitialized) {
                @unlink($defaultDb);
                @touch($defaultDb);
                static::$databasesInitialized = true;
            }

            config([
                'database.connections.sqlite.database' => $defaultDb,
                'database.connections.pgsql_ekspedisi' => [
                    'driver' => 'sqlite',
                    'database' => $defaultDb,
                    'prefix' => '',
                    'foreign_key_constraints' => false,
                ],
                'database.connections.pgsql_production' => [
                    'driver' => 'sqlite',
                    'database' => $defaultDb,
                    'prefix' => '',
                    'foreign_key_constraints' => false,
                ],
            ]);

            // Bind custom Blueprint globally so that all connections strip schema dot notation from foreign keys
            $this->app->bind(Blueprint::class, function ($app, $parameters) {
                return new class($parameters['connection'], $parameters['table'], $parameters['callback']) extends Blueprint {
                    public function toSql()
                    {
                        foreach ($this->commands as $command) {
                            if ($command->name === 'foreign' && isset($command->on)) {
                                if (str_contains($command->on, '.')) {
                                    $parts = explode('.', $command->on);
                                    $command->on = end($parts);
                                }
                            }
                        }
                        return parent::toSql();
                    }
                };
            });
        }

        parent::setUp();
    }
}
