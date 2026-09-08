<?php

namespace PlinCode\KmlParser\Traits;

trait ReadsPackageConfig
{
    /**
     * Read a package config value, falling back when there is no application.
     *
     * The parser and the extractor are both useful outside a booted Laravel
     * application: a console script, a plain PHPUnit test, a queue bootstrap
     * that has not resolved the config repository yet. The config() helper
     * does not degrade there, it throws BindingResolutionException, so the
     * container is only consulted once something is actually bound to it.
     */
    protected function packageConfig(string $key, mixed $default): mixed
    {
        if (! function_exists('config') || ! function_exists('app') || ! app()->bound('config')) {
            return $default;
        }

        return config($key, $default);
    }
}
