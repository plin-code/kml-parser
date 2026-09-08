<?php

use PlinCode\KmlParser\KmlParser;

function kmlWithCoordinates(string $coordinates): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <Point>
                <coordinates>{$coordinates}</coordinates>
            </Point>
        </Placemark>
        <Placemark>
            <LineString>
                <coordinates>{$coordinates} 7.9,45.9</coordinates>
            </LineString>
        </Placemark>
    </Document>
</kml>
XML;
}

it('returns a float altitude when the coordinate omits it', function () {
    $placemarks = (new KmlParser)->loadFromString(kmlWithCoordinates('7.7,45.8'))->getPlacemarks();

    expect($placemarks[0]['coordinates']['altitude'])->toBeFloat()
        ->and($placemarks[1]['coordinates'][0]['altitude'])->toBeFloat()
        ->and($placemarks[1]['coordinates'][1]['altitude'])->toBeFloat();
});

it('returns a float altitude when the coordinate declares it', function () {
    $placemark = (new KmlParser)->loadFromString(kmlWithCoordinates('7.7,45.8,12'))->getPlacemarks()[0];

    expect($placemark['coordinates']['altitude'])->toBeFloat()
        ->and($placemark['coordinates']['altitude'])->toBe(12.0);
});

it('keeps the altitude a float through to GeoJSON', function () {
    $geometry = (new KmlParser)->loadFromString(kmlWithCoordinates('7.7,45.8'))
        ->toGeoJson()['features'][0]['geometry'];

    expect($geometry['coordinates'][2])->toBeFloat()
        ->and($geometry['coordinates'])->toBe([7.7, 45.8, 0.0]);
});
