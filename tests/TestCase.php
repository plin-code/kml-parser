<?php

namespace PlinCode\KmlParser\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PlinCode\KmlParser\KmlParserServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            KmlParserServiceProvider::class,
        ];
    }
}
