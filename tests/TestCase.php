<?php

namespace TheShit\Review\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TheShit\Review\ReviewServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ReviewServiceProvider::class];
    }
}
