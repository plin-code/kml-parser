<?php

use PlinCode\KmlParser\Exceptions\KmlException;
use PlinCode\KmlParser\KmlParser;
use PlinCode\KmlParser\Validators\KmlValidator;

function kmlIn(string $namespace): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="{$namespace}">
    <Document>
        <name>Legacy export</name>
        <Placemark>
            <name>Lago Blu</name>
            <Point>
                <coordinates>7.7300965,45.8635629,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;
}

it('parses a document in the OGC 2.2 namespace', function () {
    $parser = (new KmlParser)->loadFromString(kmlIn('http://www.opengis.net/kml/2.2'));

    expect($parser->getPlacemarks())->toHaveCount(1)
        ->and($parser->getDocumentName())->toBe('Legacy export');
});

it('parses documents in the legacy Google namespaces', function (string $namespace) {
    $parser = (new KmlParser)->loadFromString(kmlIn($namespace));

    expect($parser->getPlacemarks())->toHaveCount(1)
        ->and($parser->getPlacemarks()[0]['name'])->toBe('Lago Blu')
        ->and($parser->getDocumentName())->toBe('Legacy export');
})->with([
    'http://earth.google.com/kml/2.2',
    'http://earth.google.com/kml/2.1',
    'http://earth.google.com/kml/2.0',
]);

it('still rejects a namespace that is not KML', function () {
    expect(fn () => (new KmlParser)->loadFromString(kmlIn('http://wrong.namespace')))
        ->toThrow(KmlException::class, 'Invalid or missing KML namespace');
});

it('accepts a namespace added through the config', function () {
    config()->set('kml-parser.supported_namespaces', ['http://example.test/kml']);

    $parser = (new KmlParser)->loadFromString(kmlIn('http://example.test/kml'));

    expect($parser->getPlacemarks())->toHaveCount(1);
});

it('keeps accepting the configured primary namespace', function () {
    config()->set('kml-parser.namespace', 'http://example.test/kml');
    config()->set('kml-parser.supported_namespaces', []);

    $parser = (new KmlParser)->loadFromString(kmlIn('http://example.test/kml'));

    expect($parser->getPlacemarks())->toHaveCount(1);
});

it('exposes the namespace the document declared', function () {
    $validator = new KmlValidator;
    $validator->validateDocument(new SimpleXMLElement(kmlIn('http://earth.google.com/kml/2.1')));

    expect($validator->documentNamespace())->toBe('http://earth.google.com/kml/2.1');
});
