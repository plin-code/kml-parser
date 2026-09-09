<?php

namespace PlinCode\KmlParser;

use PlinCode\KmlParser\Exceptions\KmzExtractorException;
use PlinCode\KmlParser\Traits\ReadsPackageConfig;
use ZipArchive;

class KmzExtractor
{
    use ReadsPackageConfig;

    /**
     * Ceilings applied to an archive before anything is read out of it.
     *
     * A KMZ is a ZIP, and a ZIP can declare a handful of entries that expand
     * into far more than the machine has. These are deliberately generous: a
     * real KMZ is a KML plus its icons, nowhere near either limit.
     */
    public const DEFAULT_MAX_ENTRIES = 5000;

    public const DEFAULT_MAX_UNCOMPRESSED_SIZE = 268435456; // 256 MB

    /**
     * Extract KML content from a KMZ file
     *
     * @throws KmzExtractorException
     */
    public function extractKmlContent(string $path): string
    {
        $zip = $this->open($path);

        try {
            $this->guardArchive($zip);

            $kmlIndex = $this->firstKmlIndex($zip);

            if ($kmlIndex === null) {
                throw KmzExtractorException::noKmlFound();
            }

            $kmlContent = $zip->getFromIndex($kmlIndex);

            if ($kmlContent === false) {
                throw KmzExtractorException::failedToExtract('Failed to read KML file from archive');
            }

            return $kmlContent;
        } finally {
            $zip->close();
        }
    }

    /**
     * Extract all files from KMZ archive
     *
     * Without a destination the files go to the configured temp_directory, or
     * to the system temp directory, in a directory of their own.
     *
     * @return array<int, string> the entry names that were written
     *
     * @throws KmzExtractorException
     */
    public function extractAllFiles(string $kmzPath, ?string $destination = null): array
    {
        $destination ??= $this->defaultDestination();

        $zip = $this->open($kmzPath);

        try {
            $this->guardArchive($zip);

            /*
             * The warning mkdir() raises carries less than the exception
             * thrown below, and an application turning warnings into
             * exceptions would otherwise get that one instead of ours. The
             * return value is what is acted on, the second is_dir() covers
             * another process winning the race.
             */
            if (! is_dir($destination) && ! @mkdir($destination, 0755, true) && ! is_dir($destination)) {
                throw KmzExtractorException::destinationNotWritable($destination);
            }

            if (! $zip->extractTo($destination)) {
                throw KmzExtractorException::failedToExtract("Unable to extract the archive into {$destination}");
            }

            return $this->entryNames($zip);
        } finally {
            $zip->close();
        }
    }

    /**
     * The directory extractAllFiles() writes to when the caller names none.
     */
    public function defaultDestination(): string
    {
        $configured = $this->packageConfig('kml-parser.temp_directory', null);
        $base = is_string($configured) && $configured !== '' ? $configured : sys_get_temp_dir();

        return rtrim($base, '/\\').DIRECTORY_SEPARATOR.'kml-parser-'.uniqid();
    }

    /**
     * @throws KmzExtractorException
     */
    protected function open(string $path): ZipArchive
    {
        if (! file_exists($path)) {
            throw KmzExtractorException::fileNotFound($path);
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw KmzExtractorException::invalidZipFile($path);
        }

        return $zip;
    }

    /**
     * Reject an archive that is too large to trust before reading anything out
     * of it, and reject any entry whose name would escape the destination.
     *
     * @throws KmzExtractorException
     */
    protected function guardArchive(ZipArchive $zip): void
    {
        $maxEntries = $this->configuredLimit('kml-parser.max_archive_entries', self::DEFAULT_MAX_ENTRIES);
        $maxSize = $this->configuredLimit('kml-parser.max_uncompressed_size', self::DEFAULT_MAX_UNCOMPRESSED_SIZE);

        if ($maxEntries > 0 && $zip->numFiles > $maxEntries) {
            throw KmzExtractorException::tooManyEntries($zip->numFiles, $maxEntries);
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                throw KmzExtractorException::failedToExtract("Unable to read entry {$i} of the archive");
            }

            $this->guardEntryName((string) $stat['name']);

            $total += (int) $stat['size'];

            if ($maxSize > 0 && $total > $maxSize) {
                throw KmzExtractorException::archiveTooLarge($maxSize);
            }
        }
    }

    /**
     * A limit that is not a number is a misconfiguration, and silently reading
     * it as 0 would turn the limit off, which is the opposite of what someone
     * setting it wants. The documented default is used instead.
     */
    protected function configuredLimit(string $key, int $default): int
    {
        $value = $this->packageConfig($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @throws KmzExtractorException
     */
    protected function guardEntryName(string $name): void
    {
        if (str_starts_with($name, '/') || str_starts_with($name, '\\') || preg_match('#^[A-Za-z]:[\\\\/]#', $name) === 1) {
            throw KmzExtractorException::unsafeEntry($name);
        }

        foreach (preg_split('#[\\\\/]#', $name) ?: [] as $segment) {
            if ($segment === '..') {
                throw KmzExtractorException::unsafeEntry($name);
            }
        }
    }

    protected function firstKmlIndex(ZipArchive $zip): ?int
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'kml') {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function entryNames(ZipArchive $zip): array
    {
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
