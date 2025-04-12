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
} 