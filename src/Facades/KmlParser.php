<?php

namespace PlinCode\KmlParser\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \PlinCode\KmlParser\KmlParser
 */
class KmlParser extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PlinCode\KmlParser\KmlParser::class;
    }
}
