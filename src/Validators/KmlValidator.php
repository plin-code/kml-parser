<?php

namespace PlinCode\KmlParser\Validators;

use PlinCode\KmlParser\Enums\GeometryType;
use PlinCode\KmlParser\Exceptions\KmlException;
use SimpleXMLElement;

class KmlValidator
{
    /**
     * The namespaces a KML document is allowed to declare.
     *
     * 2.2 is the OGC standard. The earth.google.com variants predate the OGC
     * taking the format over, and exports carrying them are still in wide
     * circulation, so rejecting them outright rejects valid files.
     *
     * @var array<int, string>
     */
    public const DEFAULT_NAMESPACES = [
        'http://www.opengis.net/kml/2.2',
        'http://earth.google.com/kml/2.2',
        'http://earth.google.com/kml/2.1',
        'http://earth.google.com/kml/2.0',
    ];

    /** @var array<int, string> */
    protected array $namespaces;

    protected string $documentNamespace = '';

    protected SimpleXMLElement $xml;

    /**
     * @param  array<int, string>|null  $namespaces  Accepted namespaces, defaults to DEFAULT_NAMESPACES.
     */
    public function __construct(?array $namespaces = null)
    {
        $namespaces = array_values(array_filter($namespaces ?? self::DEFAULT_NAMESPACES));

        $this->namespaces = $namespaces !== [] ? $namespaces : self::DEFAULT_NAMESPACES;
    }

    /**
     * The namespace declared by the document that was validated last.
     */
    public function documentNamespace(): string
    {
        return $this->documentNamespace;
    }

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
        $declared = $namespaces[''] ?? null;

        if ($declared === null || ! in_array($declared, $this->namespaces, true)) {
            throw new KmlException('Invalid or missing KML namespace');
        }

        /*
         * XPath has to run against the namespace the document actually
         * declares, not the one we would have preferred it to use.
         */
        $this->documentNamespace = $declared;
        $xml->registerXPathNamespace('kml', $declared);

        if (empty($xml->Document)) {
            throw new KmlException('Missing required element: Document');
        }

        foreach ($xml->xpath('//kml:Placemark') ?: [] as $placemark) {
            $this->validatePlacemark($placemark);
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
