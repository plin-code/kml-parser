<?php

namespace PlinCode\KmlParser;

use Exception;
use PlinCode\KmlParser\Enums\GeometryType;
use PlinCode\KmlParser\Exceptions\KmlParserException;
use PlinCode\KmlParser\Traits\ParsesCoordinates;
use PlinCode\KmlParser\Traits\ReadsPackageConfig;
use PlinCode\KmlParser\Validators\KmlValidator;
use SimpleXMLElement;

/**
 * @phpstan-import-type Position from ParsesCoordinates
 * @phpstan-import-type PolygonBoundaries from ParsesCoordinates
 *
 * @phpstan-type Placemark array<string, mixed>
 * @phpstan-type Geometry array<string, mixed>
 * @phpstan-type Style array<string, mixed>
 */
class KmlParser
{
    use ParsesCoordinates;
    use ReadsPackageConfig;

    protected ?SimpleXMLElement $xml = null;

    protected string $namespace = 'http://www.opengis.net/kml/2.2';

    protected string $documentNamespace = 'http://www.opengis.net/kml/2.2';

    protected KmlValidator $validator;

    public function __construct()
    {
        $namespace = $this->packageConfig('kml-parser.namespace', $this->namespace);

        if (is_string($namespace) && $namespace !== '') {
            $this->namespace = $namespace;
        }

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
        $supported = $this->packageConfig('kml-parser.supported_namespaces', KmlValidator::DEFAULT_NAMESPACES);
        $supported = is_array($supported) ? $supported : [];

        $namespaces = [$this->namespace];

        foreach ($supported as $namespace) {
            if (is_string($namespace) && $namespace !== '') {
                $namespaces[] = $namespace;
            }
        }

        return array_values(array_unique($namespaces));
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

        /*
         * The warning file_get_contents() raises carries less than the
         * exception below, and an application turning warnings into
         * exceptions would otherwise get that one instead of ours. The return
         * value is what is acted on.
         */
        $content = @file_get_contents($path);

        if ($content === false) {
            throw KmlParserException::failedToRead($path);
        }

        return $this->loadFromString($content);
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
        $this->documentNamespace = $this->validator->documentNamespace();
        $this->xml->registerXPathNamespace('kml', $this->documentNamespace);

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
     * @return list<Placemark>
     *
     * @throws Exception
     */
    public function getPlacemarks(): array
    {
        if (! $this->xml) {
            throw KmlParserException::noDataLoaded();
        }

        $placemarks = [];
        $placemarksXml = $this->xml->xpath('//kml:Placemark') ?: [];

        foreach ($placemarksXml as $placemarkXml) {
            $placemark = [
                'name' => (string) $placemarkXml->name,
                'description' => (string) $placemarkXml->description,
                'folder' => $this->folderPath($placemarkXml),
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
                $placemark['extendedData'] = $this->parseExtendedData($placemarkXml->ExtendedData);
            }

            $placemarks[] = $placemark;
        }

        return $placemarks;
    }

    /**
     * The Folder elements containing a Placemark, outermost first.
     *
     * Placemarks are collected with a flat //kml:Placemark query, which is
     * what makes a Folder invisible in the result. Rather than walking the
     * tree twice, each Placemark is asked for its own ancestors.
     *
     * A Folder without a name contributes an empty string, so the length of
     * the path always matches the real nesting depth.
     *
     * @return array<int, string>
     */
    protected function folderPath(SimpleXMLElement $placemark): array
    {
        $placemark->registerXPathNamespace('kml', $this->documentNamespace);

        $path = [];

        foreach ($placemark->xpath('ancestor::kml:Folder') ?: [] as $folder) {
            $path[] = (string) $folder->name;
        }

        return $path;
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
     * @return array<string, Style>
     *
     * @throws Exception
     */
    public function getStyles(): array
    {
        if (! $this->xml) {
            throw new Exception('No KML data loaded');
        }

        $styles = [];
        $stylesXml = $this->xml->xpath('//kml:Style') ?: [];

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

            if ($styleXml->LineStyle) {
                $style['lineStyle'] = $this->parseLineStyle($styleXml->LineStyle);
            }

            if ($styleXml->PolyStyle) {
                $style['polyStyle'] = $this->parsePolyStyle($styleXml->PolyStyle);
            }

            $styles[$id] = $style;
        }

        return $styles;
    }

    /**
     * KML carries typed attributes two different ways: <Data> pairs, and
     * <SimpleData> entries inside a <SchemaData> block, which is what ogr2ogr
     * and QGIS emit. Both land in the same map, SimpleData last so an explicit
     * schema value wins over a plain Data pair of the same name.
     *
     * @return array<string, string>
     */
    protected function parseExtendedData(SimpleXMLElement $extendedData): array
    {
        $parsed = [];

        foreach ($extendedData->Data as $data) {
            $parsed[(string) $data->attributes()->name] = (string) $data->value;
        }

        foreach ($extendedData->SchemaData as $schemaData) {
            foreach ($schemaData->SimpleData as $simpleData) {
                $parsed[(string) $simpleData->attributes()->name] = (string) $simpleData;
            }
        }

        return $parsed;
    }

    /**
     * The stroke of a LineString and the outline of a Polygon.
     *
     * Only the elements the document actually declares are reported. KML
     * defines defaults for both, but filling them in here would stop the
     * caller from telling "the file said nothing" apart from "the file said
     * exactly the default".
     *
     * @return array<string, string|float>
     */
    protected function parseLineStyle(SimpleXMLElement $lineStyle): array
    {
        $parsed = [];

        if (isset($lineStyle->color)) {
            $parsed['color'] = (string) $lineStyle->color;
        }

        if (isset($lineStyle->width)) {
            $parsed['width'] = (float) $lineStyle->width;
        }

        return $parsed;
    }

    /**
     * The fill of a Polygon. `fill` and `outline` are the KML booleans 0 and 1,
     * and are read with isset() rather than a truthiness check so that an
     * explicit <fill>0</fill> is reported instead of being dropped.
     *
     * @return array<string, string|bool>
     */
    protected function parsePolyStyle(SimpleXMLElement $polyStyle): array
    {
        $parsed = [];

        if (isset($polyStyle->color)) {
            $parsed['color'] = (string) $polyStyle->color;
        }

        if (isset($polyStyle->fill)) {
            $parsed['fill'] = (string) $polyStyle->fill === '1';
        }

        if (isset($polyStyle->outline)) {
            $parsed['outline'] = (string) $polyStyle->outline === '1';
        }

        return $parsed;
    }

    /**
     * Get StyleMap Node from the KML
     *
     * @return array<string, array{id: string, pairs: array<string, string>}>
     *
     * @throws Exception
     */
    public function getStyleMaps(): array
    {
        if (! $this->xml) {
            throw new Exception('No KML data loaded');
        }

        $styleMaps = [];
        $styleMapsXml = $this->xml->xpath('//kml:StyleMap') ?: [];

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
     * @return array{type: string, features: list<array<string, mixed>>}
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

            if ($placemark['folder'] !== []) {
                $feature['properties']['folder'] = $placemark['folder'];
            }

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
     * @param  array<mixed>  $geometry
     * @return array<string, mixed>|null
     */
    protected function toGeoJsonGeometry(array $geometry): ?array
    {
        $type = $geometry['type'] ?? null;

        if ($type === GeometryType::MULTI_GEOMETRY->value) {
            return [
                'type' => 'GeometryCollection',
                'geometries' => $this->toGeoJsonGeometries($geometry['geometries'] ?? []),
            ];
        }

        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return null;
        }

        return match ($type) {
            GeometryType::POINT->value => [
                'type' => 'Point',
                'coordinates' => $this->toGeoJsonPosition($coordinates),
            ],
            GeometryType::LINE_STRING->value => [
                'type' => 'LineString',
                'coordinates' => $this->toGeoJsonPositions($coordinates),
            ],
            GeometryType::POLYGON->value => [
                'type' => 'Polygon',
                'coordinates' => $this->toGeoJsonRings($coordinates),
            ],
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function toGeoJsonGeometries(mixed $geometries): array
    {
        if (! is_array($geometries)) {
            return [];
        }

        $converted = [];

        foreach ($geometries as $child) {
            if (! is_array($child)) {
                continue;
            }

            $geometry = $this->toGeoJsonGeometry($child);

            if ($geometry !== null) {
                $converted[] = $geometry;
            }
        }

        return $converted;
    }

    /**
     * @param  array<mixed>  $positions
     * @return list<list<float>>
     */
    protected function toGeoJsonPositions(array $positions): array
    {
        $converted = [];

        foreach ($positions as $position) {
            if (is_array($position)) {
                $converted[] = $this->toGeoJsonPosition($position);
            }
        }

        return $converted;
    }

    /**
     * A coordinate map as parsePointCoordinates() produces it, turned into the
     * GeoJSON position order. Anything not numeric reads as 0.0 rather than
     * throwing, so one malformed coordinate cannot take a whole document down.
     *
     * @param  array<mixed>  $position
     * @return list<float>
     */
    protected function toGeoJsonPosition(array $position): array
    {
        return [
            $this->toFloat($position['longitude'] ?? null),
            $this->toFloat($position['latitude'] ?? null),
            $this->toFloat($position['altitude'] ?? null),
        ];
    }

    protected function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * GeoJSON puts the outer ring first and every inner ring after it.
     *
     * @param  array<mixed>  $boundaries
     * @return list<list<list<float>>>
     */
    protected function toGeoJsonRings(array $boundaries): array
    {
        $outer = $boundaries['outerBoundary'] ?? [];
        $inner = $boundaries['innerBoundaries'] ?? [];

        $rings = [is_array($outer) ? $this->toGeoJsonPositions($outer) : []];

        if (! is_array($inner)) {
            return $rings;
        }

        foreach ($inner as $innerBoundary) {
            if (is_array($innerBoundary)) {
                $rings[] = $this->toGeoJsonPositions($innerBoundary);
            }
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
