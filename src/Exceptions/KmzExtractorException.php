<?php

namespace PlinCode\KmlParser\Exceptions;

class KmzExtractorException extends KmlException
{
    public static function fileNotFound(string $path): self
    {
        return new self("KMZ file not found: {$path}");
    }

    public static function noKmlFound(): self
    {
        return new self('No KML file found in KMZ archive');
    }

    public static function failedToExtract(string $message): self
    {
        return new self("Failed to extract KMZ content: {$message}");
    }

    public static function invalidZipFile(string $path): self
    {
        return new self("Invalid KMZ file: {$path}");
    }

    public static function tooManyEntries(int $count, int $max): self
    {
        return new self("KMZ archive holds {$count} entries, more than the {$max} allowed");
    }

    public static function archiveTooLarge(int $max): self
    {
        return new self("KMZ archive expands to more than the {$max} bytes allowed");
    }

    public static function unsafeEntry(string $name): self
    {
        return new self("KMZ archive holds an entry that would escape the destination: {$name}");
    }

    public static function destinationNotWritable(string $path): self
    {
        return new self("Unable to create the extraction directory: {$path}");
    }
}
