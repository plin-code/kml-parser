<?php

namespace PlinCode\KmlParser\Validators;

use PlinCode\KmlParser\Enums\GeometryType;
use PlinCode\KmlParser\Exceptions\KmlException;
use SimpleXMLElement;

class KmlValidator
{
    protected string $namespace = 'http://www.opengis.net/kml/2.2';

    protected SimpleXMLElement $xml;

    /**
     * Parse and validate raw KML content.
     *
     * @throws KmlException
     */
    public function validate(string $content): void
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = new SimpleXMLElement($content);
        } catch (\Exception $e) {
            throw new KmlException('Invalid KML content: '.$e->getMessage());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $this->validateDocument($xml);
    }

    /**
     * Validate an already parsed KML document.
     *
     * Callers that have parsed the document themselves should use this instead
     * of validate(), so the content is not parsed twice.
     *
     * @throws KmlException
     */
    public function validateDocument(SimpleXMLElement $xml): void
    {
        $this->xml = $xml;

        $namespaces = $xml->getDocNamespaces();
        if (! isset($namespaces['']) || $namespaces[''] !== $this->namespace) {
            throw new KmlException('Invalid or missing KML namespace');
        }

        $xml->registerXPathNamespace('kml', $this->namespace);

        if (empty($xml->Document)) {
            throw new KmlException('Missing required element: Document');
        }

        foreach ($xml->xpath('//kml:Placemark') ?: [] as $placemark) {
            $this->validatePlacemark($placemark);
        }
    }

    protected function validatePlacemark(SimpleXMLElement $placemark): void
    {
        foreach (GeometryType::cases() as $type) {
            if ($placemark->{$type->value}) {
                $this->validateGeometry($placemark->{$type->value}, $type);

                return;
            }
        }

        throw new KmlException('Found Placemark without valid geometry');
    }

    protected function validateGeometry(SimpleXMLElement $geometry, GeometryType $type): void
    {
        if ($type === GeometryType::MULTI_GEOMETRY) {
            $this->validateMultiGeometry($geometry);

            return;
        }

        $this->validateGeometryCoordinates($geometry, $type->value);
    }

    /**
     * A MultiGeometry carries no coordinates of its own, only nested
     * geometries, and KML allows those to be MultiGeometry elements in turn.
     */
    protected function validateMultiGeometry(SimpleXMLElement $multiGeometry): void
    {
        $found = false;

        foreach (GeometryType::cases() as $type) {
            foreach ($multiGeometry->{$type->value} as $child) {
                $found = true;
                $this->validateGeometry($child, $type);
            }
        }

        if (! $found) {
            throw new KmlException('Found MultiGeometry without any geometry');
        }
    }

    protected function validateGeometryCoordinates(SimpleXMLElement $geometry, string $type): void
    {
        if ($type === 'Polygon') {
            if (empty($geometry->outerBoundaryIs->LinearRing->coordinates)) {
                throw new KmlException('Empty coordinates in Polygon geometry');
            }
            $coordinates = (string) $geometry->outerBoundaryIs->LinearRing->coordinates;
        } else {
            if (empty($geometry->coordinates)) {
                throw new KmlException('Empty coordinates in geometry');
            }
            $coordinates = (string) $geometry->coordinates;
        }

        if (empty(trim($coordinates))) {
            throw new KmlException('Empty coordinates in geometry');
        }

        $coords = preg_split('/\s+/', trim($coordinates));
        foreach ($coords as $coord) {
            if (empty(trim($coord))) {
                continue;
            }

            $parts = explode(',', trim($coord));
            if (count($parts) < 2 || count($parts) > 3) {
                throw new KmlException('Invalid coordinate format');
            }

            if (! is_numeric($parts[0]) || $parts[0] < -180 || $parts[0] > 180) {
                throw new KmlException("Invalid longitude value: {$parts[0]}");
            }

            if (! is_numeric($parts[1]) || $parts[1] < -90 || $parts[1] > 90) {
                throw new KmlException("Invalid latitude value: {$parts[1]}");
            }

            if (isset($parts[2]) && ! is_numeric($parts[2])) {
                throw new KmlException("Invalid altitude value: {$parts[2]}");
            }
        }
    }
}
