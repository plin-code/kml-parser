<?php

use PlinCode\KmlParser\KmlParser;

$styledKml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Style id="route">
            <LineStyle>
                <color>ff0000ff</color>
                <width>4</width>
            </LineStyle>
        </Style>
        <Style id="area">
            <LineStyle>
                <color>ff00ff00</color>
            </LineStyle>
            <PolyStyle>
                <color>7f00ff00</color>
                <fill>0</fill>
                <outline>1</outline>
            </PolyStyle>
        </Style>
        <Placemark>
            <name>Line</name>
            <styleUrl>#route</styleUrl>
            <LineString>
                <coordinates>7.1,45.1,0 7.2,45.2,0</coordinates>
            </LineString>
        </Placemark>
    </Document>
</kml>
XML;

it('parses the stroke declared by a LineStyle', function () use ($styledKml) {
    $style = (new KmlParser)->loadFromString($styledKml)->getStyles()['route'];

    expect($style['lineStyle'])->toBe([
        'color' => 'ff0000ff',
        'width' => 4.0,
    ]);
});

it('parses the fill declared by a PolyStyle', function () use ($styledKml) {
    $style = (new KmlParser)->loadFromString($styledKml)->getStyles()['area'];

    expect($style['polyStyle'])->toBe([
        'color' => '7f00ff00',
        'fill' => false,
        'outline' => true,
    ]);
});

it('reports an explicit fill of zero instead of dropping it', function () use ($styledKml) {
    $style = (new KmlParser)->loadFromString($styledKml)->getStyles()['area'];

    expect($style['polyStyle'])->toHaveKey('fill')
        ->and($style['polyStyle']['fill'])->toBeFalse();
});

it('omits the elements a style does not declare', function () use ($styledKml) {
    $styles = (new KmlParser)->loadFromString($styledKml)->getStyles();

    expect($styles['route'])->not->toHaveKey('polyStyle')
        ->and($styles['route'])->not->toHaveKey('iconStyle')
        ->and($styles['route'])->not->toHaveKey('labelStyle')
        ->and($styles['area']['lineStyle'])->toBe(['color' => 'ff00ff00'])
        ->and($styles['area']['lineStyle'])->not->toHaveKey('width');
});

it('keeps parsing IconStyle and LabelStyle alongside the new ones', function () {
    $kml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Style id="pin">
            <IconStyle>
                <scale>1.4</scale>
                <Icon><href>images/icon-1.png</href></Icon>
            </IconStyle>
            <LabelStyle>
                <scale>0.5</scale>
                <color>ff112233</color>
            </LabelStyle>
            <LineStyle>
                <width>2</width>
            </LineStyle>
        </Style>
        <Placemark>
            <Point><coordinates>7.1,45.1,0</coordinates></Point>
        </Placemark>
    </Document>
</kml>
XML;

    $style = (new KmlParser)->loadFromString($kml)->getStyles()['pin'];

    expect($style['iconStyle'])->toBe(['scale' => 1.4, 'href' => 'images/icon-1.png'])
        ->and($style['labelStyle'])->toBe(['scale' => 0.5, 'color' => 'ff112233'])
        ->and($style['lineStyle'])->toBe(['width' => 2.0]);
});
