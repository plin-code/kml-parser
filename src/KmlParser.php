<?php

namespace PlinCode\KmlParser;

use Exception;
use PlinCode\KmlParser\Enums\GeometryType;
use PlinCode\KmlParser\Exceptions\KmlParserException;
use PlinCode\KmlParser\Traits\ParsesCoordinates;
use PlinCode\KmlParser\Validators\KmlValidator;
use SimpleXMLElement;

class KmlParser
{
    use ParsesCoordinates;

    protected ?SimpleXMLElement $xml = null;

    protected string $namespace = 'http://www.opengis.net/kml/2.2';

    protected KmlValidator $validator;

    public function __construct()
    {
        $this->namespace = config('kml-parser.namespace', $this->namespace);
        $this->validator = new KmlValidator($this->supportedNamespaces());
    }

    /**
     * Namespaces a document is allowed to declare: the configured primary one
     * plus every variant listed in the config.
     *
     * @return array<int, string>
     */
    protected function supportedNamespaces(): array
    {
        $supported = config('kml-parser.supported_namespaces', KmlValidator::DEFAULT_NAMESPACES);

        return array_values(array_unique(array_merge([$this->namespace], (array) $supported)));
    }

    /**
     * Load KML from a file
     *
     * @throws Exception
     */
    public function loadFromFile(string $path): self
    {
        if (! file_exists($path)) {
            throw KmlParserException::fileNotFound($path);
        }

        return $this->loadFromString(file_get_contents($path));
    }

    /**
     * Load KML from a string
     *
     * @throws Exception
     */
    public function loadFromString(string $content): self
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = new SimpleXMLElement($content);
        } catch (Exception $e) {
            throw KmlParserException::invalidXml($e->getMessage());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $this->validator->validateDocument($xml);

        $this->xml = $xml;
        $this->xml->registerXPathNamespace('kml', $this->validator->documentNamespace());

        return $this;
    }

    /**
     * Load KML from a KMZ file
     *
     * @throws Exception
     */
    public function loadFromKmz(string $path): self
    {
        $extractor = new KmzExtractor;
        $kmlContent = $extractor->extractKmlContent($path);

        return $this->loadFromString($kmlContent);
    }

    /**
     * Get Placemarks Node from the KML
     *
     * @throws Exception
     */
    public function getPlacemarks(): array
    {
        if (! $this->xml) {
            throw KmlParserException::noDataLoaded();
        }

        $placemarks = [];
        $placemarksXml = $this->xml->xpath('//kml:Placemark');

        foreach ($placemarksXml as $placemarkXml) {
            $placemark = [
                'name' => (string) $placemarkXml->name,
                'description' => (string) $placemarkXml->description,
            ];

            foreach (GeometryType::cases() as $type) {
                if ($placemarkXml->{$type->value}) {
                    $geometry = $this->parseGeometry($type, $placemarkXml->{$type->value});

                    if ($geometry !== null) {
                        $placemark = array_merge($placemark, $geometry);
                    }

                    break;
                }
            }

            if ($placemarkXml->styleUrl) {
                $placemark['styleUrl'] = (string) $placemarkXml->styleUrl;
            }

            if ($placemarkXml->ExtendedData) {
                $extendedData = [];
                foreach ($placemarkXml->ExtendedData->Data as $data) {
                    $name = (string) $data->attributes()->name;
                    $value = (string) $data->value;
                    $extendedData[$name] = $value;
                }
                $placemark['extendedData'] = $extendedData;
            }

            $placemarks[] = $placemark;
        }

        return $placemarks;
    }

    /**
     * Turn one KML geometry element into its array representation.
     *
     * @return array<string, mixed>|null
     */
    protected function parseGeometry(GeometryType $type, SimpleXMLElement $geometry): ?array
    {
        return match ($type) {
            GeometryType::POINT => [
                'type' => $type->value,
                'coordinates' => $this->parsePointCoordinates((string) $geometry->coordinates),
            ],
            GeometryType::LINE_STRING => [
                'type' => $type->value,
                'coordinates' => $this->parseLineStringCoordinates((string) $geometry->coordinates),
            ],
            GeometryType::POLYGON => [
                'type' => $type->value,
                'coordinates' => $this->parsePolygonCoordinates($geometry),
            ],
            GeometryType::MULTI_GEOMETRY => [
                'type' => $type->value,
                'geometries' => $this->parseMultiGeometry($geometry),
            ],
        };
    }

    /**
     * A MultiGeometry holds nested geometries instead of coordinates, and KML
     * allows those to be MultiGeometry elements in turn.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseMultiGeometry(SimpleXMLElement $multiGeometry): array
    {
        $geometries = [];

        foreach ($multiGeometry->children() as $name => $child) {
            $type = GeometryType::tryFrom((string) $name);

            if ($type === null) {
                continue;
            }

            $geometry = $this->parseGeometry($type, $child);

            if ($geometry !== null) {
                $geometries[] = $geometry;
            }
        }

        return $geometries;
    }

    /**
     * Get Style Node from the KML
     *
     * @throws Exception
     */
    public function getStyles(): array
    {
        if (! $this->xml) {
            throw new Exception('No KML data loaded');
        }

        $styles = [];
        $stylesXml = $this->xml->xpath('//kml:Style');

        foreach ($stylesXml as $styleXml) {
            $id = (string) $styleXml->attributes()->id;

            /*
             * A Style declared inline on a Placemark carries no id and cannot be
             * referenced through a styleUrl. Keeping it here would make every
             * anonymous style collide under the same empty key, so only shared
             * styles end up in the returned map.
             */
            if ($id === '') {
                continue;
            }

            $style = [
                'id' => $id,
            ];

            if ($styleXml->IconStyle) {
                $style['iconStyle'] = [
                    'scale' => (float) $styleXml->IconStyle->scale,
                ];

                if ($styleXml->IconStyle->Icon && $styleXml->IconStyle->Icon->href) {
                    $style['iconStyle']['href'] = (string) $styleXml->IconStyle->Icon->href;
                }

                if ($styleXml->IconStyle->hotSpot) {
                    $hotSpot = $styleXml->IconStyle->hotSpot;
                    $style['iconStyle']['hotSpot'] = [
                        'x' => (float) $hotSpot->attributes()->x,
                        'y' => (float) $hotSpot->attributes()->y,
                        'xunits' => (string) $hotSpot->attributes()->xunits,
                        'yunits' => (string) $hotSpot->attributes()->yunits,
                    ];
                }
            }

            if ($styleXml->LabelStyle) {
                $style['labelStyle'] = [
                    'scale' => (float) $styleXml->LabelStyle->scale,
                ];

                if ($styleXml->LabelStyle->color) {
                    $style['labelStyle']['color'] = (string) $styleXml->LabelStyle->color;
                }
            }

            $styles[$id] = $style;
        }

        return $styles;
    }

    /**
     * Get StyleMap Node from the KML
     *
     * @throws Exception
     */
    public function getStyleMaps(): array
    {
        if (! $this->xml) {
            throw new Exception('No KML data loaded');
        }

        $styleMaps = [];
        $styleMapsXml = $this->xml->xpath('//kml:StyleMap');

        foreach ($styleMapsXml as $styleMapXml) {
            $id = (string) $styleMapXml->attributes()->id;
            $styleMap = [
                'id' => $id,
                'pairs' => [],
            ];

            foreach ($styleMapXml->Pair as $pairXml) {
                $key = (string) $pairXml->key;
                $styleUrl = (string) $pairXml->styleUrl;

                $styleMap['pairs'][$key] = $styleUrl;
            }

            $styleMaps[$id] = $styleMap;
        }

        return $styleMaps;
    }

    /**
     * Convert data to GeoJSON format
     *
     * @throws Exception
     */
    public function toGeoJson(): array
    {
        $features = [];

        foreach ($this->getPlacemarks() as $placemark) {
            $geometry = $this->toGeoJsonGeometry($placemark);

            if ($geometry === null) {
                continue;
            }

            $feature = [
                'type' => 'Feature',
                'properties' => [
                    'name' => $placemark['name'],
                    'description' => $placemark['description'],
                ],
                'geometry' => $geometry,
            ];

            if (isset($placemark['styleUrl'])) {
                $feature['properties']['styleUrl'] = $placemark['styleUrl'];
            }

            if (isset($placemark['extendedData'])) {
                $feature['properties']['extendedData'] = $placemark['extendedData'];
            }

            $features[] = $feature;
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * A KML MultiGeometry maps onto a GeoJSON GeometryCollection, which nests
     * the same way, so this recurses alongside parseMultiGeometry().
     *
     * @param  array<string, mixed>  $geometry
     * @return array<string, mixed>|null
     */
    protected function toGeoJsonGeometry(array $geometry): ?array
    {
        return match ($geometry['type'] ?? null) {
            GeometryType::POINT->value => [
                'type' => 'Point',
                'coordinates' => $this->toGeoJsonPosition($geometry['coordinates']),
            ],
            GeometryType::LINE_STRING->value => [
                'type' => 'LineString',
                'coordinates' => array_map(
                    fn (array $position) => $this->toGeoJsonPosition($position),
                    $geometry['coordinates'],
                ),
            ],
            GeometryType::POLYGON->value => [
                'type' => 'Polygon',
                'coordinates' => $this->toGeoJsonRings($geometry['coordinates']),
            ],
            GeometryType::MULTI_GEOMETRY->value => [
                'type' => 'GeometryCollection',
                'geometries' => array_values(array_filter(array_map(
                    fn (array $child) => $this->toGeoJsonGeometry($child),
                    $geometry['geometries'],
                ))),
            ],
            default => null,
        };
    }

    /**
     * @param  array{longitude: float, latitude: float, altitude: float}  $position
     * @return array<int, float>
     */
    protected function toGeoJsonPosition(array $position): array
    {
        return [$position['longitude'], $position['latitude'], $position['altitude']];
    }

    /**
     * GeoJSON puts the outer ring first and every inner ring after it.
     *
     * @param  array{outerBoundary: array<int, array<string, float>>, innerBoundaries: array<int, array<int, array<string, float>>>}  $boundaries
     * @return array<int, array<int, array<int, float>>>
     */
    protected function toGeoJsonRings(array $boundaries): array
    {
        $rings = [
            array_map(
                fn (array $position) => $this->toGeoJsonPosition($position),
                $boundaries['outerBoundary'],
            ),
        ];

        foreach ($boundaries['innerBoundaries'] as $innerBoundary) {
            $rings[] = array_map(
                fn (array $position) => $this->toGeoJsonPosition($position),
                $innerBoundary,
            );
        }

        return $rings;
    }

    /**
     * Get Document Node from the KML
     *
     * @throws Exception
     */
    public function getDocumentName(): ?string
    {
        if (! $this->xml) {
            throw new Exception('No KML data loaded');
        }

        $document = $this->xml->xpath('//kml:Document');
        if (! empty($document) && isset($document[0]->name)) {
            return (string) $document[0]->name;
        }

        return null;
    }

    /**
     * Get Document Description from the KML
     *
     * @throws Exception
     */
    public function getDocumentDescription(): ?string
    {
        if (! $this->xml) {
            throw new Exception('No KML data loaded');
        }

        $document = $this->xml->xpath('//kml:Document');
        if (! empty($document) && isset($document[0]->description)) {
            return (string) $document[0]->description;
        }

        return null;
    }
}
