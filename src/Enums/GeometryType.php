<?php

namespace PlinCode\KmlParser\Enums;

enum GeometryType: string
{
    case POINT = 'Point';
    case LINE_STRING = 'LineString';
    case POLYGON = 'Polygon';
    case MULTI_GEOMETRY = 'MultiGeometry';

    /**
     * Get all values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
