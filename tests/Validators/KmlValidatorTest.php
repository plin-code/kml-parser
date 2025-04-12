<?php

use PlinCode\KmlParser\Exceptions\KmlException;
use PlinCode\KmlParser\Validators\KmlValidator;

beforeEach(function () {
    $this->validator = new KmlValidator;
});

it('validates valid KML content', function () {
    $validKml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <Point>
                <coordinates>7.7300965,45.8635629,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;

    expect(fn () => $this->validator->validate($validKml))->not->toThrow(KmlException::class);
});

it('throws exception when required elements are missing', function () {
    $noDocument = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Placemark>
        <Point>
            <coordinates>7.7300965,45.8635629,0</coordinates>
        </Point>
    </Placemark>
</kml>
XML;

    expect(fn () => $this->validator->validate($noDocument))
        ->toThrow(KmlException::class, 'Missing required element: Document');
});

it('throws exception when Placemark has no geometry', function () {
    $noGeometry = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <name>Test Point</name>
        </Placemark>
    </Document>
</kml>
XML;

    expect(fn () => $this->validator->validate($noGeometry))
        ->toThrow(KmlException::class, 'Found Placemark without valid geometry');
});

it('throws exception on invalid coordinates', function () {
    $invalidCoordinates = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <Point>
                <coordinates>181,91,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;

    expect(fn () => $this->validator->validate($invalidCoordinates))
        ->toThrow(KmlException::class, 'Invalid longitude value: 181');
});

it('validates different geometry types', function () {
    $multiGeometry = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <Point>
                <coordinates>10.123456,45.123456,0</coordinates>
            </Point>
        </Placemark>
        <Placemark>
            <LineString>
                <coordinates>10.123456,45.123456,0 10.234567,45.234567,0</coordinates>
            </LineString>
        </Placemark>
        <Placemark>
            <Polygon>
                <outerBoundaryIs>
                    <LinearRing>
                        <coordinates>10.123456,45.123456,0 10.234567,45.234567,0 10.345678,45.345678,0 10.123456,45.123456,0</coordinates>
                    </LinearRing>
                </outerBoundaryIs>
            </Polygon>
        </Placemark>
    </Document>
</kml>
XML;

    expect(fn () => $this->validator->validate($multiGeometry))->not->toThrow(KmlException::class);
});

it('validates KML without Placemarks', function () {
    $noPlacemarks = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <name>Test Document</name>
    </Document>
</kml>
XML;

    expect(fn () => $this->validator->validate($noPlacemarks))->not->toThrow(KmlException::class);
});

it('throws exception on invalid namespace', function () {
    $invalidNamespace = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://wrong.namespace">
    <Document>
        <Placemark>
            <Point>
                <coordinates>7.7300965,45.8635629,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;

    expect(fn () => $this->validator->validate($invalidNamespace))
        ->toThrow(KmlException::class, 'Invalid or missing KML namespace');
});
