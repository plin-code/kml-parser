<?php

use PlinCode\KmlParser\Exceptions\KmlParserException;
use PlinCode\KmlParser\KmlParser;

it('reuses the same parser instance within a single scope', function () {
    $parser = app(KmlParser::class);

    expect(app(KmlParser::class))->toBe($parser);
});

it('resolves a fresh parser once the scope is flushed', function () {
    $parser = app(KmlParser::class);

    app()->forgetScopedInstances();

    expect(app(KmlParser::class))->not->toBe($parser);
});

it('does not leak a loaded document across scopes', function () {
    app(KmlParser::class)->loadFromFile(__DIR__.'/files/kml-example/base.kml');

    app()->forgetScopedInstances();

    app(KmlParser::class)->getPlacemarks();
})->throws(KmlParserException::class, 'No KML data loaded');
