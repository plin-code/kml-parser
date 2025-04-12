<?php

use PlinCode\KmlParser\Exceptions\KmlParserException;
use PlinCode\KmlParser\Exceptions\KmzExtractorException;
use PlinCode\KmlParser\KmlParser;
use PlinCode\KmlParser\KmzExtractor;

it('throws exception when KML file not found', function () {
    $parser = new KmlParser;
    
    $parser->loadFromFile('non-existent.kml');
})->throws(KmlParserException::class, 'KML file not found: non-existent.kml');

it('throws exception when no KML data loaded', function () {
    $parser = new KmlParser;
    
    $parser->getPlacemarks();
})->throws(KmlParserException::class, 'No KML data loaded');

it('throws exception on invalid XML', function () {
    $parser = new KmlParser;
    
    $parser->loadFromString('invalid xml content');
})->throws(KmlParserException::class, 'Failed to parse KML content');

it('throws exception when KMZ file not found', function () {
    $extractor = new KmzExtractor;
    
    $extractor->extractKmlContent('non-existent.kmz');
})->throws(KmzExtractorException::class, 'KMZ file not found: non-existent.kmz');

it('throws exception when KMZ file is invalid', function () {
    $extractor = new KmzExtractor;
    
    // Create an empty file
    file_put_contents(__DIR__.'/files/invalid.kmz', 'not a zip file');
    
    $extractor->extractKmlContent(__DIR__.'/files/invalid.kmz');
})->throws(KmzExtractorException::class, 'Invalid KMZ file');

it('throws exception when no KML found in KMZ', function () {
    $extractor = new KmzExtractor;
    
    // Create a valid zip file without KML
    $zip = new ZipArchive;
    $zip->open(__DIR__.'/files/no-kml.kmz', ZipArchive::CREATE);
    $zip->addFromString('test.txt', 'test content');
    $zip->close();
    
    $extractor->extractKmlContent(__DIR__.'/files/no-kml.kmz');
})->throws(KmzExtractorException::class, 'No KML file found in KMZ archive');

afterEach(function () {
    // Cleanup test files
    @unlink(__DIR__.'/files/invalid.kmz');
    @unlink(__DIR__.'/files/no-kml.kmz');
}); 