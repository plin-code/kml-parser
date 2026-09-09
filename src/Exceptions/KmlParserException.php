<?php

namespace PlinCode\KmlParser\Exceptions;

class KmlParserException extends KmlException
{
    public static function fileNotFound(string $path): self
    {
        return new self("KML file not found: {$path}");
    }

    public static function failedToRead(string $path): self
    {
        return new self("Unable to read KML file: {$path}");
    }

    public static function noDataLoaded(): self
    {
        return new self('No KML data loaded');
    }

    public static function invalidXml(string $message): self
    {
        return new self("XML parsing error: {$message}");
    }

    /**
     * @deprecated Nothing throws this any more. Malformed XML now surfaces as
     *             invalidXml(), and a validation failure keeps its own
     *             KmlException instead of being wrapped. Kept for one cycle so
     *             callers referencing it do not break; slated for removal in
     *             the next major.
     */
    public static function failedToParse(string $message): self
    {
        return new self("Failed to parse KML content: {$message}");
    }
}
