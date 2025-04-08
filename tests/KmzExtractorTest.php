<?php

use PlinCode\KmlParser\KmzExtractor;

it('can extract KML content from KMZ file', function () {
    $extractor = new KmzExtractor;
    $kmlContent = $extractor->extractKmlContent(__DIR__.'/files/kml-example.kmz');
    expect($kmlContent)->toBeString()
        ->and($kmlContent)->toContain('<?xml')
        ->and($kmlContent)->toContain('<kml');
});

it('can extract all files from KMZ', function () {
    $tempDir = sys_get_temp_dir().'/kmz-test-'.uniqid();

    $extractor = new KmzExtractor;
    $files = $extractor->extractAllFiles(__DIR__.'/files/kml-example.kmz', $tempDir);

    expect($files)->toBeArray()->and($files)->not->toBeEmpty();
});

it('throws exception when KMZ file not found', function () {
    $extractor = new KmzExtractor;
    $extractor->extractKmlContent('non-existent-file.kmz');
})->throws(Exception::class);
