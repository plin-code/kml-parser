<?php

namespace PlinCode\KmlParser;

use PlinCode\KmlParser\Exceptions\KmzExtractorException;
use ZipArchive;

class KmzExtractor
{
    /**
     * Extract KML content from a KMZ file
     */
    public function extractKmlContent(string $path): string
    {
        if (! file_exists($path)) {
            throw KmzExtractorException::fileNotFound($path);
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw KmzExtractorException::invalidZipFile($path);
        }

        try {
            $kmlFiles = array_filter(
                array_map(
                    fn (int $i) => $zip->getNameIndex($i),
                    range(0, $zip->numFiles - 1)
                ),
                fn (string $filename) => pathinfo($filename, PATHINFO_EXTENSION) === 'kml'
            );

            if (empty($kmlFiles)) {
                throw KmzExtractorException::noKmlFound();
            }

            $kmlContent = $zip->getFromIndex(array_key_first($kmlFiles));
            if ($kmlContent === false) {
                throw KmzExtractorException::failedToExtract('Failed to read KML file from archive');
            }

            return $kmlContent;
        } finally {
            $zip->close();
        }
    }

    /**
     * Extract all files from KMZ to a directory
     *
     * @throws Exception
     */
    public function extractAllFiles(string $kmzPath, string $destination): array
    {
        if (! file_exists($kmzPath)) {
            throw new Exception("KMZ file not found: {$kmzPath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($kmzPath) !== true) {
            throw new Exception("Unable to open KMZ file: {$kmzPath}");
        }

        if (! file_exists($destination)) {
            mkdir($destination, 0755, true);
        }

        $zip->extractTo($destination);

        $extractedFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $extractedFiles[] = $zip->getNameIndex($i);
        }

        $zip->close();

        return $extractedFiles;
    }
}
