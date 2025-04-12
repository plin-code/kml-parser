<?php

namespace PlinCode\KmlParser\Exceptions;

class KmlParserException extends KmlException
{
    public static function fileNotFound(string $path): self
    {
        return new self("KML file not found: {$path}");
    }

    public static function noDataLoaded(): self
    {
        return new self('No KML data loaded');
    }

    public static function invalidXml(string $message): self
    {
        return new self("XML parsing error: {$message}");
    }

    public static function failedToParse(string $message): self
    {
        return new self("Failed to parse KML content: {$message}");
    }
} 