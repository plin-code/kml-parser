<?php

use Illuminate\Container\Container;
use PlinCode\KmlParser\KmlParser;

function withoutBoundConfig(callable $callback): mixed
{
    $application = Container::getInstance();

    Container::setInstance(new Container);

    try {
        return $callback();
    } finally {
        Container::setInstance($application);
    }
}

$kml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <name>Standalone</name>
        <Placemark>
            <name>Lago Blu</name>
            <Point>
                <coordinates>7.7300965,45.8635629,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;

it('can be constructed with no config repository bound', function () {
    expect(withoutBoundConfig(fn () => new KmlParser))->toBeInstanceOf(KmlParser::class);
});

it('parses with no config repository bound', function () use ($kml) {
    $placemarks = withoutBoundConfig(fn () => (new KmlParser)->loadFromString($kml)->getPlacemarks());

    expect($placemarks)->toHaveCount(1)
        ->and($placemarks[0]['name'])->toBe('Lago Blu');
});

it('falls back to the default namespaces with no config repository bound', function () {
    $legacy = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://earth.google.com/kml/2.1">
    <Document>
        <Placemark>
            <Point>
                <coordinates>7.7,45.8,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;

    $placemarks = withoutBoundConfig(fn () => (new KmlParser)->loadFromString($legacy)->getPlacemarks());

    expect($placemarks)->toHaveCount(1);
});

it('still reads the config when the application provides one', function () {
    config()->set('kml-parser.supported_namespaces', ['http://example.test/kml']);

    $kml = str_replace('http://www.opengis.net/kml/2.2', 'http://example.test/kml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <Point>
                <coordinates>7.7,45.8,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML);

    expect((new KmlParser)->loadFromString($kml)->getPlacemarks())->toHaveCount(1);
});
