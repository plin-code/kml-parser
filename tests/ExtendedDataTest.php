<?php

use PlinCode\KmlParser\KmlParser;

function kmlWithExtendedData(string $extendedData): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <name>Sample</name>
            <ExtendedData>
{$extendedData}
            </ExtendedData>
            <Point>
                <coordinates>7.1,45.1,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;
}

it('parses Data pairs', function () {
    $kml = kmlWithExtendedData(<<<'XML'
                <Data name="area"><value>12</value></Data>
                <Data name="region"><value>Piemonte</value></Data>
XML);

    expect((new KmlParser)->loadFromString($kml)->getPlacemarks()[0]['extendedData'])
        ->toBe(['area' => '12', 'region' => 'Piemonte']);
});

it('parses SimpleData entries inside a SchemaData block', function () {
    $kml = kmlWithExtendedData(<<<'XML'
                <SchemaData schemaUrl="#sample">
                    <SimpleData name="area">12</SimpleData>
                    <SimpleData name="region">Piemonte</SimpleData>
                </SchemaData>
XML);

    expect((new KmlParser)->loadFromString($kml)->getPlacemarks()[0]['extendedData'])
        ->toBe(['area' => '12', 'region' => 'Piemonte']);
});

it('merges Data pairs and SimpleData entries', function () {
    $kml = kmlWithExtendedData(<<<'XML'
                <Data name="area"><value>12</value></Data>
                <SchemaData schemaUrl="#sample">
                    <SimpleData name="region">Piemonte</SimpleData>
                </SchemaData>
XML);

    expect((new KmlParser)->loadFromString($kml)->getPlacemarks()[0]['extendedData'])
        ->toBe(['area' => '12', 'region' => 'Piemonte']);
});

it('reads SimpleData across several SchemaData blocks', function () {
    $kml = kmlWithExtendedData(<<<'XML'
                <SchemaData schemaUrl="#one">
                    <SimpleData name="area">12</SimpleData>
                </SchemaData>
                <SchemaData schemaUrl="#two">
                    <SimpleData name="region">Piemonte</SimpleData>
                </SchemaData>
XML);

    expect((new KmlParser)->loadFromString($kml)->getPlacemarks()[0]['extendedData'])
        ->toBe(['area' => '12', 'region' => 'Piemonte']);
});

it('carries extended data through to GeoJSON properties', function () {
    $kml = kmlWithExtendedData(<<<'XML'
                <SchemaData schemaUrl="#sample">
                    <SimpleData name="area">12</SimpleData>
                </SchemaData>
XML);

    expect((new KmlParser)->loadFromString($kml)->toGeoJson()['features'][0]['properties'])
        ->toBe([
            'name' => 'Sample',
            'description' => '',
            'extendedData' => ['area' => '12'],
        ]);
});
