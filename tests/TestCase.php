<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get(
            'database.connections.sqlite.database'
        );

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'Tests require in-memory SQLite. Check phpunit.xml '
                .'and run php artisan config:clear.'
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}