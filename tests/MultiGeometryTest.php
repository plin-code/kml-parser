<?php

use PlinCode\KmlParser\Exceptions\KmlException;
use PlinCode\KmlParser\KmlParser;

$multiGeometryKml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <name>Mixed</name>
            <description>One placemark, three geometries</description>
            <MultiGeometry>
                <Point>
                    <coordinates>7.7,45.8,10</coordinates>
                </Point>
                <LineString>
                    <coordinates>7.1,45.1,0 7.2,45.2,0</coordinates>
                </LineString>
                <Polygon>
                    <outerBoundaryIs>
                        <LinearRing>
                            <coordinates>7.0,45.0,0 7.1,45.0,0 7.1,45.1,0 7.0,45.0,0</coordinates>
                        </LinearRing>
                    </outerBoundaryIs>
                </Polygon>
            </MultiGeometry>
        </Placemark>
    </Document>
</kml>
XML;

it('loads a document containing a MultiGeometry', function () use ($multiGeometryKml) {
    expect(fn () => (new KmlParser)->loadFromString($multiGeometryKml))
        ->not->toThrow(KmlException::class);
});

it('parses every geometry nested in a MultiGeometry', function () use ($multiGeometryKml) {
    $placemark = (new KmlParser)->loadFromString($multiGeometryKml)->getPlacemarks()[0];

    expect($placemark['name'])->toBe('Mixed')
        ->and($placemark['type'])->toBe('MultiGeometry')
        ->and($placemark)->not->toHaveKey('coordinates')
        ->and($placemark['geometries'])->toHaveCount(3);

    [$point, $line, $polygon] = $placemark['geometries'];

    expect($point['type'])->toBe('Point')
        ->and($point['coordinates'])->toBe(['longitude' => 7.7, 'latitude' => 45.8, 'altitude' => 10.0])
        ->and($line['type'])->toBe('LineString')
        ->and($line['coordinates'])->toHaveCount(2)
        ->and($polygon['type'])->toBe('Polygon')
        ->and($polygon['coordinates']['outerBoundary'])->toHaveCount(4)
        ->and($polygon['coordinates']['innerBoundaries'])->toBe([]);
});

it('emits a MultiGeometry as a GeoJSON GeometryCollection', function () use ($multiGeometryKml) {
    $feature = (new KmlParser)->loadFromString($multiGeometryKml)->toGeoJson()['features'][0];

    expect($feature['geometry']['type'])->toBe('GeometryCollection')
        ->and($feature['geometry']['geometries'])->toHaveCount(3)
        ->and($feature['geometry']['geometries'][0])->toBe([
            'type' => 'Point',
            'coordinates' => [7.7, 45.8, 10.0],
        ])
        ->and($feature['geometry']['geometries'][1]['coordinates'])->toBe([
            [7.1, 45.1, 0.0],
            [7.2, 45.2, 0.0],
        ])
        ->and($feature['geometry']['geometries'][2]['type'])->toBe('Polygon')
        ->and($feature['geometry']['geometries'][2]['coordinates'])->toHaveCount(1);
});

it('handles a MultiGeometry nested inside another one', function () {
    $nested = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <MultiGeometry>
                <MultiGeometry>
                    <Point>
                        <coordinates>7.7,45.8,0</coordinates>
                    </Point>
                </MultiGeometry>
            </MultiGeometry>
        </Placemark>
    </Document>
</kml>
XML;

    $geometry = (new KmlParser)->loadFromString($nested)->toGeoJson()['features'][0]['geometry'];

    expect($geometry['type'])->toBe('GeometryCollection')
        ->and($geometry['geometries'][0]['type'])->toBe('GeometryCollection')
        ->and($geometry['geometries'][0]['geometries'][0]['type'])->toBe('Point');
});

it('rejects a MultiGeometry that holds no geometry', function () {
    $empty = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <MultiGeometry></MultiGeometry>
        </Placemark>
    </Document>
</kml>
XML;

    expect(fn () => (new KmlParser)->loadFromString($empty))
        ->toThrow(KmlException::class, 'Found MultiGeometry without any geometry');
});

it('still validates the coordinates nested in a MultiGeometry', function () {
    $badLatitude = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <MultiGeometry>
                <Point>
                    <coordinates>7.7,91,0</coordinates>
                </Point>
            </MultiGeometry>
        </Placemark>
    </Document>
</kml>
XML;

    expect(fn () => (new KmlParser)->loadFromString($badLatitude))
        ->toThrow(KmlException::class, 'Invalid latitude value: 91');
});
