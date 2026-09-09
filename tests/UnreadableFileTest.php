<?php

use PlinCode\KmlParser\Exceptions\KmlParserException;
use PlinCode\KmlParser\KmlParser;

it('reports a file it cannot read', function () {
    $path = sys_get_temp_dir().'/kml-parser-unreadable-'.uniqid().'.kml';
    file_put_contents($path, '<kml></kml>');
    chmod($path, 0000);

    try {
        expect(fn () => (new KmlParser)->loadFromFile($path))
            ->toThrow(KmlParserException::class, 'Unable to read KML file');
    } finally {
        chmod($path, 0644);
        unlink($path);
    }
})->skipOnWindows();

it('still reports a file that is not there at all', function () {
    expect(fn () => (new KmlParser)->loadFromFile('/no/such/file.kml'))
        ->toThrow(KmlParserException::class, 'KML file not found');
});
