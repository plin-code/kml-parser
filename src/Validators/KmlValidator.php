<?php

namespace PlinCode\KmlParser\Validators;

use PlinCode\KmlParser\Enums\GeometryType;
use PlinCode\KmlParser\Exceptions\KmlException;
use SimpleXMLElement;

class KmlValidator
{
    protected string $namespace = 'http://www.opengis.net/kml/2.2';

    protected SimpleXMLElement $xml;

    public function validate(string $content): void
    {
        libxml_use_internal_errors(true);

        try {
            $this->xml = new SimpleXMLElement($content);

            $namespaces = $this->xml->getDocNamespaces();
            if (! isset($namespaces['']) || $namespaces[''] !== $this->namespace) {
                throw new KmlException('Invalid or missing KML namespace');
            }

            $this->xml->registerXPathNamespace('kml', $this->namespace);

            if (empty($this->xml->Document)) {
                throw new KmlException('Missing required element: Document');
            }

            $placemarks = $this->xml->xpath('//kml:Placemark');
            if (! empty($placemarks)) {
                foreach ($placemarks as $placemark) {
                    $this->validatePlacemark($placemark);
                }
            }
        } catch (KmlException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new KmlException('Invalid KML content: '.$e->getMessage());
        } finally {
            libxml_clear_errors();
        }
    }

    protected function validatePlacemark(SimpleXMLElement $placemark): void
    {
        $hasGeometry = false;
        foreach (GeometryType::cases() as $type) {
            if ($placemark->{$type->value}) {
                $hasGeometry = true;
                $this->validateGeometryCoordinates($placemark->{$type->value}, $type->value);
                break;
            }
        }

        if (! $hasGeometry) {
            throw new KmlException('Found Placemark without valid geometry');
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
