<?php

use PlinCode\KmlParser\Exceptions\KmzExtractorException;
use PlinCode\KmlParser\KmzExtractor;

function tempPath(string $suffix): string
{
    return sys_get_temp_dir().'/kml-parser-test-'.uniqid().$suffix;
}

function makeArchive(callable $build): string
{
    $path = tempPath('.kmz');

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $build($zip);
    $zip->close();

    return $path;
}

function validKml(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
    <Document>
        <Placemark><Point><coordinates>7.7,45.8,0</coordinates></Point></Placemark>
    </Document>
</kml>
XML;
}

function removeRecursively(string $path): void
{
    if (is_dir($path)) {
        foreach (glob($path.'/*') ?: [] as $child) {
            removeRecursively($child);
        }

        @rmdir($path);

        return;
    }

    @unlink($path);
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/kml-parser-test-*') ?: [] as $path) {
        removeRecursively($path);
    }
});

it('rejects an archive with more entries than allowed', function () {
    config()->set('kml-parser.max_archive_entries', 3);

    $path = makeArchive(function (ZipArchive $zip) {
        $zip->addFromString('doc.kml', validKml());
        foreach (range(1, 5) as $i) {
            $zip->addFromString("images/icon-{$i}.png", 'x');
        }
    });

    expect(fn () => (new KmzExtractor)->extractKmlContent($path))
        ->toThrow(KmzExtractorException::class, 'more than the 3 allowed');
});

it('rejects an archive that expands beyond the size limit', function () {
    config()->set('kml-parser.max_uncompressed_size', 1024);

    $path = makeArchive(function (ZipArchive $zip) {
        $zip->addFromString('doc.kml', validKml());
        $zip->addFromString('big.bin', str_repeat('a', 4096));
    });

    expect(fn () => (new KmzExtractor)->extractKmlContent($path))
        ->toThrow(KmzExtractorException::class, 'expands to more than the 1024 bytes allowed');
});

it('rejects an entry that would escape the destination', function (string $name) {
    $path = makeArchive(function (ZipArchive $zip) use ($name) {
        $zip->addFromString('doc.kml', validKml());
        $zip->addFromString($name, 'x');
    });

    expect(fn () => (new KmzExtractor)->extractAllFiles($path, tempPath('-dir')))
        ->toThrow(KmzExtractorException::class, 'would escape the destination');
})->with([
    '../escaped.txt',
    'images/../../escaped.txt',
    '/etc/passwd',
]);

it('accepts an ordinary archive', function () {
    $path = makeArchive(function (ZipArchive $zip) {
        $zip->addFromString('doc.kml', validKml());
        $zip->addFromString('images/icon.png', 'x');
    });

    expect((new KmzExtractor)->extractKmlContent($path))->toContain('<kml');
});

it('honours a limit of zero as no limit', function () {
    config()->set('kml-parser.max_archive_entries', 0);
    config()->set('kml-parser.max_uncompressed_size', 0);

    $path = makeArchive(function (ZipArchive $zip) {
        $zip->addFromString('doc.kml', validKml());
        $zip->addFromString('big.bin', str_repeat('a', 4096));
    });

    expect((new KmzExtractor)->extractKmlContent($path))->toContain('<kml');
});

it('extracts to the configured temp directory when given no destination', function () {
    $base = tempPath('-base');
    mkdir($base, 0755, true);
    config()->set('kml-parser.temp_directory', $base);

    $path = makeArchive(function (ZipArchive $zip) {
        $zip->addFromString('doc.kml', validKml());
    });

    $extractor = new KmzExtractor;
    $files = $extractor->extractAllFiles($path);

    expect($files)->toBe(['doc.kml'])
        ->and(glob($base.'/kml-parser-*/doc.kml'))->toHaveCount(1);
});

it('falls back to the system temp directory when none is configured', function () {
    config()->set('kml-parser.temp_directory', null);

    expect((new KmzExtractor)->defaultDestination())->toStartWith(sys_get_temp_dir().DIRECTORY_SEPARATOR.'kml-parser-');
});

it('reports a destination it cannot create', function () {
    $path = makeArchive(fn (ZipArchive $zip) => $zip->addFromString('doc.kml', validKml()));

    $blocker = tempPath('-blocker');
    file_put_contents($blocker, 'not a directory');

    expect(fn () => (new KmzExtractor)->extractAllFiles($path, $blocker.'/inside'))
        ->toThrow(KmzExtractorException::class, 'Unable to create the extraction directory');
});

it('falls back to the documented default when a limit is not a number', function () {
    config()->set('kml-parser.max_archive_entries', 'plenty');

    $path = makeArchive(function (ZipArchive $zip) {
        $zip->addFromString('doc.kml', validKml());
    });

    expect((new KmzExtractor)->extractKmlContent($path))->toContain('<kml');
});
