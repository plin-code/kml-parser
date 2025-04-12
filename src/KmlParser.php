<?php

namespace PlinCode\KmlParser;

use Exception;
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
        $this->validator = new KmlValidator;
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
        try {
            $this->validator->validate($content);
            libxml_use_internal_errors(true);
            $this->xml = new SimpleXMLElement($content);

            $errors = libxml_get_errors();
            if ($errors) {
                $errorMessage = $errors[0]->message;
                libxml_clear_errors();
                throw KmlParserException::invalidXml($errorMessage);
            }

            $this->xml->registerXPathNamespace('kml', $this->namespace);

            return $this;
        } catch (\Exception $e) {
            libxml_clear_errors();
            throw KmlParserException::failedToParse($e->getMessage());
        }
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
                'name' => (string) ($placemarkXml->name ?: $placemarkXml->n),
                'description' => (string) $placemarkXml->description,
            ];

            if ($placemarkXml->Point) {
                $coords = (string) $placemarkXml->Point->coordinates;
                $coordsArray = explode(',', trim($coords));
                $placemark['type'] = 'Point';
                $placemark['coordinates'] = [
                    'longitude' => (float) $coordsArray[0],
                    'latitude' => (float) $coordsArray[1],
                    'altitude' => isset($coordsArray[2]) ? (float) $coordsArray[2] : 0,
                ];
            }

            if ($placemarkXml->LineString) {
                $coords = (string) $placemarkXml->LineString->coordinates;
                $placemark['type'] = 'LineString';
                $placemark['coordinates'] = $this->parseLineStringCoordinates($coords);
            }

            if ($placemarkXml->Polygon) {
                $placemark['type'] = 'Polygon';
                $placemark['coordinates'] = $this->parsePolygonCoordinates($placemarkXml->Polygon);
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
        $placemarks = $this->getPlacemarks();

        foreach ($placemarks as $placemark) {
            if (isset($placemark['coordinates'])) {
                $feature = [
                    'type' => 'Feature',
                    'properties' => [
                        'name' => $placemark['name'],
                        'description' => $placemark['description'],
                    ],
                ];

                // Set geometry based on type
                if ($placemark['type'] === 'Point') {
                    $feature['geometry'] = [
                        'type' => 'Point',
                        'coordinates' => [
                            $placemark['coordinates']['longitude'],
                            $placemark['coordinates']['latitude'],
                            $placemark['coordinates']['altitude'],
                        ],
                    ];
                } elseif ($placemark['type'] === 'LineString') {
                    $coordinates = [];
                    foreach ($placemark['coordinates'] as $coord) {
                        $coordinates[] = [
                            $coord['longitude'],
                            $coord['latitude'],
                            $coord['altitude'],
                        ];
                    }

                    $feature['geometry'] = [
                        'type' => 'LineString',
                        'coordinates' => $coordinates,
                    ];
                } elseif ($placemark['type'] === 'Polygon') {
                    $outerCoordinates = [];
                    foreach ($placemark['coordinates']['outerBoundary'] as $coord) {
                        $outerCoordinates[] = [
                            $coord['longitude'],
                            $coord['latitude'],
                            $coord['altitude'],
                        ];
                    }

                    $innerCoordinates = [];
                    foreach ($placemark['coordinates']['innerBoundaries'] as $innerBoundary) {
                        $innerBoundaryCoords = [];
                        foreach ($innerBoundary as $coord) {
                            $innerBoundaryCoords[] = [
                                $coord['longitude'],
                                $coord['latitude'],
                                $coord['altitude'],
                            ];
                        }
                        $innerCoordinates[] = $innerBoundaryCoords;
                    }

                    $allCoordinates = [$outerCoordinates];
                    if (! empty($innerCoordinates)) {
                        $allCoordinates = array_merge($allCoordinates, $innerCoordinates);
                    }

                    $feature['geometry'] = [
                        'type' => 'Polygon',
                        'coordinates' => $allCoordinates,
                    ];
                }

                if (isset($placemark['styleUrl'])) {
                    $feature['properties']['styleUrl'] = $placemark['styleUrl'];
                }

                if (isset($placemark['extendedData'])) {
                    $feature['properties']['extendedData'] = $placemark['extendedData'];
                }

                $features[] = $feature;
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
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
        } elseif (! empty($document) && isset($document[0]->n)) {
            return (string) $document[0]->n;
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
