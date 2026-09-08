<?php

use PlinCode\KmlParser\KmlParser;

function kmlWithStyles(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <name>0</name>
        <Style id="shared-red">
            <IconStyle>
                <scale>1.2</scale>
            </IconStyle>
        </Style>
        <Style id="shared-blue">
            <IconStyle>
                <scale>0.8</scale>
            </IconStyle>
        </Style>
        <Placemark>
            <name>0</name>
            <Style>
                <IconStyle>
                    <scale>3</scale>
                </IconStyle>
            </Style>
            <Point>
                <coordinates>7.7300965,45.8635629,0</coordinates>
            </Point>
        </Placemark>
        <Placemark>
            <name>Second</name>
            <Style>
                <IconStyle>
                    <scale>4</scale>
                </IconStyle>
            </Style>
            <Point>
                <coordinates>8.1,45.1,0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;
}

beforeEach(function () {
    $this->parser = (new KmlParser)->loadFromString(kmlWithStyles());
});

it('reads the placemark name from the name element', function () {
    expect($this->parser->getPlacemarks()[0]['name'])->toBe('0');
});

it('reads the document name from the name element', function () {
    expect($this->parser->getDocumentName())->toBe('0');
});

it('returns only styles that can be referenced by a styleUrl', function () {
    $styles = $this->parser->getStyles();

    expect($styles)->toHaveCount(2)
        ->and($styles)->toHaveKeys(['shared-red', 'shared-blue'])
        ->and($styles)->not->toHaveKey('')
        ->and($styles['shared-red']['iconStyle']['scale'])->toBe(1.2)
        ->and($styles['shared-blue']['iconStyle']['scale'])->toBe(0.8);
});
