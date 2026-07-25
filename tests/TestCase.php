<?php

namespace Tests;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        if (config('database.default') === 'sqlite') {
            config([
                'database.connections.pgsql_ekspedisi' => config('database.connections.sqlite'),
                'database.connections.pgsql_production' => config('database.connections.sqlite'),
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
