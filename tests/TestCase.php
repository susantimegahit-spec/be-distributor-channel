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
            $ekspedisiDb = database_path('testing_ekspedisi.sqlite');
            $productionDb = database_path('testing_production.sqlite');

            $needsMigration = false;
            if (! static::$databasesInitialized) {
                @unlink($defaultDb);
                @unlink($ekspedisiDb);
                @unlink($productionDb);
                @touch($defaultDb);
                @touch($ekspedisiDb);
                @touch($productionDb);
                static::$databasesInitialized = true;
                $needsMigration = true;
            }

            config([
                'database.connections.sqlite.database' => $defaultDb,
                'database.connections.pgsql_ekspedisi' => [
                    'driver' => 'sqlite',
                    'database' => $ekspedisiDb,
                    'prefix' => '',
                    'foreign_key_constraints' => false,
                ],
                'database.connections.pgsql_production' => [
                    'driver' => 'sqlite',
                    'database' => $productionDb,
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

        if (config('database.default') === 'sqlite' && !empty($needsMigration)) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/production',
                '--database' => 'pgsql_production',
            ]);
        }
    }
}
