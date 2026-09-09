<?php

use PlinCode\KmlParser\KmlParser;

function foldersKml(string $namespace = 'http://www.opengis.net/kml/2.2'): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="{$namespace}">
    <Document>
        <name>Regions</name>
        <Placemark>
            <name>Loose</name>
            <Point><coordinates>7.0,45.0,0</coordinates></Point>
        </Placemark>
        <Folder>
            <name>Piemonte</name>
            <Placemark>
                <name>Torino</name>
                <Point><coordinates>7.1,45.1,0</coordinates></Point>
            </Placemark>
            <Folder>
                <name>Laghi</name>
                <Placemark>
                    <name>Lago Blu</name>
                    <Point><coordinates>7.2,45.2,0</coordinates></Point>
                </Placemark>
            </Folder>
            <Folder>
                <Placemark>
                    <name>Unnamed folder</name>
                    <Point><coordinates>7.3,45.3,0</coordinates></Point>
                </Placemark>
            </Folder>
        </Folder>
        <Folder>
            <name>Liguria</name>
            <Placemark>
                <name>Genova</name>
                <Point><coordinates>8.9,44.4,0</coordinates></Point>
            </Placemark>
        </Folder>
    </Document>
</kml>
XML;
}

function folderPaths(string $kml): array
{
    $placemarks = (new KmlParser)->loadFromString($kml)->getPlacemarks();

    return array_combine(
        array_column($placemarks, 'name'),
        array_column($placemarks, 'folder'),
    );
}

it('reports an empty path for a placemark outside every folder', function () {
    expect(folderPaths(foldersKml())['Loose'])->toBe([]);
});

it('reports the folder a placemark sits in', function () {
    expect(folderPaths(foldersKml())['Torino'])->toBe(['Piemonte']);
});

it('reports nested folders outermost first', function () {
    expect(folderPaths(foldersKml())['Lago Blu'])->toBe(['Piemonte', 'Laghi']);
});

it('keeps the depth when a folder has no name', function () {
    expect(folderPaths(foldersKml())['Unnamed folder'])->toBe(['Piemonte', '']);
});

it('keeps sibling folders apart', function () {
    expect(folderPaths(foldersKml())['Genova'])->toBe(['Liguria']);
});

it('still returns every placemark in document order', function () {
    $placemarks = (new KmlParser)->loadFromString(foldersKml())->getPlacemarks();

    expect(array_column($placemarks, 'name'))
        ->toBe(['Loose', 'Torino', 'Lago Blu', 'Unnamed folder', 'Genova']);
});

it('resolves folders in a legacy namespace too', function () {
    expect(folderPaths(foldersKml('http://earth.google.com/kml/2.1'))['Lago Blu'])
        ->toBe(['Piemonte', 'Laghi']);
});

it('carries the folder into the GeoJSON properties', function () {
    $features = (new KmlParser)->loadFromString(foldersKml())->toGeoJson()['features'];

    $byName = array_combine(array_column(array_column($features, 'properties'), 'name'), $features);

    expect($byName['Lago Blu']['properties']['folder'])->toBe(['Piemonte', 'Laghi']);
});

it('leaves the folder out of the GeoJSON properties when there is none', function () {
    $features = (new KmlParser)->loadFromString(foldersKml())->toGeoJson()['features'];

    expect($features[0]['properties'])->toBe([
        'name' => 'Loose',
        'description' => '',
    ]);
});
