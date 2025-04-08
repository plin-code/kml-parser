<?php

namespace PlinCode\KmlParser;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use PlinCode\KmlParser\Commands\KmlParserCommand;

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
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_kml_parser_table')
            ->hasCommand(KmlParserCommand::class);
    }
}
