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
        $this->app->singleton(KmlParser::class, function () {
            return new KmlParser;
        });
    }
}
