<?php

namespace PlinCode\KmlParser\Traits;

use SimpleXMLElement;

trait ParsesCoordinates
{
    protected function parseLineStringCoordinates(string $coordinates): array
    {
        $coords = [];
        $points = preg_split('/\s+/', trim($coordinates));

        foreach ($points as $point) {
            if (empty(trim($point))) {
                continue;
            }

            $parts = explode(',', trim($point));
            if (count($parts) >= 2) {
                $coords[] = [
                    'longitude' => (float) $parts[0],
                    'latitude' => (float) $parts[1],
                    'altitude' => isset($parts[2]) ? (float) $parts[2] : 0,
                ];
            }
        }

        return $coords;
    }

    protected function parsePolygonCoordinates(SimpleXMLElement $polygon): array
    {
        $result = [
            'outerBoundary' => [],
            'innerBoundaries' => [],
        ];

        if ($polygon->outerBoundaryIs && $polygon->outerBoundaryIs->LinearRing) {
            $coordinates = (string) $polygon->outerBoundaryIs->LinearRing->coordinates;
            $result['outerBoundary'] = $this->parseLineStringCoordinates($coordinates);
        }

        foreach ($polygon->innerBoundaryIs as $innerBoundary) {
            if ($innerBoundary->LinearRing) {
                $coordinates = (string) $innerBoundary->LinearRing->coordinates;
                $result['innerBoundaries'][] = $this->parseLineStringCoordinates($coordinates);
            }
        }

        return $result;
    }
}
