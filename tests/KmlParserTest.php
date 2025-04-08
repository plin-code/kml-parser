<?php

use PlinCode\KmlParser\KmlParser;

beforeEach(function () {
    $this->parser = new KmlParser;
    $this->parser->loadFromFile(__DIR__.'/files/kml-example/base.kml');
});

it('can parse KML file', function () {
    $placemarks = $this->parser->getPlacemarks();
    expect($placemarks)->toBeArray()
        ->and($placemarks)->not->toBeEmpty();

    $firstPlacemark = $placemarks[0];

    expect($firstPlacemark)
        ->toHaveKeys(['name', 'description', 'type', 'coordinates'])
        ->and($firstPlacemark['type'])->toBe('Point')
        ->and($firstPlacemark['coordinates'])->toHaveKeys(['longitude', 'latitude', 'altitude']);
});

it('can parse KMZ file', function () {
    $this->parser->loadFromKmz(__DIR__.'/files/kml-example.kmz');
    $placemarks = $this->parser->getPlacemarks();

    expect($placemarks)->toBeArray()
        ->and($placemarks)->not->toBeEmpty();
});

it('can convert to GeoJSON', function () {
    $geoJson = $this->parser->toGeoJson();

    expect($geoJson)->toBeArray()
        ->and($geoJson['type'])->toBe('FeatureCollection')
        ->and($geoJson['features'])->toBeArray()
        ->and($geoJson['features'])->not->toBeEmpty();
});

it('can extract styles from KML', function () {
    $styles = $this->parser->getStyles();

    expect($styles)->toBeArray()
        ->and($styles)->not->toBeEmpty();
});

it('can extract style maps from KML', function () {
    $styleMaps = $this->parser->getStyleMaps();

    expect($styleMaps)->toBeArray();
});

it('throws exception when file not found', function () {
    $this->parser->loadFromFile('non-existent-file.kml');
})->throws(Exception::class);

it('throws exception when loading data before parsing', function () {
    $this->parser = new KmlParser;
    $this->parser->getPlacemarks();
})->throws(Exception::class);
