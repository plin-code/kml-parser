<?php

use PlinCode\KmlParser\KmlParser;

$shapesKml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <name>Line</name>
            <description>A line</description>
            <styleUrl>#shared</styleUrl>
            <LineString>
                <coordinates>7.1,45.1,5 7.2,45.2,6</coordinates>
            </LineString>
        </Placemark>
        <Placemark>
            <name>Ring</name>
            <description>A polygon with a hole</description>
            <ExtendedData>
                <Data name="area"><value>12</value></Data>
            </ExtendedData>
            <Polygon>
                <outerBoundaryIs>
                    <LinearRing>
                        <coordinates>7.0,45.0,0 7.4,45.0,0 7.4,45.4,0 7.0,45.0,0</coordinates>
                    </LinearRing>
                </outerBoundaryIs>
                <innerBoundaryIs>
                    <LinearRing>
                        <coordinates>7.1,45.1,0 7.2,45.1,0 7.2,45.2,0 7.1,45.1,0</coordinates>
                    </LinearRing>
                </innerBoundaryIs>
            </Polygon>
        </Placemark>
    </Document>
</kml>
XML;

it('emits a LineString feature unchanged', function () use ($shapesKml) {
    $feature = (new KmlParser)->loadFromString($shapesKml)->toGeoJson()['features'][0];

    expect($feature)->toBe([
        'type' => 'Feature',
        'properties' => [
            'name' => 'Line',
            'description' => 'A line',
            'styleUrl' => '#shared',
        ],
        'geometry' => [
            'type' => 'LineString',
            'coordinates' => [
                [7.1, 45.1, 5.0],
                [7.2, 45.2, 6.0],
            ],
        ],
    ]);
});

it('emits a Polygon feature with the outer ring first', function () use ($shapesKml) {
    $feature = (new KmlParser)->loadFromString($shapesKml)->toGeoJson()['features'][1];

    expect($feature)->toBe([
        'type' => 'Feature',
        'properties' => [
            'name' => 'Ring',
            'description' => 'A polygon with a hole',
            'extendedData' => ['area' => '12'],
        ],
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [
                [[7.0, 45.0, 0.0], [7.4, 45.0, 0.0], [7.4, 45.4, 0.0], [7.0, 45.0, 0.0]],
                [[7.1, 45.1, 0.0], [7.2, 45.1, 0.0], [7.2, 45.2, 0.0], [7.1, 45.1, 0.0]],
            ],
        ],
    ]);
});

it('parses the polygon boundaries into outer and inner sets', function () use ($shapesKml) {
    $placemark = (new KmlParser)->loadFromString($shapesKml)->getPlacemarks()[1];

    expect($placemark['type'])->toBe('Polygon')
        ->and($placemark['coordinates']['outerBoundary'])->toHaveCount(4)
        ->and($placemark['coordinates']['innerBoundaries'])->toHaveCount(1)
        ->and($placemark['coordinates']['innerBoundaries'][0])->toHaveCount(4);
});
