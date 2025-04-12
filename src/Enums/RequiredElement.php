<?php

namespace PlinCode\KmlParser\Enums;

enum RequiredElement: string
{
    case KML = 'kml';
    case DOCUMENT = 'Document';

    /**
     * Get all values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $element) => $element->value, self::cases());
    }
}
