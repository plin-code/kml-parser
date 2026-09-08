<?php

use PlinCode\KmlParser\Exceptions\KmlException;
use PlinCode\KmlParser\Exceptions\KmlParserException;
use PlinCode\KmlParser\KmlParser;
use PlinCode\KmlParser\Validators\KmlValidator;

function kmlWithLatitude(string $latitude): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark>
            <Point>
                <coordinates>7.7300965,{$latitude},0</coordinates>
            </Point>
        </Placemark>
    </Document>
</kml>
XML;
}

it('surfaces a validation failure with its own type and message', function () {
    try {
        (new KmlParser)->loadFromString(kmlWithLatitude('91'));
    } catch (KmlException $e) {
        expect($e)->not->toBeInstanceOf(KmlParserException::class)
            ->and($e->getMessage())->toBe('Invalid latitude value: 91');

        return;
    }

    $this->fail('No exception was thrown.');
});

it('reports malformed XML as a parsing error', function () {
    try {
        (new KmlParser)->loadFromString('<kml><unclosed>');
    } catch (KmlParserException $e) {
        expect($e->getMessage())->toStartWith('XML parsing error: ');

        return;
    }

    $this->fail('No exception was thrown.');
});

it('restores the libxml error handling mode after a successful load', function () {
    $before = libxml_use_internal_errors(false);

    (new KmlParser)->loadFromString(kmlWithLatitude('45.8635629'));

    expect(libxml_use_internal_errors($before))->toBeFalse();
});

it('restores the libxml error handling mode after a failed load', function () {
    $before = libxml_use_internal_errors(false);

    try {
        (new KmlParser)->loadFromString('<kml><unclosed>');
    } catch (KmlParserException) {
        // The state has to be restored on the failure path too.
    }

    expect(libxml_use_internal_errors($before))->toBeFalse();
});

it('validates an already parsed document without reparsing it', function () {
    $xml = new SimpleXMLElement(kmlWithLatitude('91'));

    expect(fn () => (new KmlValidator)->validateDocument($xml))
        ->toThrow(KmlException::class, 'Invalid latitude value: 91');
});
