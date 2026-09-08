<?php

namespace PlinCode\KmlParser;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class KmlParserServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('kml-parser')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        /*
         * The parser keeps the loaded document in memory, so a singleton would
         * leak that state across requests under Octane and across jobs in a
         * long running queue worker. A scoped binding is resolved once per
         * request/job lifecycle and flushed in between.
         */
        $this->app->scoped(KmlParser::class, function () {
            return new KmlParser;
        });
    }
}
