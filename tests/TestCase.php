<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $default = config('database.default');
        $database = config("database.connections.{$default}.database");

        if ($default === 'pgsql' && $database !== 'plant_doctor_test') {
            throw new \RuntimeException(
                'Tests are not allowed to run against a non-test database. '
                ."Expected \"plant_doctor_test\", got \"{$database}\". "
                .'Clear the config cache (composer run test does it) so the phpunit.xml env vars apply.'
            );
        }
    }
}
