<?php

namespace PlinCode\KmlParser;

use Exception;
use ZipArchive;

class KmzExtractor
{
    /**
     * Extract KML content from a KMZ file
     */
    public function extractKmlContent(string $kmzPath): string
    {
        if (! file_exists($kmzPath)) {
            throw new Exception("KMZ file not found: {$kmzPath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($kmzPath) !== true) {
            throw new Exception("Unable to open KMZ file: {$kmzPath}");
        }

        $kmlIndex = -1;
        $kmlFilename = basename($kmzPath, '.kmz').'.kml';

        if (($docKmlIndex = $zip->locateName('doc.kml', ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR)) !== false) {
            $kmlIndex = $docKmlIndex;
        } elseif (($kmlNameIndex = $zip->locateName($kmlFilename, ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR)) !== false) {
            $kmlIndex = $kmlNameIndex;
        } else {
            $kmlIndex = collect(range(0, $zip->numFiles - 1))
                ->map(fn ($i) => ['index' => $i, 'name' => $zip->getNameIndex($i)])
                ->filter(fn ($file) => pathinfo($file['name'], PATHINFO_EXTENSION) === 'kml')
                ->value('index', -1);
        }

        if ($kmlIndex === -1) {
            $zip->close();
            throw new Exception('No KML file found in KMZ archive');
        }

        $kmlContent = $zip->getFromIndex($kmlIndex);
        $zip->close();

        return $kmlContent;
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
